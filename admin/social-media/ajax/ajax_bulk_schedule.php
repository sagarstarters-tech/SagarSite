<?php
declare(strict_types=1);
header('Content-Type: application/json');

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/session_setup.php';
require_once BASE_PATH . '/includes/db_connect.php';
require_once BASE_PATH . '/admin/core/AuthMiddleware.php';
require_once BASE_PATH . '/admin/helpers/csrf.php';
require_once BASE_PATH . '/config/DbConnection.php';
require_once BASE_PATH . '/admin/social-media/services/TemplateEngine.php';

use Admin\SocialMedia\Services\TemplateEngine;

AuthMiddleware::check($conn);
$pdo = DbConnection::getInstance();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $submittedToken = $_POST['_csrf_token'] ?? '';
    $storedToken = $_SESSION['csrf_token'] ?? '';
    if (empty($storedToken) || !hash_equals($storedToken, $submittedToken)) {
        throw new Exception('Security token mismatch (CSRF). Please refresh the page and try again.');
    }

    $filterType = $_POST['filter_type'] ?? 'all';
    $filterValue = $_POST['filter_value'] ?? null;
    $templateId = filter_input(INPUT_POST, 'template_id', FILTER_VALIDATE_INT) ?: null;
    $cta = trim($_POST['cta'] ?? '');
    $hashtags = trim($_POST['hashtags'] ?? '');
    $intervalMinutes = filter_input(INPUT_POST, 'interval_minutes', FILTER_VALIDATE_INT) ?: 15;

    // Parse platforms
    $rawPlatforms = $_POST['platforms'] ?? [];
    if (is_string($rawPlatforms)) {
        $platforms = json_decode($rawPlatforms, true) ?: [];
    } else {
        $platforms = (array)$rawPlatforms;
    }

    if (empty($platforms)) {
        throw new Exception('Please select at least one social media platform.');
    }

    // 1. Fetch matching products
    $products = [];
    if ($filterType === 'all') {
        $stmt = $pdo->query("SELECT * FROM products");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($filterType === 'category' && !empty($filterValue)) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ?");
        $stmt->execute([$filterValue]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($filterType === 'brand' && !empty($filterValue)) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE brand = ?");
        $stmt->execute([$filterValue]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($filterType === 'selected') {
        $selectedIds = is_array($filterValue) ? $filterValue : json_decode($filterValue ?: '[]', true);
        if (!empty($selectedIds)) {
            $inClause = implode(',', array_map('intval', $selectedIds));
            $stmt = $pdo->query("SELECT * FROM products WHERE id IN ($inClause)");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (empty($products)) {
        throw new Exception('No products found matching the selected criteria.');
    }

    // 2. Fetch active connected accounts for selected platforms
    $inPlatforms = implode(',', array_map(function($p) use ($pdo) {
        return $pdo->quote(strtolower(trim($p)));
    }, $platforms));

    $stmtAcc = $pdo->query("SELECT * FROM sm_connected_accounts WHERE is_active = 1 AND LOWER(platform) IN ($inPlatforms)");
    $connectedAccounts = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);

    if (empty($connectedAccounts)) {
        throw new Exception('No active connected accounts found for the selected platforms. Please connect accounts on the Accounts page first.');
    }

    // 3. Fetch template
    $templateBody = "Check out {product_name}! Price: ₹{price}\nShop here: {product_url}\n{hashtags}\n{cta}";
    if ($templateId) {
        $stmtTpl = $pdo->prepare("SELECT template_body FROM sm_templates WHERE id = ?");
        $stmtTpl->execute([$templateId]);
        $tpl = $stmtTpl->fetchColumn();
        if ($tpl) {
            $templateBody = $tpl;
        }
    }

    $templateEngine = new TemplateEngine();
    $staggerIndex = 0;
    $totalPostsScheduled = 0;
    $userId = $_SESSION['user_id'] ?? 1;

    $stmtQueue = $pdo->prepare("INSERT INTO sm_queue 
        (product_id, platform, account_id, template_id, status, post_content, post_image_url, post_link, scheduled_at, max_retries) 
        VALUES (?, ?, ?, ?, 'scheduled', ?, ?, ?, ?, 3)");

    foreach ($products as $prod) {
        // Resolve image URL
        $imgUrl = resolve_product_image_url($prod['image'] ?? '', $conn, (int)$prod['id']);

        // Resolve product URL
        $prodUrl = SITE_URL . '/product.php?id=' . $prod['id'];
        if (!empty($prod['slug'])) {
            $prodUrl = SITE_URL . '/product/' . $prod['slug'];
        }

        foreach ($connectedAccounts as $acc) {
            // Duplicate Check: Skip if this product is already queued (any active/waiting status)
            // This prevents duplicates when Bulk Schedule is run multiple times
            $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM sm_queue 
                WHERE product_id = ? AND platform = ? AND account_id = ? 
                AND status IN ('pending', 'scheduled', 'publishing')");
            $chkStmt->execute([$prod['id'], strtolower($acc['platform']), $acc['id']]);
            if ($chkStmt->fetchColumn() > 0) {
                continue; // Skip - already in active queue
            }

            $renderedContent = $templateEngine->render($templateBody, $prod, [
                'hashtags' => $hashtags,
                'cta' => $cta,
                'cta_text' => $cta
            ]);

            // Stagger posts by intervalMinutes
            // NOTE: $staggerIndex increments ONLY when we actually insert, not when skipped
            $scheduledTimestamp = time() + ($staggerIndex * $intervalMinutes * 60);
            $scheduledAt = date('Y-m-d H:i:s', $scheduledTimestamp);

            $stmtQueue->execute([
                $prod['id'],
                strtolower($acc['platform']),
                $acc['id'],
                $templateId,
                $renderedContent,
                $imgUrl,
                $prodUrl,
                $scheduledAt
            ]);
            $staggerIndex++; // Only increment AFTER successful insert
            $totalPostsScheduled++;
        }
    }

    // Record Bulk Job
    $stmtJob = $pdo->prepare("INSERT INTO sm_bulk_jobs 
        (job_name, filter_type, filter_value, template_id, total_products, processed_products, status, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, 'completed', ?)");
    $stmtJob->execute([
        'Bulk Schedule - ' . date('Y-m-d H:i'),
        $filterType,
        json_encode($filterValue),
        $templateId,
        count($products),
        count($products),
        $userId
    ]);

    echo json_encode([
        'success' => true,
        'message' => "Successfully scheduled {$totalPostsScheduled} post(s) for " . count($products) . " product(s) across " . count($connectedAccounts) . " platform account(s)!",
        'data' => [
            'total_products' => count($products),
            'total_posts' => $totalPostsScheduled,
            'total_accounts' => count($connectedAccounts)
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
