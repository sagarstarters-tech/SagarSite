<?php
declare(strict_types=1);

namespace CourierModule\Adapters;

use Throwable;

/**
 * Class BharatShipAdapter
 * Official Implementation of CourierInterface for BharatShip API.
 * Official Documentation: https://app.bharatship.com/api/v1/create-order
 */
class BharatShipAdapter extends BaseCourierAdapter
{
    public function getProviderCode(): string
    {
        return 'bharatship';
    }

    /**
     * Test API connection with configured Bearer token
     */
    public function testConnection(): array
    {
        if (empty($this->apiToken)) {
            return [
                'success' => false,
                'message' => 'API Bearer Token is empty. Please enter your BharatShip Bearer token in Settings.'
            ];
        }

        // Test with official warehouseList or courierList endpoints
        $res = $this->getWarehouses();
        if ($res['success']) {
            $count = count($res['warehouses']);
            return [
                'success' => true,
                'message' => "BharatShip connection verified! ({$count} active warehouses found).",
                'data'    => $res['warehouses']
            ];
        }

        $courierRes = $this->getCouriers();
        if ($courierRes['success']) {
            $count = count($courierRes['couriers']);
            return [
                'success' => true,
                'message' => "BharatShip connection verified! ({$count} courier partners available).",
                'data'    => $courierRes['couriers']
            ];
        }

        return [
            'success' => false,
            'message' => 'BharatShip connection failed: ' . ($res['message'] ?? 'Invalid Bearer Token or endpoint unreachable.'),
            'data'    => $res
        ];
    }

    /**
     * Fetch list of registered warehouses from BharatShip
     * Response: {"status": true, "data": [{"warehouse_name": "...", "warehouse_type": "...", "number": "...", "pincode": ..., "city_name": "..."}]}
     */
    public function getWarehouses(): array
    {
        $res = $this->request('api/warehouseList', 'GET');
        if (!$res['success']) {
            $res = $this->request('api/v1/warehouse-list', 'GET');
        }
        if (!$res['success']) {
            $res = $this->request('api/warehouses', 'GET');
        }

        if ($res['success'] && !empty($res['data'])) {
            $status = $res['data']['status'] ?? false;
            $list = $res['data']['data'] ?? $res['data']['warehouses'] ?? [];
            if ($status || !empty($list)) {
                return [
                    'success'    => true,
                    'warehouses' => is_array($list) ? $list : [],
                    'message'    => 'Warehouses fetched successfully.'
                ];
            }
        }

        return [
            'success'    => false,
            'warehouses' => [],
            'message'    => $res['message'] ?? ($res['data']['message'] ?? 'Failed to fetch warehouse list from BharatShip')
        ];
    }

    /**
     * Fetch list of available courier partners from BharatShip
     * Response: {"status": true, "data": [{"courier_name": "BlueDart", "courier_code": "blueDart"}, ...]}
     */
    public function getCouriers(): array
    {
        $res = $this->request('api/courierList', 'GET');
        if (!$res['success']) {
            $res = $this->request('api/v1/courier-list', 'GET');
        }
        if (!$res['success']) {
            $res = $this->request('api/couriers', 'GET');
        }

        if ($res['success'] && !empty($res['data'])) {
            $status = $res['data']['status'] ?? false;
            $list = $res['data']['data'] ?? [];
            if ($status || !empty($list)) {
                return [
                    'success'  => true,
                    'couriers' => is_array($list) ? $list : [],
                    'message'  => 'Courier list fetched successfully.'
                ];
            }
        }

        return [
            'success'  => false,
            'couriers' => [],
            'message'  => $res['message'] ?? 'Failed to fetch courier list from BharatShip'
        ];
    }

    /**
     * Calculate live shipping rates
     */
    public function checkServiceability(
        string $pickupPincode,
        string $deliveryPincode,
        float $weightKg,
        string $paymentType,
        float $codAmount = 0.0
    ): array {
        $payload = [
            'pickup_pincode'      => substr(preg_replace('/[^0-9]/', '', $pickupPincode), 0, 6),
            'destination_pincode' => substr(preg_replace('/[^0-9]/', '', $deliveryPincode), 0, 6),
            'weight'              => max(0.50, round($weightKg, 2)),
            'payment_mode'        => strtoupper($paymentType) === 'COD' ? 'COD' : 'PPD',
            'cod_amount'          => $codAmount,
            'invoice_amount'      => max(100, $codAmount)
        ];

        $res = $this->request('api/rateCalculator', 'POST', $payload);
        if (!$res['success']) {
            $res = $this->request('api/v1/rate-calculator', 'POST', $payload);
        }

        if ($res['success'] && !empty($res['data'])) {
            $list = $res['data']['data'] ?? [];
            return [
                'success'            => true,
                'available_couriers' => is_array($list) ? $list : [],
                'message'            => 'Rate calculation successful.'
            ];
        }

        return [
            'success'            => false,
            'available_couriers' => [],
            'message'            => $res['message'] ?? 'Pincode not serviceable by BharatShip'
        ];
    }

    /**
     * Create / Register pickup warehouse on BharatShip
     * Response: {"status": true, "warehouse_id": 256, "message": "Warehouse Added Succefully"}
     */
    public function createWarehouse(array $warehouseData): array
    {
        $payload = [
            'warehouse_name' => $warehouseData['warehouse_name'] ?? 'Primary Hub',
            'warehouse_type' => $warehouseData['warehouse_type'] ?? 'Office',
            'name'           => $warehouseData['contact_name'] ?? 'Logistics Manager',
            'number'         => substr(preg_replace('/[^0-9]/', '', (string)($warehouseData['contact_phone'] ?? '9876543210')), -10),
            'email'          => $warehouseData['contact_email'] ?? null,
            'address'        => $warehouseData['address_line1'] ?? '',
            'address_2'      => $warehouseData['address_line2'] ?? null,
            'city_name'      => $warehouseData['city'] ?? '',
            'state'          => strtoupper((string)($warehouseData['state'] ?? '')),
            'pincode'        => intval(substr(preg_replace('/[^0-9]/', '', (string)($warehouseData['pincode'] ?? '110001')), 0, 6))
        ];

        $res = $this->request('api/createWarehouse', 'POST', $payload);
        if (!$res['success']) {
            $res = $this->request('api/v1/create-warehouse', 'POST', $payload);
        }

        if ($res['success'] && !empty($res['data'])) {
            $whId = (int)($res['data']['warehouse_id'] ?? $res['data']['id'] ?? 0);
            return [
                'success'        => true,
                'warehouse_id'   => $whId,
                'warehouse_code' => (string)$whId,
                'message'        => $res['data']['message'] ?? 'Warehouse created successfully on BharatShip.',
                'raw'            => $res['data']
            ];
        }

        return [
            'success'        => false,
            'warehouse_id'   => 0,
            'warehouse_code' => '',
            'message'        => $res['message'] ?? ($res['data']['message'] ?? 'Failed to create warehouse on BharatShip'),
            'raw'            => $res['data'] ?? []
        ];
    }

    /**
     * Create Order & Shipment on BharatShip using Official Endpoint & Schema
     * Endpoint: POST https://app.bharatship.com/api/v1/create-order
     *
     * Official Response format:
     * {"status": true, "order_id": 11557, "waybill": "153854853300026", "courierName": "XpressBees", "routing_code": "FAR/MMM", "client_order_id": 0, "message": "Order Placed successfully"}
     */
    public function createShipment(array $orderData): array
    {
        $orderId = (int)($orderData['id'] ?? 0);
        $clientOrderId = 'SS-ORD-' . $orderId;

        // 1. Validate & Sanitize Customer Phone Number (Strictly 10 digits)
        $rawPhone = (string)($orderData['customer_phone'] ?? $orderData['phone'] ?? '');
        $cleanPhone = substr(preg_replace('/[^0-9]/', '', $rawPhone), -10);
        if (strlen($cleanPhone) !== 10 || !preg_match('/^[6-9][0-9]{9}$/', $cleanPhone)) {
            return [
                'success' => false,
                'message' => "Validation Error: Phone number must be exactly 10 digits starting with 6-9 (Got '{$rawPhone}')."
            ];
        }

        // 2. Validate & Sanitize Pincode (Strictly 6 digits)
        $rawPincode = (string)($orderData['zip_code'] ?? $orderData['pincode'] ?? '');
        $cleanPincode = substr(preg_replace('/[^0-9]/', '', $rawPincode), 0, 6);
        if (strlen($cleanPincode) !== 6 || !preg_match('/^[1-9][0-9]{5}$/', $cleanPincode)) {
            return [
                'success' => false,
                'message' => "Validation Error: Pincode must be exactly 6 numeric digits (Got '{$rawPincode}')."
            ];
        }

        // 3. Payment Mode & COD Amount
        $isCod = (strtoupper((string)($orderData['payment_method'] ?? '')) === 'COD');
        $isPartialCod = ($orderData['payment_mode'] ?? '') === 'COD_PARTIAL';

        $paymentMode = $isCod ? 'COD' : 'PPD';
        $totalAmount = round((float)($orderData['total_amount'] ?? 0), 2);
        $orderAmount = $totalAmount;

        $codAmount = 0.0;
        if ($isCod) {
            if ($isPartialCod) {
                // Partial COD: Remaining balance to be collected on delivery
                $codAmount = round((float)($orderData['remaining_amount'] ?? 0), 2);
            } else {
                $codAmount = $totalAmount;
            }
        }

        // 4. Pickup Address ID (Warehouse)
        $pickupAddressId = intval($orderData['pickup_address_id'] ?? $this->integration['pickup_address_id'] ?? $this->integration['default_warehouse_code'] ?? 1);

        // 5. Courier Assignment Mode (1 = Specific Courier, 2 = BharatShip Auto)
        $courierShipType = intval($orderData['courier_ship_type'] ?? $this->integration['default_courier_ship_type'] ?? 2);
        if (!in_array($courierShipType, [1, 2])) {
            $courierShipType = 2;
        }

        $courierCode = !empty($orderData['courier_code']) ? trim($orderData['courier_code']) : null;
        if ($courierShipType === 1 && empty($courierCode)) {
            // If specific mode selected but no code given, fallback to auto
            $courierShipType = 2;
        }

        $express = strtolower($orderData['express'] ?? $this->integration['default_express'] ?? 'surface');
        if (!in_array($express, ['surface', 'air'])) {
            $express = 'surface';
        }

        // 6. Build Parallel Arrays for Products (Strictly matching indexes)
        $lengthArr = [];
        $widthArr = [];
        $heightArr = [];
        $weightArr = [];
        $noBoxArr = [];
        $productNameArr = [];
        $productQtyArr = [];
        $productPriceArr = [];
        $productTaxArr = [];
        $productUnitTypeArr = [];
        $productSkuArr = [];
        $productHsnArr = [];

        $items = $orderData['items'] ?? [];
        if (empty($items)) {
            // Fallback single item if items array is empty
            $items = [[
                'name'     => 'Agricultural Equipment / Starter Panel',
                'sku'      => 'SS-' . $orderId,
                'quantity' => 1,
                'price'    => $totalAmount,
                'weight'   => 1.0,
                'length'   => 15.0,
                'width'    => 12.0,
                'height'   => 10.0
            ]];
        }

        foreach ($items as $item) {
            $qty = max(1, intval($item['quantity'] ?? $item['qty'] ?? 1));
            $price = round((float)($item['price'] ?? 0), 2);
            $l = max(5.0, round((float)($item['length'] ?? 15.0), 1));
            $w = max(5.0, round((float)($item['width'] ?? 12.0), 1));
            $h = max(5.0, round((float)($item['height'] ?? 10.0), 1));
            $wt = max(0.50, round((float)($item['weight'] ?? 1.0), 2));

            $lengthArr[] = $l;
            $widthArr[] = $w;
            $heightArr[] = $h;
            $weightArr[] = $wt;
            $noBoxArr[] = 1; // 1 box per item unit
            $productNameArr[] = mb_substr((string)($item['product_name'] ?? $item['name'] ?? 'Product'), 0, 100);
            $productQtyArr[] = $qty;
            $productPriceArr[] = $price;
            $productTaxArr[] = round((float)($item['tax_percent'] ?? 18.0), 2);
            $productUnitTypeArr[] = 'per_unit';
            $productSkuArr[] = (string)($item['sku'] ?? ('SKU-' . ($item['product_id'] ?? $item['id'] ?? '1')));
            $productHsnArr[] = (string)($item['hsn_code'] ?? '');
        }

        $totalBoxes = array_sum($noBoxArr);
        $mps = ($totalBoxes > 1) ? 'yes' : 'no';

        // 7. Assemble Complete Official Payload (Exact field names)
        $payload = [
            'client_order_id'   => $clientOrderId,
            'pickup_address_id' => $pickupAddressId,
            'payment_mode'      => $paymentMode,
            'phone_number'      => $cleanPhone,
            'full_name'         => mb_substr((string)($orderData['customer_name'] ?? $orderData['name'] ?? 'Customer'), 0, 80),
            'full_address'      => mb_substr((string)($orderData['shipping_address'] ?? $orderData['address'] ?? ($orderData['city'] . ', ' . $orderData['state'])), 0, 250),
            'pincode'           => $cleanPincode,
            'total_amount'      => $totalAmount,
            'order_amount'      => $orderAmount,
            'courier_ship_type' => $courierShipType,
            'express'           => $express,
            'appointment'       => 'no',
            'mps'               => $mps,
            'insurance'         => intval($orderData['insurance'] ?? 0),

            // Product & Dimensions Arrays
            'length'            => $lengthArr,
            'width'             => $widthArr,
            'height'            => $heightArr,
            'weight'            => $weightArr,
            'no_box'            => $noBoxArr,
            'product_name'      => $productNameArr,
            'product_quantity'  => $productQtyArr,
            'product_price'     => $productPriceArr,
            'product_tax_per'   => $productTaxArr,
            'product_unit_type' => $productUnitTypeArr,
            'product_sku'       => $productSkuArr,
            'product_hsn'       => $productHsnArr,
        ];

        // Conditional fields
        if ($paymentMode === 'COD') {
            $payload['cod_amount'] = $codAmount;
        }

        if ($courierShipType === 1 && !empty($courierCode)) {
            $payload['courier_code'] = $courierCode;
            if (strtolower($courierCode) === 'delhiveryb2b') {
                $payload['callback_url'] = 'https://www.sagarstarters.com/courier_module/Webhooks/bharatship_webhook.php';
            }
        }

        if (!empty($orderData['invoice_number'])) {
            $payload['invoice_number'] = (string)$orderData['invoice_number'];
        }

        // GST & E-Way Bill (For orders >= 50,000)
        if ($orderAmount >= 50000) {
            if (!empty($orderData['ewaybill_no'])) {
                $payload['ewaybill_no'] = (string)$orderData['ewaybill_no'];
            }
            if (!empty($orderData['consigner_gst_number'])) {
                $payload['consigner_gst_number'] = (string)$orderData['consigner_gst_number'];
            }
            if (!empty($orderData['consignee_gst_number'])) {
                $payload['consignee_gst_number'] = (string)$orderData['consignee_gst_number'];
            }
        }

        // 8. Execute Request to Official Endpoint
        $res = $this->request('api/v1/create-order', 'POST', $payload, [], $orderId);

        // Fallback to root path if v1 subpath returns 404
        if (!$res['success'] && ($res['http_code'] === 404 || strpos((string)($res['data']['message'] ?? ''), 'Route not found') !== false)) {
            $res = $this->request('api/createOrder', 'POST', $payload, [], $orderId);
        }

        // 9. Parse Official Response
        if ($res['success'] && !empty($res['data'])) {
            $d = $res['data'];
            $isStatusTrue = ($d['status'] === true || $d['status'] === 1 || $d['status'] === 'true');

            if ($isStatusTrue) {
                $waybill = (string)($d['waybill'] ?? $d['awb'] ?? $d['tracking_number'] ?? '');
                $courierOrderId = (string)($d['order_id'] ?? '');
                $courierName = (string)($d['courierName'] ?? $d['courier_name'] ?? 'Assigned Partner');
                $routingCode = (string)($d['routing_code'] ?? '');
                $returnedClientOrderId = (string)($d['client_order_id'] ?? $clientOrderId);
                $message = (string)($d['message'] ?? 'Order Placed successfully');

                return [
                    'success'                 => true,
                    'waybill'                 => $waybill,
                    'awb_number'              => $waybill, // Standard alias
                    'courier_order_id'        => $courierOrderId,
                    'courier_partner'         => $courierName,
                    'routing_code'            => $routingCode,
                    'client_order_id'         => $returnedClientOrderId,
                    'label_url'               => null, // Will be fetched via orderLabel API
                    'manifest_url'            => null,
                    'shipping_cost_estimated' => 0.0,
                    'charged_weight_kg'       => array_sum($weightArr),
                    'collectible_cod_amount'  => $codAmount,
                    'message'                 => $message,
                    'raw_response'            => $res['data']
                ];
            }

            return [
                'success'      => false,
                'message'      => $d['message'] ?? 'BharatShip returned status false',
                'raw_response' => $res['data']
            ];
        }

        return [
            'success'      => false,
            'message'      => $res['message'] ?? ($res['data']['message'] ?? 'Failed to connect to BharatShip Create Order API'),
            'raw_response' => $res['data'] ?? []
        ];
    }

    /**
     * Generate AWB / Assign Courier (if separate)
     */
    public function generateAwb(string $shipmentId, array $options = []): array
    {
        return [
            'success'         => true,
            'awb_number'      => $shipmentId,
            'courier_partner' => 'BharatShip Partner',
            'label_url'       => null,
            'message'         => 'AWB is auto-generated during Create Order.'
        ];
    }

    /**
     * Fetch Shipping Label PDF from Official Endpoint
     * Response: {"status": true, "label_url": "https://app.bharatship.com/shipment_lables/<FILE>.pdf"}
     */
    public function getShippingLabel(string $shipmentIdOrAwb): array
    {
        $payload = ['order_id' => $shipmentIdOrAwb, 'waybill' => $shipmentIdOrAwb];
        $res = $this->request('api/orderLabel', 'POST', $payload);
        if (!$res['success']) {
            $res = $this->request('api/v1/order-label', 'POST', $payload);
        }

        if ($res['success'] && !empty($res['data'])) {
            $labelUrl = (string)($res['data']['label_url'] ?? '');
            if (!empty($labelUrl)) {
                return [
                    'success'   => true,
                    'label_url' => $labelUrl,
                    'message'   => 'Label fetched successfully.'
                ];
            }
        }

        return [
            'success'   => false,
            'label_url' => '',
            'message'   => $res['message'] ?? ($res['data']['message'] ?? 'Shipping label not ready yet on BharatShip.')
        ];
    }

    /**
     * Fetch Live Real-Time Tracking History from Official Endpoint
     * Response: {"status": true, "data": {"summary": {...}, "history": [...]}}
     */
    public function trackShipment(string $awbNumber): array
    {
        $payload = ['awb' => $awbNumber, 'waybill' => $awbNumber];
        $res = $this->request('api/tracking', 'POST', $payload);
        if (!$res['success']) {
            $res = $this->request('api/v1/tracking/' . urlencode($awbNumber), 'GET');
        }

        if ($res['success'] && !empty($res['data']['data'])) {
            $summary = $res['data']['data']['summary'] ?? [];
            $history = $res['data']['data']['history'] ?? [];

            $statusCode = $summary['shipment_status'] ?? 1;
            $statusTitle = 'Booked';

            if (!empty($history) && is_array($history)) {
                $latest = reset($history);
                $statusTitle = $latest['status_title'] ?? ('Status ' . $statusCode);
            }

            return [
                'success'        => true,
                'current_status' => strtoupper(str_replace(' ', '_', (string)$statusTitle)),
                'status_details' => (string)$statusTitle,
                'summary'        => $summary,
                'history'        => $history,
                'milestones'     => $history,
                'est_delivery'   => null,
                'raw_response'   => $res['data']
            ];
        }

        return [
            'success'        => false,
            'current_status' => 'UNKNOWN',
            'status_details' => $res['message'] ?? 'Tracking information unavailable',
            'summary'        => [],
            'history'        => [],
            'milestones'     => [],
            'est_delivery'   => null,
            'raw_response'   => $res['data'] ?? []
        ];
    }

    /**
     * Cancel an active shipment on BharatShip
     * Response: {"status": true, "message": "Shipment cancelled successfully", "client_order_id": "0"}
     */
    public function cancelShipment(string $awbNumber, string $shipmentId = ''): array
    {
        $payload = [
            'waybill'         => $awbNumber,
            'order_id'        => $shipmentId,
            'client_order_id' => $shipmentId
        ];

        $res = $this->request('api/cancelOrder', 'POST', $payload);
        if (!$res['success']) {
            $res = $this->request('api/v1/cancel-order', 'POST', $payload);
        }

        $isSuccess = ($res['data']['status'] ?? false) === true;

        return [
            'success' => $isSuccess,
            'message' => $res['data']['message'] ?? ($res['message'] ?? ($isSuccess ? 'Shipment cancelled successfully.' : 'Cancellation failed'))
        ];
    }
}
