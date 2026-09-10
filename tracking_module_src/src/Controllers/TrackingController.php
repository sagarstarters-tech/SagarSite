<?php
namespace TrackingModule\Controllers;

use TrackingModule\Config\TrackingConfig;
use TrackingModule\Repositories\TrackingRepository;
use TrackingModule\Services\TrackingService;
use Exception;

class TrackingController {
    private $service;
    private $repository;

    public function __construct() {
        $config = new TrackingConfig();
        $db = $config->getConnection();
        
        if (!$db) {
            $this->sendJson(['error' => 'Tracking Database connection failed'], 500);
        }

        $this->repository = new TrackingRepository($db);
        $this->service = new TrackingService($this->repository);
    }

    public function handleRequest() {
        $action = $_GET['action'] ?? ($_POST['action'] ?? '');

        switch ($action) {
            case 'get_customer_tracking':
                $this->getCustomerTracking();
                break;
            case 'admin_update_tracking':
                $this->adminUpdateTracking();
                break;
            case 'admin_get_couriers':
                $this->getCouriers();
                break;
            case 'admin_clear_tracking_logs':
                $this->adminClearTrackingLogs();
                break;
            default:
                $this->sendJson(['error' => 'Invalid or missing action'], 400);
        }
    }

    private function getCustomerTracking() {
        $order_id   = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $identifier = trim($_GET['email'] ?? ($_GET['phone'] ?? ($_GET['identifier'] ?? '')));

        if (empty($identifier) && empty($order_id)) {
            $this->sendJson(['status' => 'error', 'message' => 'Please enter your Mobile Number or Email to track your order.'], 400);
        }

        try {
            // Case 1: Both Order ID and Identifier provided
            if ($order_id > 0 && !empty($identifier)) {
                $data = $this->service->getCustomerTracking($order_id, $identifier);
                $this->sendJson(['status' => 'success', 'data' => $data]);
            }

            // Case 2: Only Identifier provided (No Order ID)
            if (!empty($identifier) && empty($order_id)) {
                $orders = $this->service->findCustomerOrders($identifier);
                if (empty($orders)) {
                    $this->sendJson(['status' => 'error', 'message' => 'No orders found matching this Mobile Number or Email. Please check and try again.'], 404);
                }

                if (count($orders) === 1) {
                    // Exactly one order found, return full tracking details for this order
                    $data = $this->service->getCustomerTracking((int)$orders[0]['id'], $identifier);
                    $this->sendJson(['status' => 'success', 'data' => $data]);
                } else {
                    // Multiple orders found, return list for user selection
                    $this->sendJson([
                        'status' => 'multiple_orders',
                        'message' => 'Multiple orders found for this account. Please select an order to track:',
                        'orders' => $orders
                    ]);
                }
            }

            // Case 3: Only Order ID provided (No Identifier)
            if ($order_id > 0 && empty($identifier)) {
                $this->sendJson(['status' => 'error', 'message' => 'Please also enter your registered Mobile Number or Email for Order #' . $order_id], 400);
            }
        } catch (Exception $e) {
            $code = $e->getCode() ?: 500;
            if ($code < 100 || $code > 599) $code = 400;
            $this->sendJson(['status' => 'error', 'message' => $e->getMessage()], $code);
        }
    }

    private function adminUpdateTracking() {
        // Basic security check - should ideally verify admin session here
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $role = $_SESSION['role'] ?? null;
        if ($role !== 'admin') {
            $msg = 'Unauthorized Admin Access';
            if (empty($role)) $msg .= ' (Session missing or role not set)';
            else $msg .= ' (Role: ' . $role . ')';
            
            $this->sendJson(['error' => $msg], 403);
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        $courier_id = intval($_POST['courier_id'] ?? 0);
        $tracking_number = $_POST['tracking_number'] ?? null;
        $est_date = $_POST['estimated_delivery_date'] ?? null;
        $status = $_POST['status'] ?? 'pending';

        if (!$order_id) {
            $this->sendJson(['error' => 'Order ID is required'], 400);
        }

        // Handle empty dates for DB
        if (empty($est_date)) {
            $est_date = null;
        }

        try {
            $result = $this->service->adminUpdateTracking($order_id, $courier_id, $tracking_number, $est_date, $status, true);
            $this->sendJson($result);
        } catch (Exception $e) {
            $this->sendJson(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function getCouriers() {
        try {
            $couriers = $this->repository->getActiveCouriers();
            $this->sendJson(['status' => 'success', 'data' => $couriers]);
        } catch (Exception $e) {
            $this->sendJson(['error' => 'Failed to fetch couriers'], 500);
        }
    }

    private function adminClearTrackingLogs() {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $this->sendJson(['error' => 'Unauthorized Admin Access'], 403);
        }

        try {
            $this->repository->clearAllStatusHistory();
            $this->sendJson(['status' => 'success', 'message' => 'Tracking status history cleared successfully.']);
        } catch (Exception $e) {
            $this->sendJson(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function sendJson($data, $statusCode = 200) {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
