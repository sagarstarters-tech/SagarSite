<?php
declare(strict_types=1);
ob_start(); // Buffer output to prevent session_setup.php header issues

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

// Clean output buffer and set JSON header after all includes are done
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=UTF-8');

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
    if ($intervalMinutes < 1) $intervalMinutes = 5;

    // Parse platforms — prioritize explicit selected array, fallback to JSON
    $platforms = [];
    if (!empty($_POST['platforms'])) {
        if (is_array($_POST['platforms'])) {
            $platforms = $_POST['platforms'];
        } elseif (is_string($_POST['platforms'])) {
            $decoded = json_decode($_POST['platforms'], true);
            if (is_array($decoded) && !empty($decoded)) {
                $platforms = $decoded;
            } else {
                $platforms = [$_POST['platforms']];
            }
        }
    }
    $platforms = array_values(array_unique(array_filter(array_map(fn($p) => strtolower(trim($p)), $platforms))));

    if (empty($platforms)) {
        throw new Exception('Please select at least one social media platform.');
    }

    // 1. Fetch matching products
    $products = [];
    if ($filterType === 'all') {
        $stmt = $pdo->query("SELECT * FROM products ORDER BY id ASC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($filterType === 'category' && !empty($filterValue)) {
        $catVal = trim((string)$filterValue);
        // Support both category_id (int) and category name (string)
        if (ctype_digit($catVal)) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ?");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE category = ?");
        }
        $stmt->execute([$catVal]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($filterType === 'brand' && !empty($filterValue)) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE brand = ?");
        $stmt->execute([trim((string)$filterValue)]);
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
    $rawAccounts = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);

    // Map 1 active account per platform to prevent multi-account duplication for the same platform
    $connectedAccounts = [];
    foreach ($rawAccounts as $acc) {
        $pKey = strtolower(trim($acc['platform']));
        if (!isset($connectedAccounts[$pKey])) {
            $connectedAccounts[$pKey] = $acc;
        }
    }

    if (empty($connectedAccounts)) {
        throw new Exception('No active connected accounts found for the selected platforms. Please connect accounts on the Accounts page first.');
    }

    // 3. Fetch template
    $templateBody = "🔥 PREMIUM PRODUCT SPOTLIGHT 🔥\n\n"
                  . "✨ {product_name}\n\n"
                  . "💰 Best Price: ₹{price}\n"
                  . "✅ Guaranteed Quality & Heavy Duty Performance\n"
                  . "🚚 Express Shipping Across India\n\n"
                  . "🛒 Order Direct Here: {product_url}\n\n"
                  . "{cta}\n\n"
                  . "{hashtags}";
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
            // Duplicate Check: Skip if this product is already queued for this platform+account (including failed within 24h)
            $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM sm_queue 
                WHERE product_id = ? AND LOWER(platform) = ? AND account_id = ?
                AND status IN ('pending', 'scheduled', 'publishing', 'failed')
                AND scheduled_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $chkStmt->execute([$prod['id'], strtolower($acc['platform']), $acc['id']]);
            if ($chkStmt->fetchColumn() > 0) {
                continue; // Skip - already in queue for this platform+account recently
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
    if (ob_get_length()) ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
