<?php
namespace TrackingModule\Services;

use TrackingModule\Repositories\TrackingRepository;
use Exception;

class TrackingService {
    private $repository;

    public function __construct(TrackingRepository $repository) {
        $this->repository = $repository;
    }

    /**
     * Get complete tracking details for a customer
     */
    public function getCustomerTracking($order_id, $email) {
        if (!$this->repository->verifyOrderBelongsToEmail($order_id, $email)) {
            throw new Exception("Unauthorized: Order ID and Email do not match.", 403);
        }

        return $this->buildTrackingPayload($order_id);
    }

    /**
     * Get tracking details for admin (no email check)
     */
    public function getAdminTracking($order_id) {
        return $this->buildTrackingPayload($order_id);
    }

    /**
     * Update tracking information from Admin Panel
     */
    public function adminUpdateTracking($order_id, $courier_id, $tracking_number, $estimated_date, $status, $notify_customer = false) {
        // 1. Get current state to detect changes
        $currentTracking = $this->repository->getTrackingDetailsByOrder($order_id);
        $old_status = $currentTracking ? $currentTracking['current_status'] : null;
        $old_courier_id = $currentTracking ? $currentTracking['courier_id'] : null;
        $old_tracking_num = $currentTracking ? $currentTracking['tracking_number'] : null;
        $old_est_date = $currentTracking ? $currentTracking['estimated_delivery_date'] : null;

        // 2. Update basic tracking data
        $this->repository->upsertTrackingInfo($order_id, $courier_id, $tracking_number, $estimated_date);

        // 3. Log changes
        $changes = [];
        
        if ($old_status !== $status) {
            $this->repository->updateOrderStatus($order_id, $status);
            $changes[] = "Status updated to " . ucwords(str_replace('_', ' ', $status));
        }

        if ($old_courier_id != $courier_id || $old_tracking_num != $tracking_number || $old_est_date != $estimated_date) {
            $changes[] = "Tracking info updated";
            if ($tracking_number && $old_tracking_num != $tracking_number) {
                $changes[] = "Tracking #: {$tracking_number}";
            }
        }

        if (!empty($changes)) {
            $notes = implode(". ", $changes) . ". Updated via Tracking Panel.";
            $this->repository->logStatusChange($order_id, $status, $notes, 'admin');
        }

        // Optionally notify customer
        if ($notify_customer && $old_status !== $status) {
            require_once __DIR__ . '/../../../includes/mail_functions.php';
            require_once __DIR__ . '/../../../includes/whatsapp_functions.php';
            $conn = $this->repository->getConnection();
            $q = $conn->query("SELECT u.email, u.name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = $order_id");
            if ($q && $q->num_rows > 0) {
                $user = $q->fetch_assoc();
                sendOrderStatusEmail($conn, $order_id, $user['email'], $user['name'], $status);
            }
            sendCustomerOrderStatusWhatsApp($conn, $order_id);
        }

        return ['success' => true, 'message' => 'Tracking updated successfully'];
    }

    /**
     * Build the structured response payload
     */
    private function buildTrackingPayload($order_id) {
        $details = $this->repository->getTrackingDetailsByOrder($order_id);
        $history = $this->repository->getOrderStatusHistory($order_id);
        
        $current_status = $details ? $details['current_status'] : 'pending';
        
        // If no tracking record exists yet, build a default payload from just the order
        if (!$details) {
             // In a real scenario we'd query the orders table fresh here if details is null
             // But for brevity we assume the controller handles 404s
        }

        // Calculate visual progress index (0-4)
        $progressStages = ['pending', 'processing', 'partially_shipped', 'shipped', 'delivered'];
        
        // shipped is equivalent to partially_shipped in terms of stage index for simplicity
        $stageIndex = 0;
        switch($current_status) {
            case 'pending': $stageIndex = 0; break;
            case 'processing': $stageIndex = 1; break;
            case 'partially_shipped': $stageIndex = 2; break;
            case 'shipped': $stageIndex = 3; break;
            case 'delivered': $stageIndex = 4; break;
            case 'cancelled': $stageIndex = -1; break; // Error state
        }

        $tracking_url = null;
        if ($details && $details['tracking_number'] && $details['tracking_url_base']) {
             $tracking_url = $details['tracking_url_base'] . $details['tracking_number'];
        }

        return [
            'order_info' => [
                'order_id' => $order_id,
                'status' => $current_status,
                'status_formatted' => ucwords(str_replace('_', ' ', $current_status)),
                'progress_stage_index' => $stageIndex,
            ],
            'shipping' => [
                'courier_name' => $details['courier_name'] ?? 'Not Assigned',
                'tracking_number' => $details['tracking_number'] ?? null,
                'tracking_url' => $tracking_url,
                'estimated_delivery' => $details['estimated_delivery_date'] ?? null
            ],
            'timeline' => $history
        ];
    }
}
?>
