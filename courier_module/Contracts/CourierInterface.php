<?php
declare(strict_types=1);

namespace CourierModule\Contracts;

/**
 * Interface CourierInterface
 * Standard contract that every courier aggregator / direct provider must implement.
 */
interface CourierInterface
{
    /**
     * Get unique provider identifier (e.g. 'bharatship', 'shiprocket', 'delhivery')
     */
    public function getProviderCode(): string;

    /**
     * Test API connection with configured credentials.
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function testConnection(): array;

    /**
     * Check delivery serviceability and fetch real-time rate card.
     * @param string $pickupPincode
     * @param string $deliveryPincode
     * @param float  $weightKg
     * @param string $paymentType ('COD' or 'PREPAID')
     * @param float  $codAmount
     * @return array ['success' => bool, 'available_couriers' => array, 'message' => string]
     */
    public function checkServiceability(
        string $pickupPincode,
        string $deliveryPincode,
        float $weightKg,
        string $paymentType,
        float $codAmount = 0.0
    ): array;

    /**
     * Create or sync a pickup warehouse / location on the courier platform.
     * @param array $warehouseData
     * @return array ['success' => bool, 'warehouse_code' => string, 'message' => string, 'raw' => array]
     */
    public function createWarehouse(array $warehouseData): array;

    /**
     * Fetch all registered warehouses from courier platform.
     * @return array ['success' => bool, 'warehouses' => array, 'message' => string]
     */
    public function getWarehouses(): array;

    /**
     * Push order to courier platform & create shipment.
     * @param array $orderData Complete order, items, address, and weight payload
     * @return array [
     *     'success'           => bool,
     *     'courier_order_id'  => ?string,
     *     'shipment_id'       => ?string,
     *     'awb_number'        => ?string,
     *     'courier_partner'   => ?string,
     *     'label_url'         => ?string,
     *     'manifest_url'      => ?string,
     *     'message'           => string,
     *     'raw_response'      => array
     * ]
     */
    public function createShipment(array $orderData): array;

    /**
     * Generate / Assign AWB for an already created shipment (if separate in courier API).
     * @param string $shipmentId
     * @param array  $options
     * @return array ['success' => bool, 'awb_number' => string, 'courier_partner' => string, 'label_url' => ?string]
     */
    public function generateAwb(string $shipmentId, array $options = []): array;

    /**
     * Fetch direct PDF download URL or binary for shipping label.
     * @param string $shipmentIdOrAwb
     * @return array ['success' => bool, 'label_url' => string, 'message' => string]
     */
    public function getShippingLabel(string $shipmentIdOrAwb): array;

    /**
     * Fetch live real-time milestone tracking for an AWB.
     * @param string $awbNumber
     * @return array [
     *     'success'          => bool,
     *     'current_status'   => string,
     *     'status_details'   => string,
     *     'milestones'       => array,
     *     'est_delivery'     => ?string,
     *     'raw_response'     => array
     * ]
     */
    public function trackShipment(string $awbNumber): array;

    /**
     * Cancel an active shipment on the courier platform.
     * @param string $awbNumber
     * @param string $shipmentId
     * @return array ['success' => bool, 'message' => string]
     */
    public function cancelShipment(string $awbNumber, string $shipmentId = ''): array;
}
