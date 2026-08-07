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

AuthMiddleware::check($conn);
$pdo = DbConnection::getInstance();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $submittedToken = $_POST['_csrf_token'] ?? '';
    $storedToken = $_SESSION['csrf_token'] ?? '';
    if (empty($storedToken) || !hash_equals($storedToken, $submittedToken)) {
        throw new Exception('Security token mismatch (CSRF). Please refresh the page.');
    }

    $action = trim($_POST['action'] ?? '');
    $userId = $_SESSION['user_id'] ?? 1;

    switch ($action) {
        case 'save':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $name = trim($_POST['name'] ?? '');
            $body = trim($_POST['template_body'] ?? '');
            $isDefault = !empty($_POST['is_default']) ? 1 : 0;

            if (empty($name)) {
                throw new Exception('Template name is required.');
            }
            if (empty($body)) {
                throw new Exception('Template body content is required.');
            }

            if ($isDefault) {
                // Reset other default templates
                $pdo->query("UPDATE sm_templates SET is_default = 0");
            }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE sm_templates 
                    SET name = ?, template_body = ?, is_default = ?, updated_at = NOW() 
                    WHERE id = ?");
                $stmt->execute([$name, $body, $isDefault, $id]);
                $msg = "Template updated successfully!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO sm_templates 
                    (name, template_body, is_default, created_by) 
                    VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $body, $isDefault, $userId]);
                $msg = "New template created successfully!";
            }

            echo json_encode(['success' => true, 'message' => $msg]);
            break;

        case 'set_default':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception('Invalid template ID');

            $pdo->query("UPDATE sm_templates SET is_default = 0");
            $stmt = $pdo->prepare("UPDATE sm_templates SET is_default = 1 WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Default template updated!']);
            break;

        case 'clone':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception('Invalid template ID');

            $stmt = $pdo->prepare("SELECT * FROM sm_templates WHERE id = ?");
            $stmt->execute([$id]);
            $tpl = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tpl) throw new Exception('Template not found');

            $newName = $tpl['name'] . ' (Copy)';
            $insStmt = $pdo->prepare("INSERT INTO sm_templates 
                (name, template_body, is_default, created_by) 
                VALUES (?, ?, 0, ?)");
            $insStmt->execute([$newName, $tpl['template_body'], $userId]);

            echo json_encode(['success' => true, 'message' => 'Template cloned successfully!']);
            break;

        case 'delete':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new Exception('Invalid template ID');

            $stmt = $pdo->prepare("DELETE FROM sm_templates WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Template deleted successfully!']);
            break;

        default:
            throw new Exception('Invalid action requested.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
