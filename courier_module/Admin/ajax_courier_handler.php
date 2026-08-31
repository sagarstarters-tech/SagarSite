<?php
declare(strict_types=1);

/**
 * Courier Module — Admin AJAX Endpoint Handler
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/session_setup.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../config/DbConnection.php';
require_once __DIR__ . '/../Services/CourierCryptoService.php';
require_once __DIR__ . '/../Services/CourierManager.php';
require_once __DIR__ . '/../Services/CourierQueueService.php';

// Auth Guard: Admin Only
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$pdo = DbConnection::getInstance();
$manager = new \CourierModule\Services\CourierManager($pdo);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $postedToken = trim($_POST['api_token'] ?? '');

    switch ($action) {
        case 'generate_auth_token':
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            if (empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
                exit;
            }

            $url = 'https://app.bharatship.com/api/authToken';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['email' => $email, 'password' => $password]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_TIMEOUT => 15
            ]);
            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $res = json_decode($raw, true);
            if ($code === 200 && !empty($res['token'])) {
                $token = $res['token'];
                $enc = \CourierModule\Services\CourierCryptoService::encrypt($token);
                $pdo->prepare("UPDATE courier_integrations SET api_token = ?, is_enabled = 1, is_default = 1, auto_sync_orders = 1 WHERE provider_code = 'bharatship'")->execute([$enc]);

                // Sync warehouses automatically
                $courier = $manager->getCourier('bharatship');
                if ($courier) {
                    $courier->setApiToken($token);
                    $whRes = $courier->getWarehouses();
                    if ($whRes['success'] && !empty($whRes['warehouses'])) {
                        foreach ($whRes['warehouses'] as $wh) {
                            $whId = intval($wh['warehouse_id'] ?? $wh['id'] ?? 6763);
                            $whName = trim((string)($wh['warehouse_name'] ?? $wh['name'] ?? 'Primary Hub'));
                            $stmt = $pdo->prepare("
                                INSERT INTO courier_warehouses (
                                    integration_id, warehouse_id, warehouse_name, warehouse_code,
                                    contact_name, contact_phone, address_line1, city, state, pincode, is_default, is_active
                                ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
                                ON DUPLICATE KEY UPDATE
                                    warehouse_name = VALUES(warehouse_name),
                                    address_line1 = VALUES(address_line1)
                            ");
                            $stmt->execute([
                                $whId,
                                $whName,
                                (string)$whId,
                                (string)($wh['name'] ?? 'Pramod Kumar Sagar'),
                                (string)($wh['number'] ?? '8573934013'),
                                (string)($wh['address'] ?? 'Alipur Madra Jakhania'),
                                (string)($wh['city_name'] ?? 'Ghazipur'),
                                (string)($wh['state'] ?? 'UTTAR PRADESH'),
                                (string)($wh['pincode'] ?? '275203')
                            ]);
                        }
                        $pdo->prepare("UPDATE courier_integrations SET pickup_address_id = 6763 WHERE provider_code = 'bharatship'")->execute();
                    }
                }

                echo json_encode(['success' => true, 'token' => $token, 'message' => 'BharatShip Bearer Token generated and Warehouses synced successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => $res['message'] ?? 'Failed to authenticate with BharatShip.']);
            }
            exit;

        case 'test_connection':
            $providerCode = trim($_POST['provider_code'] ?? 'bharatship');
            $courier = $manager->getCourier($providerCode);
            if (!$courier) {
                echo json_encode(['success' => false, 'message' => "Provider '{$providerCode}' is not configured."]);
                exit;
            }
            if (!empty($postedToken) && strpos($postedToken, '••••') === false) {
                $courier->setApiToken($postedToken);
                // Also auto-save valid token
                $enc = \CourierModule\Services\CourierCryptoService::encrypt($postedToken);
                $pdo->prepare("UPDATE courier_integrations SET api_token = ? WHERE provider_code = ?")->execute([$enc, $providerCode]);
            }
            $testRes = $courier->testConnection();
            echo json_encode($testRes);
            exit;

        case 'fetch_warehouses':
            $providerCode = trim($_POST['provider_code'] ?? 'bharatship');
            $courier = $manager->getCourier($providerCode);
            if (!$courier) {
                echo json_encode(['success' => false, 'message' => 'Courier provider not found.']);
                exit;
            }
            if (!empty($postedToken) && strpos($postedToken, '••••') === false) {
                $courier->setApiToken($postedToken);
            }

            $res = $courier->getWarehouses();
            if ($res['success'] && !empty($res['warehouses'])) {
                // Save/Sync warehouses into courier_warehouses table
                foreach ($res['warehouses'] as $wh) {
                    $whId = intval($wh['warehouse_id'] ?? $wh['id'] ?? 0);
                    $whName = trim((string)($wh['warehouse_name'] ?? $wh['name'] ?? 'Primary'));
                    $whPhone = (string)($wh['number'] ?? $wh['phone'] ?? '');
                    $whCity = (string)($wh['city_name'] ?? $wh['city'] ?? '');
                    $whState = (string)($wh['state'] ?? '');
                    $whPincode = (string)($wh['pincode'] ?? '');
                    $whAddr = (string)($wh['address'] ?? '');

                    if ($whId > 0 || !empty($whName)) {
                        $whCode = $whId > 0 ? (string)$whId : $whName;
                        $stmt = $pdo->prepare("
                            INSERT INTO courier_warehouses (
                                integration_id, warehouse_id, warehouse_name, warehouse_code,
                                contact_name, contact_phone, address_line1, city, state, pincode
                            ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                warehouse_name = VALUES(warehouse_name),
                                contact_phone = VALUES(contact_phone),
                                address_line1 = VALUES(address_line1),
                                city = VALUES(city),
                                state = VALUES(state),
                                pincode = VALUES(pincode)
                        ");
                        $stmt->execute([
                            $whId,
                            $whName,
                            $whCode,
                            (string)($wh['name'] ?? 'Manager'),
                            $whPhone,
                            $whAddr,
                            $whCity,
                            $whState,
                            $whPincode
                        ]);
                    }
                }
            }
            echo json_encode($res);
            exit;

        case 'add_warehouse':
            $providerCode = trim($_POST['provider_code'] ?? 'bharatship');
            $courier = $manager->getCourier($providerCode);
            if (!$courier) {
                echo json_encode(['success' => false, 'message' => 'Courier provider not found.']);
                exit;
            }
            if (!empty($postedToken) && strpos($postedToken, '••••') === false) {
                $courier->setApiToken($postedToken);
            }

            $warehouseData = [
                'warehouse_name' => trim($_POST['warehouse_name'] ?? 'Primary Hub'),
                'warehouse_type' => trim($_POST['warehouse_type'] ?? 'office'),
                'contact_name'   => trim($_POST['contact_name'] ?? 'Store Manager'),
                'contact_phone'  => trim($_POST['contact_phone'] ?? '918573934013'),
                'contact_email'  => trim($_POST['contact_email'] ?? ''),
                'address_line1'  => trim($_POST['address_line1'] ?? ''),
                'address_line2'  => trim($_POST['address_line2'] ?? ''),
                'city'           => trim($_POST['city'] ?? ''),
                'state'          => trim($_POST['state'] ?? ''),
                'pincode'        => trim($_POST['pincode'] ?? '110001')
            ];

            $res = $courier->createWarehouse($warehouseData);
            if ($res['success']) {
                $whId = (int)($res['warehouse_id'] ?? 0);
                if ($whId > 0) {
                    $pdo->prepare("UPDATE courier_integrations SET pickup_address_id = ? WHERE provider_code = ?")->execute([$whId, $providerCode]);
                }
            }
            echo json_encode($res);
            exit;

        case 'fetch_couriers':
            $providerCode = trim($_POST['provider_code'] ?? 'bharatship');
            $courier = $manager->getCourier($providerCode);
            if (!$courier || !method_exists($courier, 'getCouriers')) {
                echo json_encode(['success' => false, 'message' => 'Courier provider not available.']);
                exit;
            }
            $res = $courier->getCouriers();
            echo json_encode($res);
            exit;

        case 'save_integration':
            $id = intval($_POST['id'] ?? 1);
            $apiBaseUrl = trim($_POST['api_base_url'] ?? 'https://app.bharatship.com/');
            $apiTokenRaw = trim($_POST['api_token'] ?? '');
            $pickupAddressId = intval($_POST['pickup_address_id'] ?? 0);
            $courierShipType = intval($_POST['default_courier_ship_type'] ?? 2);
            $express = trim($_POST['default_express'] ?? 'surface');
            $isEnabled = isset($_POST['is_enabled']) && $_POST['is_enabled'] == '1' ? 1 : 0;
            $isDefault = isset($_POST['is_default']) && $_POST['is_default'] == '1' ? 1 : 0;
            $autoSync = isset($_POST['auto_sync_orders']) && $_POST['auto_sync_orders'] == '1' ? 1 : 0;

            if ($isDefault) {
                $pdo->query("UPDATE courier_integrations SET is_default = 0");
            }

            if (!empty($apiTokenRaw) && strpos($apiTokenRaw, '••••') === false) {
                $encryptedToken = \CourierModule\Services\CourierCryptoService::encrypt($apiTokenRaw);
                $stmt = $pdo->prepare("
                    UPDATE courier_integrations 
                    SET api_base_url = ?, api_token = ?, pickup_address_id = ?, 
                        default_courier_ship_type = ?, default_express = ?,
                        is_enabled = ?, is_default = ?, auto_sync_orders = ?
                    WHERE id = ?
                ");
                $stmt->execute([$apiBaseUrl, $encryptedToken, $pickupAddressId, $courierShipType, $express, $isEnabled, $isDefault, $autoSync, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE courier_integrations 
                    SET api_base_url = ?, pickup_address_id = ?, 
                        default_courier_ship_type = ?, default_express = ?,
                        is_enabled = ?, is_default = ?, auto_sync_orders = ?
                    WHERE id = ?
                ");
                $stmt->execute([$apiBaseUrl, $pickupAddressId, $courierShipType, $express, $isEnabled, $isDefault, $autoSync, $id]);
            }

            echo json_encode(['success' => true, 'message' => 'BharatShip configuration updated successfully.']);
            exit;

        case 'push_single_order':
            $orderId = intval($_POST['order_id'] ?? 0);
            if (!$orderId) {
                echo json_encode(['success' => false, 'message' => 'Valid Order ID is required.']);
                exit;
            }

            $options = [];
            if (!empty($_POST['courier_ship_type'])) {
                $options['courier_ship_type'] = intval($_POST['courier_ship_type']);
            }
            if (!empty($_POST['courier_code'])) {
                $options['courier_code'] = trim($_POST['courier_code']);
            }
            if (!empty($_POST['express'])) {
                $options['express'] = trim($_POST['express']);
            }

            $res = $manager->pushOrderToCourier($orderId, 'bharatship', $options);
            echo json_encode($res);
            exit;

        case 'retry_queue':
            $queueId = intval($_POST['queue_id'] ?? 0);
            if (!$queueId) {
                echo json_encode(['success' => false, 'message' => 'Queue ID is required.']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE courier_queue SET status = 'pending', attempts = 0, next_attempt_at = NOW(), locked_at = NULL WHERE id = ?");
            $stmt->execute([$queueId]);
            echo json_encode(['success' => true, 'message' => 'Order reset to pending. Background cron will process it immediately.']);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => "Unknown action: '{$action}'."]);
            exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
    exit;
}
