<?php
/**
 * ============================================================
 *  Invoice Service
 *  Location: /includes/InvoiceService.php
 * ============================================================
 *  Handles all invoice business logic:
 *    - Auto-generation on order status change
 *    - Invoice number sequencing
 *    - Secure access token generation
 *    - Data aggregation for rendering
 *    - WhatsApp invoice link sending
 * ============================================================
 */

class InvoiceService
{
    private $conn;

    /** Statuses that trigger auto-invoice generation */
    private const INVOICE_TRIGGER_STATUSES = [
        'processing', 'shipped', 'delivered', 'completed'
    ];

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // ── Invoice Generation ──────────────────────────────────

    /**
     * Generate an invoice for an order (idempotent — won't duplicate).
     * @return array ['success' => bool, 'invoice_id' => int|null, 'message' => string]
     */
    public function generateInvoice(int $orderId): array
    {
        // Check if invoice already exists
        $existing = $this->getInvoiceByOrder($orderId);
        if ($existing) {
            return [
                'success' => true,
                'invoice_id' => $existing['id'],
                'invoice_number' => $existing['invoice_number'],
                'message' => 'Invoice already exists.'
            ];
        }

        // Fetch order data
        $stmt = $this->conn->prepare("
            SELECT o.*, u.id as uid
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            return ['success' => false, 'invoice_id' => null, 'message' => 'Order not found.'];
        }

        // Calculate line-item totals
        $items_stmt = $this->conn->prepare("
            SELECT oi.*, p.product_type
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $items_stmt->bind_param("i", $orderId);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();

        $subtotal = 0;
        $shipping_total = 0;
        while ($item = $items_result->fetch_assoc()) {
            $subtotal += $item['quantity'] * $item['price'];
            if ($item['product_type'] === 'physical') {
                $shipping_total += (float)$item['shipping_cost'];
            }
        }
        $items_stmt->close();

        $cod_charges = (float)($order['cod_charge'] ?? 0);
        $total_amount = (float)$order['total_amount'];
        $calculated = $subtotal + $shipping_total + $cod_charges;
        $discount = max(0, $calculated - $total_amount);

        // Generate unique invoice number and access token
        $invoice_number = $this->generateInvoiceNumber();
        $access_token = bin2hex(random_bytes(32));

        $ins = $this->conn->prepare("
            INSERT INTO invoices
                (invoice_number, order_id, user_id, invoice_date, subtotal,
                 shipping_total, cod_charges, discount, total_amount,
                 payment_method, access_token, status)
            VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, 'generated')
        ");
        $ins->bind_param(
            "siidddddss",
            $invoice_number,
            $orderId,
            $order['user_id'],
            $subtotal,
            $shipping_total,
            $cod_charges,
            $discount,
            $total_amount,
            $order['payment_method'],
            $access_token
        );

        if ($ins->execute()) {
            $invoice_id = $ins->insert_id;
            $ins->close();
            return [
                'success' => true,
                'invoice_id' => $invoice_id,
                'invoice_number' => $invoice_number,
                'message' => 'Invoice generated successfully.'
            ];
        }

        $error = $ins->error;
        $ins->close();
        return ['success' => false, 'invoice_id' => null, 'message' => 'DB Error: ' . $error];
    }

    /**
     * Auto-generate invoice if the new status qualifies.
     */
    public function autoGenerateOnStatusChange(int $orderId, string $newStatus): ?array
    {
        $settings = $this->getInvoiceSettings();
        if (($settings['invoice_auto_generate'] ?? '0') !== '1') {
            return null;
        }

        if (!in_array($newStatus, self::INVOICE_TRIGGER_STATUSES, true)) {
            return null;
        }

        $result = $this->generateInvoice($orderId);

        // Auto-send via WhatsApp if enabled
        if ($result['success'] && ($settings['invoice_auto_send_whatsapp'] ?? '0') === '1') {
            $existing = $this->getInvoiceByOrder($orderId);
            if ($existing && !$existing['whatsapp_sent']) {
                $this->sendViaWhatsApp($orderId);
            }
        }

        return $result;
    }

    // ── Invoice Retrieval ───────────────────────────────────

    /**
     * Get invoice record by order ID.
     */
    public function getInvoiceByOrder(int $orderId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM invoices WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Get invoice record by access token (for public view).
     */
    public function getInvoiceByToken(string $token): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM invoices WHERE access_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Full invoice data for rendering (invoice + order + customer + items).
     */
    public function getFullInvoiceData(int $orderId): ?array
    {
        $invoice = $this->getInvoiceByOrder($orderId);
        if (!$invoice) return null;

        // Order + Customer
        $stmt = $this->conn->prepare("
            SELECT o.*,
                   u.name as customer_name, u.email as customer_email,
                   u.phone as customer_phone, u.address as customer_address,
                   u.city as customer_city, u.state as customer_state,
                   u.country as customer_country, u.zip_code as customer_zip
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) return null;

        // Order Items
        $items_stmt = $this->conn->prepare("
            SELECT oi.*, p.name as product_name, p.image as product_image, p.product_type
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $items_stmt->bind_param("i", $orderId);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        $items = [];
        while ($item = $items_result->fetch_assoc()) {
            $items[] = $item;
        }
        $items_stmt->close();

        return [
            'invoice'  => $invoice,
            'order'    => $order,
            'items'    => $items,
            'settings' => $this->getInvoiceSettings(),
        ];
    }

    // ── Invoice Listing (Admin) ─────────────────────────────

    /**
     * Get paginated invoice list for admin.
     */
    public function getAllInvoices(int $page = 1, int $limit = 15, string $statusFilter = 'all'): array
    {
        $offset = ($page - 1) * $limit;
        $where = "";
        if ($statusFilter !== 'all' && $statusFilter !== '') {
            $statusFilter = $this->conn->real_escape_string($statusFilter);
            $where = " WHERE i.status = '$statusFilter'";
        }

        $countQ = $this->conn->query("SELECT COUNT(*) as c FROM invoices i" . $where);
        $total = $countQ ? $countQ->fetch_assoc()['c'] : 0;

        $sql = "SELECT i.*, o.status as order_status, u.name as customer_name, u.phone as customer_phone
                FROM invoices i
                JOIN orders o ON i.order_id = o.id
                JOIN users u ON i.user_id = u.id
                $where
                ORDER BY i.created_at DESC
                LIMIT $limit OFFSET $offset";
        $result = $this->conn->query($sql);

        $invoices = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $invoices[] = $row;
            }
        }

        return [
            'invoices'    => $invoices,
            'total'       => $total,
            'total_pages' => ceil($total / $limit),
            'page'        => $page,
        ];
    }

    /**
     * Get invoice statistics for dashboard cards.
     */
    public function getStats(): array
    {
        $r = $this->conn->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN whatsapp_sent = 1 THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN whatsapp_sent = 0 THEN 1 ELSE 0 END) as not_sent,
                SUM(total_amount) as revenue
            FROM invoices
        ");
        return $r ? $r->fetch_assoc() : ['total'=>0,'sent'=>0,'not_sent'=>0,'revenue'=>0];
    }

    // ── WhatsApp Integration ────────────────────────────────

    /**
     * Send invoice link via WhatsApp (extends existing API, does NOT modify it).
     */
    public function sendViaWhatsApp(int $orderId): array
    {
        $invoice = $this->getInvoiceByOrder($orderId);
        if (!$invoice) {
            return ['success' => false, 'error' => 'Invoice not found. Generate it first.'];
        }

        // Fetch order + customer
        $stmt = $this->conn->prepare("
            SELECT o.*, u.name as customer_name, u.phone as customer_phone
            FROM orders o JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order || empty($order['customer_phone'])) {
            return ['success' => false, 'error' => 'Customer phone not found.'];
        }

        // Build invoice URL
        $siteUrl = defined('SITE_URL') ? SITE_URL : '';
        $invoiceLink = $siteUrl . '/invoice.php?token=' . $invoice['access_token'];

        // Build message
        $message = "Hello " . $order['customer_name'] . ",\n\n"
            . "Your invoice for Order #" . $orderId . " is ready.\n"
            . "Invoice No: " . $invoice['invoice_number'] . "\n"
            . "Amount: " . ($GLOBALS['global_currency'] ?? '₹') . number_format($invoice['total_amount'], 2) . "\n\n"
            . "View / Download Invoice:\n" . $invoiceLink . "\n\n"
            . "Thank you for shopping with us!";

        // Normalize phone
        $clean_number = preg_replace('/[^0-9]/', '', $order['customer_phone']);
        if (strpos($clean_number, '0') === 0) $clean_number = ltrim($clean_number, '0');
        if (strlen($clean_number) == 10) $clean_number = '91' . $clean_number;

        // Check WhatsApp settings
        $set_q = $this->conn->query("SELECT * FROM whatsapp_settings WHERE id = 1");
        $settings = $set_q ? $set_q->fetch_assoc() : null;

        $sending_mode = ($settings && $settings['sending_mode'] === 'api') ? 'api' : 'web';
        $status_text = 'Pending';

        if ($sending_mode === 'api' && $settings && !empty($settings['api_token']) && !empty($settings['phone_number_id'])) {
            // Send via Meta API (plain text mode — no template needed for invoice)
            $token = trim($settings['api_token']);
            $phone_id = trim($settings['phone_number_id']);
            $url = "https://graph.facebook.com/v19.0/{$phone_id}/messages";

            $payload = [
                "messaging_product" => "whatsapp",
                "recipient_type"    => "individual",
                "to"                => $clean_number,
                "type"              => "text",
                "text"              => ["preview_url" => true, "body" => $message]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $meta_response = json_decode($result, true);
            if ($http_code == 200 && isset($meta_response['messages'])) {
                $status_text = 'Sent via Meta API (Invoice)';
            } else {
                $err = $meta_response['error']['message'] ?? 'Unknown error';
                $status_text = 'Failed API: ' . substr($err, 0, 100);
            }
        } else {
            $status_text = 'Sent via Web (Invoice)';
        }

        // Log to whatsapp_logs
        $log_stmt = $this->conn->prepare(
            "INSERT INTO whatsapp_logs (order_id, customer_number, message, sending_mode, status)
             VALUES (?, ?, ?, ?, ?)"
        );
        $log_stmt->bind_param("issss", $orderId, $clean_number, $message, $sending_mode, $status_text);
        $log_stmt->execute();
        $log_stmt->close();

        // Mark invoice as sent
        $upd = $this->conn->prepare("UPDATE invoices SET whatsapp_sent = 1, whatsapp_sent_at = NOW(), status = 'sent' WHERE order_id = ?");
        $upd->bind_param("i", $orderId);
        $upd->execute();
        $upd->close();

        return [
            'success'      => (strpos($status_text, 'Failed') === false),
            'sending_mode' => $sending_mode,
            'message'      => $message,
            'phone'        => $clean_number,
            'status'       => $status_text,
            'invoice_link' => $invoiceLink ?? '',
        ];
    }

    // ── Settings ────────────────────────────────────────────

    /**
     * Get all invoice_* settings as key => value array.
     */
    public function getInvoiceSettings(): array
    {
        $result = $this->conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'invoice_%'");
        $settings = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        return $settings;
    }

    /**
     * Update invoice settings.
     */
    public function updateSettings(array $data): bool
    {
        $allowed = [
            'invoice_auto_generate', 'invoice_auto_send_whatsapp',
            'invoice_prefix', 'invoice_store_name', 'invoice_store_address',
            'invoice_store_phone', 'invoice_store_email', 'invoice_gst_number',
            'invoice_footer_text', 'invoice_terms'
        ];

        $stmt = $this->conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $stmt->bind_param("ss", $key, $value);
                $stmt->execute();
            }
        }
        $stmt->close();
        return true;
    }

    // ── Private Helpers ─────────────────────────────────────

    /**
     * Generate the next sequential invoice number: PREFIX-YYYYMMDD-NNNNN
     */
    private function generateInvoiceNumber(): string
    {
        $settings = $this->getInvoiceSettings();
        $prefix = $settings['invoice_prefix'] ?? 'INV';
        $date = date('Ymd');

        // Get the latest invoice number for today
        $like = $prefix . '-' . $date . '-%';
        $stmt = $this->conn->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $seq = 1;
        if ($row) {
            $parts = explode('-', $row['invoice_number']);
            $seq = intval(end($parts)) + 1;
        }

        return $prefix . '-' . $date . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
