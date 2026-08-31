<?php
declare(strict_types=1);

namespace CourierModule\Services;

use CourierModule\Contracts\CourierInterface;
use CourierModule\Adapters\BharatShipAdapter;
use DbConnection;
use PDO;
use Throwable;

/**
 * Class CourierManager
 * Central Factory and orchestrator for all courier operations.
 * Decouples frontend and admin controllers from specific courier providers.
 */
class CourierManager
{
    private PDO $pdo;
    /** @var array<string, CourierInterface> */
    private array $adapters = [];

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo === null) {
            require_once dirname(__DIR__, 2) . '/config/DbConnection.php';
            $this->pdo = DbConnection::getInstance();
        } else {
            $this->pdo = $pdo;
        }

        require_once __DIR__ . '/../Database/CourierSchemaBootstrap.php';
        \CourierModule\Database\CourierSchemaBootstrap::ensureTablesExist($this->pdo);
    }

    /**
     * Resolve courier provider instance by code
     */
    public function getCourier(string $providerCode): ?CourierInterface
    {
        $code = strtolower(trim($providerCode));
        if (isset($this->adapters[$code])) {
            return $this->adapters[$code];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM courier_integrations WHERE provider_code = ? LIMIT 1");
        $stmt->execute([$code]);
        $integration = $stmt->fetch();

        if (!$integration) {
            return null;
        }

        $adapter = $this->createAdapter($integration);
        if ($adapter !== null) {
            $this->adapters[$code] = $adapter;
        }
        return $adapter;
    }

    /**
     * Get the configured active default courier
     */
    public function getDefaultCourier(): ?CourierInterface
    {
        // 1. Check for enabled & default courier
        $stmt = $this->pdo->query("SELECT * FROM courier_integrations WHERE is_enabled = 1 AND is_default = 1 LIMIT 1");
        $integration = $stmt->fetch();

        // 2. If no default is set, take the first enabled courier
        if (!$integration) {
            $stmt = $this->pdo->query("SELECT * FROM courier_integrations WHERE is_enabled = 1 ORDER BY id ASC LIMIT 1");
            $integration = $stmt->fetch();
        }

        // 3. If none enabled, fallback to first configured provider for testing
        if (!$integration) {
            $stmt = $this->pdo->query("SELECT * FROM courier_integrations ORDER BY id ASC LIMIT 1");
            $integration = $stmt->fetch();
        }

        if (!$integration) {
            return null;
        }

        return $this->getCourier($integration['provider_code']);
    }

    /**
     * Instantiate specific adapter by provider code
     */
    private function createAdapter(array $integration): ?CourierInterface
    {
        $code = strtolower((string)$integration['provider_code']);

        switch ($code) {
            case 'bharatship':
                require_once __DIR__ . '/../Contracts/CourierInterface.php';
                require_once __DIR__ . '/../Adapters/BaseCourierAdapter.php';
                require_once __DIR__ . '/../Adapters/BharatShipAdapter.php';
                require_once __DIR__ . '/CourierCryptoService.php';
                return new BharatShipAdapter($this->pdo, $integration);

            default:
                return null;
        }
    }

    /**
     * High-level helper: Push website order to active courier and record shipment.
     */
    public function pushOrderToCourier(int $orderId, ?string $providerCode = null, array $customOptions = []): array
    {
        try {
            // 1. Fetch full order details & customer info
            $stmt = $this->pdo->prepare("
                SELECT o.*, 
                       u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
                       u.address as shipping_address, u.city, u.state, u.country, u.zip_code
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                return ['success' => false, 'message' => "Order #{$orderId} not found in database."];
            }

            // 2. Fetch order items with product weights and dimensions
            $itemStmt = $this->pdo->prepare("
                SELECT oi.*, p.name as product_name, p.sku, p.weight, p.length, p.width, p.height
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $itemStmt->execute([$orderId]);
            $order['items'] = $itemStmt->fetchAll();

            // Merge any custom options from admin (courier_ship_type, courier_code, express, etc.)
            $order = array_merge($order, $customOptions);

            // 3. Resolve Courier Provider
            $courier = ($providerCode !== null) ? $this->getCourier($providerCode) : $this->getDefaultCourier();
            if (!$courier) {
                return ['success' => false, 'message' => 'No active courier provider configured.'];
            }

            // 4. Duplicate Check (Idempotency)
            $shipCheck = $this->pdo->prepare("
                SELECT * FROM courier_shipments 
                WHERE order_id = ? AND courier_status NOT IN ('CANCELLED') 
                LIMIT 1
            ");
            $shipCheck->execute([$orderId]);
            $existingShipment = $shipCheck->fetch();

            if ($existingShipment && !empty($existingShipment['awb_number'])) {
                return [
                    'success'         => true,
                    'already_synced'  => true,
                    'waybill'         => $existingShipment['awb_number'],
                    'awb_number'      => $existingShipment['awb_number'],
                    'courier_partner' => $existingShipment['courier_partner_name'],
                    'routing_code'    => $existingShipment['routing_code'],
                    'label_url'       => $existingShipment['label_url'],
                    'message'         => "Order already shipped with Waybill {$existingShipment['awb_number']} ({$existingShipment['courier_partner_name']})."
                ];
            }

            // 5. Execute API Call
            $result = $courier->createShipment($order);

            if ($result['success']) {
                $awb = $result['waybill'] ?? $result['awb_number'] ?? ('BS-PENDING-' . time());
                $courierOrderId = (string)($result['courier_order_id'] ?? '');
                $partner = (string)($result['courier_partner'] ?? 'Assigned Courier');
                $routingCode = (string)($result['routing_code'] ?? '');
                $clientOrderId = (string)($result['client_order_id'] ?? ('SS-ORD-' . $orderId));
                $weight = (float)($result['charged_weight_kg'] ?? 0.5);
                $codCollect = (float)($result['collectible_cod_amount'] ?? 0);
                $rawJson = json_encode($result['raw_response'] ?? [], JSON_UNESCAPED_UNICODE);

                // Fetch shipping label if possible
                $labelUrl = $result['label_url'] ?? null;
                if (empty($labelUrl)) {
                    $lblRes = $courier->getShippingLabel($courierOrderId ?: $awb);
                    if ($lblRes['success'] && !empty($lblRes['label_url'])) {
                        $labelUrl = $lblRes['label_url'];
                    }
                }

                // 6. Save in courier_shipments table
                $saveStmt = $this->pdo->prepare("
                    INSERT INTO courier_shipments (
                        order_id, integration_id, courier_order_id, shipment_id, awb_number,
                        courier_partner_name, routing_code, client_order_id, label_url,
                        shipping_cost_estimated, charged_weight_kg, collectible_cod_amount,
                        courier_status, raw_creation_response
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'AWB_ASSIGNED', ?)
                    ON DUPLICATE KEY UPDATE
                        courier_order_id = VALUES(courier_order_id),
                        shipment_id = VALUES(shipment_id),
                        awb_number = VALUES(awb_number),
                        courier_partner_name = VALUES(courier_partner_name),
                        routing_code = VALUES(routing_code),
                        client_order_id = VALUES(client_order_id),
                        label_url = COALESCE(VALUES(label_url), label_url),
                        courier_status = 'AWB_ASSIGNED',
                        raw_creation_response = VALUES(raw_creation_response)
                ");
                $saveStmt->execute([
                    $orderId,
                    (int)($order['integration_id'] ?? 1),
                    $courierOrderId,
                    $courierOrderId,
                    $awb,
                    $partner,
                    $routingCode,
                    $clientOrderId,
                    $labelUrl,
                    0.00,
                    $weight,
                    $codCollect,
                    $rawJson
                ]);

                // 7. Sync with legacy order_tracking table for seamless backward compatibility
                try {
                    $legStmt = $this->pdo->prepare("
                        INSERT INTO order_tracking (order_id, tracking_number, estimated_delivery_date)
                        VALUES (?, ?, NOW() + INTERVAL 5 DAY)
                        ON DUPLICATE KEY UPDATE tracking_number = VALUES(tracking_number)
                    ");
                    $legStmt->execute([$orderId, $awb]);
                } catch (Throwable $e) {}

                return array_merge($result, [
                    'label_url' => $labelUrl,
                    'message'   => "Order #{$orderId} placed successfully on BharatShip! Assigned: {$partner} | Waybill: {$awb}"
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            error_log('[CourierManager] Error pushing order: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Courier Push Exception: ' . $e->getMessage()];
        }
    }

    /**
     * Fetch all integrations from database
     */
    public function getAllIntegrations(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM courier_integrations ORDER BY is_default DESC, id ASC");
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get shipment record for an order
     */
    public function getShipmentByOrderId(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, i.provider_name, i.provider_code 
            FROM courier_shipments s
            JOIN courier_integrations i ON s.integration_id = i.id
            WHERE s.order_id = ?
            ORDER BY s.id DESC LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
