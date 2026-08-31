<?php
declare(strict_types=1);

/**
 * ============================================================
 *  BHARATSHIP INBOUND WEBHOOK RECEIVER
 *  Location: /courier_module/Webhooks/bharatship_webhook.php
 * ============================================================
 *  Receives real-time shipment milestone and delivery status
 *  updates pushed by BharatShip logistics servers.
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/db_connect.php';
require_once BASE_PATH . '/config/DbConnection.php';

$pdo = DbConnection::getInstance();

// 1. Handle BharatShip Probe / URL Verification Pings (GET, HEAD, or empty test POST)
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'HEAD') {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'BharatShip Webhook Endpoint Active (200 OK)']);
    exit;
}

// Read raw JSON payload
$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true);

// If empty ping or test verification probe, respond 200 OK
if (empty($data) || !is_array($data) || isset($data['test']) || isset($data['ping']) || empty($data['awb_number']) && empty($data['awb']) && empty($data['waybill']) && empty($data['tracking_number'])) {
    http_response_code(200);
    echo json_encode([
        'status'  => 'success',
        'message' => 'BharatShip Webhook Verified & Active (200 OK)',
        'received_at' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// 2. Extract AWB and status identifiers
$awb = trim((string)($data['waybill'] ?? $data['awb_number'] ?? $data['awb'] ?? $data['tracking_number'] ?? ''));
$status = strtoupper(trim((string)($data['current_status'] ?? $data['status'] ?? '')));
$courierPartner = trim((string)($data['courier_name'] ?? $data['courier_partner'] ?? ''));
$labelUrl = trim((string)($data['label_url'] ?? $data['label'] ?? ''));
$manifestUrl = trim((string)($data['manifest_url'] ?? ''));
$statusDescription = trim((string)($data['status_description'] ?? $data['activity'] ?? $status));

try {
    // 3. Find matching shipment in database
    $stmt = $pdo->prepare("SELECT * FROM courier_shipments WHERE awb_number = ? LIMIT 1");
    $stmt->execute([$awb]);
    $shipment = $stmt->fetch();

    $orderId = $shipment ? (int)$shipment['order_id'] : null;

    // 4. Log the webhook in courier_api_logs
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO courier_api_logs 
              (order_id, integration_id, provider_code, endpoint_url, http_method, http_status_code, request_payload, response_payload, duration_ms, ip_address)
            VALUES 
              (?, 1, 'bharatship', 'webhook/bharatship_webhook.php', 'POST', 200, ?, ?, 0, ?)
        ");
        $logStmt->execute([
            $orderId,
            $rawPayload,
            json_encode(['status' => 'processed', 'awb' => $awb, 'mapped_status' => $status]),
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
    } catch (Throwable $e) {}

    if (!$shipment) {
        // Shipment not created via this store, but acknowledge receipt
        echo json_encode(['status' => 'ignored', 'message' => 'Shipment AWB not matched with store records.']);
        exit;
    }

    // 5. Update courier_shipments table
    $updShip = $pdo->prepare("
        UPDATE courier_shipments 
        SET courier_status = ?,
            status_description = ?,
            last_tracking_sync_at = NOW(),
            label_url = COALESCE(NULLIF(?, ''), label_url),
            manifest_url = COALESCE(NULLIF(?, ''), manifest_url)
        WHERE awb_number = ?
    ");
    $updShip->execute([$status, $statusDescription, $labelUrl, $manifestUrl, $awb]);

    // 6. Map logistics status to eCommerce orders.status
    if ($orderId > 0) {
        $normalized = strtolower($status);

        if (in_array($normalized, ['picked_up', 'in_transit', 'out_for_delivery', 'shipped'])) {
            $pdo->prepare("UPDATE orders SET status = 'shipped' WHERE id = ? AND status IN ('pending', 'processing')")->execute([$orderId]);
        } elseif (in_array($normalized, ['delivered', 'completed'])) {
            $pdo->prepare("UPDATE orders SET status = 'delivered', payment_status = 'SUCCESS' WHERE id = ?")->execute([$orderId]);
        } elseif (in_array($normalized, ['rto_delivered', 'cancelled'])) {
            $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
        }

        // Log to order_status_history
        try {
            $histStmt = $pdo->prepare("
                INSERT INTO order_status_history (order_id, status, notes, changed_by)
                VALUES (?, ?, ?, 'bharatship_webhook')
            ");
            $histStmt->execute([$orderId, $status, "Logistics updated to {$status}: {$statusDescription}"]);
        } catch (Throwable $e) {}
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Webhook processed successfully.', 'awb' => $awb]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error: ' . $e->getMessage()]);
    exit;
}
