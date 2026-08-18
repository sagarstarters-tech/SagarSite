<?php
/**
 * ============================================================
 *  Invoice Render Helper
 *  Location: /includes/invoice_render.php
 * ============================================================
 *  Renders a beautiful, print-ready A4 invoice as HTML string.
 *  Used by both admin/invoice_view.php and public invoice.php
 * ============================================================
 */

/**
 * Render a complete standalone HTML invoice page.
 *
 * @param array $data  [invoice, order, items, settings]
 * @param bool  $showActions  Show print/download/whatsapp buttons
 * @param bool  $isAdmin      Admin context (show more actions)
 * @return string  Complete HTML document
 */
function renderInvoiceHTML(array $data, bool $showActions = true, bool $isAdmin = false): string
{
    $inv      = $data['invoice'];
    $order    = $data['order'];
    $items    = $data['items'];
    $settings = $data['settings'];

    // Store settings fallback
    $storeName    = !empty($settings['invoice_store_name']) ? $settings['invoice_store_name'] : ($GLOBALS['global_settings']['site_name'] ?? "Sagar Starter's");
    $storeAddress = $settings['invoice_store_address'] ?? '';
    $storePhone   = $settings['invoice_store_phone'] ?? ($GLOBALS['global_settings']['contact_phone'] ?? '');
    $storeEmail   = $settings['invoice_store_email'] ?? ($GLOBALS['global_settings']['contact_email'] ?? '');
    $gstNumber    = $settings['invoice_gst_number'] ?? '';
    $footerText   = $settings['invoice_footer_text'] ?? 'Thank you for shopping with us!';
    $terms        = $settings['invoice_terms'] ?? '';
    $currency     = $GLOBALS['global_currency'] ?? '₹';
    $logoFile     = $GLOBALS['global_settings']['header_logo_image'] ?? 'logo.jpg';
    $assetsUrl    = defined('ASSETS_URL') ? ASSETS_URL : '/assets';

    // Format dates
    $invoiceDate = date('d M Y', strtotime($inv['invoice_date']));
    $orderDate   = date('d M Y', strtotime($order['created_at']));

    // Payment method label
    $paymentLabel = strtoupper($order['payment_method'] ?? 'N/A');
    if (isset($order['payment_mode']) && $order['payment_mode'] === 'COD_PARTIAL') {
        $paymentLabel = 'COD (Partial Advance)';
    }

    // File name suggestion for PDF
    $pdfFileName = 'Invoice_' . $inv['order_id'] . '_' . date('Ymd', strtotime($inv['invoice_date']));

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pdfFileName; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
    <style>
        /* ── Reset & Base ──────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
            color: #2d3436;
            background: #f0f2f5;
            line-height: 1.6;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Action Bar ────────────────────────────────── */
        .action-bar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .action-bar .bar-title {
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .action-bar .bar-title i { color: #64b5f6; }
        .action-btns { display: flex; gap: 8px; flex-wrap: wrap; }
        .action-btns .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-print { background: #e3f2fd; color: #1565c0; }
        .btn-print:hover { background: #bbdefb; transform: translateY(-1px); }
        .btn-download { background: #e8f5e9; color: #2e7d32; }
        .btn-download:hover { background: #c8e6c9; transform: translateY(-1px); }
        .btn-whatsapp { background: #25d366; color: #fff; }
        .btn-whatsapp:hover { background: #1da851; transform: translateY(-1px); }
        .btn-back { background: rgba(255,255,255,0.15); color: #fff; }
        .btn-back:hover { background: rgba(255,255,255,0.25); }

        /* ── Invoice Container ─────────────────────────── */
        .invoice-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 16px 40px;
        }
        .invoice-page {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        /* ── Header ────────────────────────────────────── */
        .invoice-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #0f3460 60%, #16213e 100%);
            color: #fff;
            padding: 32px 40px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .header-left { display: flex; align-items: center; gap: 18px; }
        .header-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            background: #fff;
            border-radius: 12px;
            padding: 6px;
        }
        .store-name {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .store-details {
            font-size: 12px;
            opacity: 0.85;
            margin-top: 4px;
            line-height: 1.7;
        }
        .header-right { text-align: right; }
        .invoice-badge {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 3px;
            opacity: 0.95;
        }
        .invoice-badge-sub {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ── Meta Grid ─────────────────────────────────── */
        .invoice-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 2px solid #f0f2f5;
        }
        .meta-box {
            padding: 24px 40px;
        }
        .meta-box:first-child { border-right: 2px solid #f0f2f5; }
        .meta-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #95a5a6;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 13px;
        }
        .meta-row .key { color: #636e72; font-weight: 500; }
        .meta-row .val { font-weight: 700; color: #2d3436; }

        /* ── Bill To ───────────────────────────────────── */
        .bill-to {
            padding: 24px 40px;
            background: #fafbfc;
            border-bottom: 2px solid #f0f2f5;
        }
        .customer-name {
            font-size: 16px;
            font-weight: 700;
            color: #2d3436;
            margin-bottom: 6px;
        }
        .customer-info {
            font-size: 13px;
            color: #636e72;
            line-height: 1.8;
        }

        /* ── Items Table ───────────────────────────────── */
        .items-section { padding: 0; }
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table thead th {
            background: #1a1a2e;
            color: #fff;
            padding: 14px 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            text-align: left;
        }
        .items-table thead th:first-child { padding-left: 40px; }
        .items-table thead th:last-child { text-align: right; padding-right: 40px; }
        .items-table tbody td {
            padding: 14px 20px;
            font-size: 13px;
            border-bottom: 1px solid #f0f2f5;
            vertical-align: middle;
        }
        .items-table tbody td:first-child { padding-left: 40px; }
        .items-table tbody td:last-child { text-align: right; padding-right: 40px; font-weight: 700; }
        .items-table tbody tr:nth-child(even) { background: #fafbfc; }
        .items-table tbody tr:hover { background: #f0f4ff; }
        .item-name { font-weight: 600; color: #2d3436; }
        .item-id { font-size: 11px; color: #95a5a6; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* ── Summary ───────────────────────────────────── */
        .invoice-summary {
            padding: 24px 40px;
            display: flex;
            justify-content: flex-end;
        }
        .summary-box {
            width: 320px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
        }
        .summary-row .label { color: #636e72; }
        .summary-row .value { font-weight: 600; color: #2d3436; }
        .summary-divider {
            border: none;
            border-top: 2px dashed #dfe6e9;
            margin: 6px 0;
        }
        .summary-total {
            display: flex;
            justify-content: space-between;
            padding: 12px 16px;
            background: linear-gradient(135deg, #1a1a2e, #0f3460);
            border-radius: 10px;
            color: #fff;
            margin-top: 8px;
        }
        .summary-total .label {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
        }
        .summary-total .value {
            font-size: 18px;
            font-weight: 800;
            color: #fff;
        }

        /* ── Payment Badge ─────────────────────────────── */
        .payment-section {
            padding: 0 40px 24px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .payment-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge-cod { background: #fff3e0; color: #e65100; border: 1px solid #ffcc80; }
        .badge-online { background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }

        /* ── Footer ────────────────────────────────────── */
        .invoice-footer {
            background: #fafbfc;
            border-top: 2px solid #f0f2f5;
            padding: 28px 40px;
            text-align: center;
        }
        .footer-thanks {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        .footer-terms {
            font-size: 11px;
            color: #95a5a6;
            line-height: 1.8;
            max-width: 500px;
            margin: 0 auto;
        }
        .footer-generated {
            font-size: 10px;
            color: #b2bec3;
            margin-top: 12px;
            font-style: italic;
        }

        /* ── Print Styles ──────────────────────────────── */
        @media print {
            body { background: #fff !important; margin: 0; padding: 0; }
            .action-bar { display: none !important; }
            .invoice-container { margin: 0; padding: 0; max-width: none; }
            .invoice-page {
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            .invoice-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .items-table thead th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .summary-total {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page {
                size: A4;
                margin: 10mm;
            }
        }

        /* ── Responsive ────────────────────────────────── */
        @media (max-width: 640px) {
            .invoice-header { flex-direction: column; gap: 16px; padding: 24px 20px; }
            .header-right { text-align: left; }
            .invoice-meta { grid-template-columns: 1fr; }
            .meta-box:first-child { border-right: none; border-bottom: 2px solid #f0f2f5; }
            .meta-box, .bill-to, .payment-section { padding-left: 20px; padding-right: 20px; }
            .items-table thead th:first-child,
            .items-table tbody td:first-child { padding-left: 20px; }
            .items-table thead th:last-child,
            .items-table tbody td:last-child { padding-right: 20px; }
            .invoice-summary { padding: 24px 20px; }
            .summary-box { width: 100%; }
            .invoice-footer { padding: 24px 20px; }
            .action-bar { flex-direction: column; gap: 10px; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <?php if ($showActions): ?>
    <div class="action-bar">
        <div class="bar-title">
            <i class="fas fa-file-invoice"></i>
            <?php echo htmlspecialchars($inv['invoice_number']); ?>
        </div>
        <div class="action-btns">
            <?php if ($isAdmin): ?>
                <a href="order_details.php?id=<?php echo $inv['order_id']; ?>" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Order
                </a>
            <?php endif; ?>
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
            <button class="btn btn-download" onclick="window.print()">
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
            <?php if ($isAdmin): ?>
                <button class="btn btn-whatsapp" onclick="sendInvoiceWhatsApp(<?php echo $inv['order_id']; ?>)" id="waBtn">
                    <i class="fab fa-whatsapp"></i> Send via WhatsApp
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="invoice-container">
        <div class="invoice-page">

            <!-- Header -->
            <div class="invoice-header">
                <div class="header-left">
                    <img src="<?php echo $assetsUrl; ?>/images/<?php echo htmlspecialchars($logoFile); ?>"
                         alt="Logo" class="header-logo"
                         onerror="this.style.display='none'">
                    <div>
                        <div class="store-name"><?php echo htmlspecialchars($storeName); ?></div>
                        <div class="store-details">
                            <?php if ($storeAddress): ?><?php echo htmlspecialchars($storeAddress); ?><br><?php endif; ?>
                            <?php if ($storePhone): ?><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($storePhone); ?><?php endif; ?>
                            <?php if ($storePhone && $storeEmail): ?> &nbsp;|&nbsp; <?php endif; ?>
                            <?php if ($storeEmail): ?><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($storeEmail); ?><?php endif; ?>
                            <?php if ($gstNumber): ?><br><strong>GSTIN:</strong> <?php echo htmlspecialchars($gstNumber); ?><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="header-right">
                    <div class="invoice-badge">INVOICE</div>
                    <div class="invoice-badge-sub"><?php echo $gstNumber ? 'Tax Invoice' : 'Invoice'; ?></div>
                </div>
            </div>

            <!-- Meta Information -->
            <div class="invoice-meta">
                <div class="meta-box">
                    <div class="meta-label">Invoice Details</div>
                    <div class="meta-row">
                        <span class="key">Invoice No.</span>
                        <span class="val"><?php echo htmlspecialchars($inv['invoice_number']); ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="key">Invoice Date</span>
                        <span class="val"><?php echo $invoiceDate; ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="key">Order ID</span>
                        <span class="val">#<?php echo $inv['order_id']; ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="key">Order Date</span>
                        <span class="val"><?php echo $orderDate; ?></span>
                    </div>
                </div>
                <div class="meta-box">
                    <div class="meta-label">Bill To</div>
                    <div class="customer-name"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></div>
                    <div class="customer-info">
                        <?php
                        $addressParts = array_filter([
                            $order['customer_address'] ?? '',
                            $order['customer_city'] ?? '',
                            $order['customer_state'] ?? '',
                            $order['customer_zip'] ?? '',
                            $order['customer_country'] ?? ''
                        ]);
                        echo htmlspecialchars(implode(', ', $addressParts) ?: 'N/A');
                        ?>
                        <?php if (!empty($order['customer_phone'])): ?>
                            <br><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($order['customer_phone']); ?>
                        <?php endif; ?>
                        <?php if (!empty($order['customer_email'])): ?>
                            <br><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($order['customer_email']); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="items-section">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:5%">#</th>
                            <th style="width:40%">Product</th>
                            <th class="text-center" style="width:10%">Qty</th>
                            <th class="text-right" style="width:15%">Unit Price</th>
                            <th class="text-right" style="width:15%">Shipping</th>
                            <th style="width:15%">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sr = 0;
                        foreach ($items as $item):
                            $sr++;
                            $lineTotal = $item['quantity'] * $item['price'];
                            $lineShipping = ($item['product_type'] === 'physical') ? (float)$item['shipping_cost'] : 0;
                        ?>
                        <tr>
                            <td style="padding-left:40px; color:#95a5a6; font-weight:600;"><?php echo $sr; ?></td>
                            <td>
                                <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="item-id">ID: #<?php echo $item['product_id']; ?></div>
                            </td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-right"><?php echo $currency . number_format($item['price'], 2); ?></td>
                            <td class="text-right">
                                <?php if ($item['product_type'] !== 'physical'): ?>
                                    <span style="color:#95a5a6">N/A</span>
                                <?php elseif ($item['shipping_cost'] > 0): ?>
                                    <?php echo $currency . number_format($item['shipping_cost'], 2); ?>
                                <?php else: ?>
                                    <span style="color:#27ae60">Free</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $currency . number_format($lineTotal + $lineShipping, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Summary -->
            <div class="invoice-summary">
                <div class="summary-box">
                    <div class="summary-row">
                        <span class="label">Subtotal</span>
                        <span class="value"><?php echo $currency . number_format($inv['subtotal'], 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="label">Shipping</span>
                        <span class="value"><?php echo $inv['shipping_total'] > 0 ? $currency . number_format($inv['shipping_total'], 2) : '<span style="color:#27ae60">Free</span>'; ?></span>
                    </div>
                    <?php if ($inv['cod_charges'] > 0): ?>
                    <div class="summary-row">
                        <span class="label"><i class="fas fa-money-bill-wave" style="color:#e65100;margin-right:4px"></i>COD Charges</span>
                        <span class="value"><?php echo $currency . number_format($inv['cod_charges'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($inv['discount'] > 0): ?>
                    <div class="summary-row">
                        <span class="label" style="color:#27ae60"><i class="fas fa-tag" style="margin-right:4px"></i>Discount</span>
                        <span class="value" style="color:#27ae60">-<?php echo $currency . number_format($inv['discount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($inv['tax_amount'] > 0): ?>
                    <div class="summary-row">
                        <span class="label">Tax (GST)</span>
                        <span class="value"><?php echo $currency . number_format($inv['tax_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <hr class="summary-divider">
                    <div class="summary-total">
                        <span class="label">TOTAL</span>
                        <span class="value"><?php echo $currency . number_format($inv['total_amount'], 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Info -->
            <div class="payment-section">
                <span style="font-size:13px;color:#636e72;font-weight:600;">Payment:</span>
                <?php if ($order['payment_method'] === 'cod'): ?>
                    <span class="payment-badge badge-cod"><i class="fas fa-money-bill-wave"></i> <?php echo $paymentLabel; ?></span>
                <?php else: ?>
                    <span class="payment-badge badge-online"><i class="fas fa-credit-card"></i> <?php echo $paymentLabel; ?></span>
                <?php endif; ?>

                <?php if (isset($order['payment_mode']) && $order['payment_mode'] === 'COD_PARTIAL'): ?>
                    <span style="font-size:11px;color:#636e72;">
                        (Advance: <?php echo $currency . number_format($order['advance_amount'] ?? 0, 2); ?>
                         | COD Balance: <?php echo $currency . number_format($order['remaining_amount'] ?? 0, 2); ?>)
                    </span>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="invoice-footer">
                <div class="footer-thanks"><?php echo htmlspecialchars($footerText); ?></div>
                <?php if ($terms): ?>
                    <div class="footer-terms"><?php echo htmlspecialchars($terms); ?></div>
                <?php endif; ?>
                <div class="footer-generated">
                    This is a computer-generated invoice and does not require a physical signature.
                </div>
            </div>

        </div>
    </div>

    <?php if ($showActions && $isAdmin): ?>
    <script>
    function sendInvoiceWhatsApp(orderId) {
        const btn = document.getElementById('waBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

        fetch('ajax_invoice_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=send_whatsapp&order_id=' + orderId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.sending_mode === 'web') {
                    const waLink = 'https://wa.me/' + data.phone + '?text=' + encodeURIComponent(data.message);
                    window.open(waLink, '_blank');
                }
                btn.innerHTML = '<i class="fas fa-check"></i> Sent!';
                btn.style.background = '#2e7d32';
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
                btn.innerHTML = '<i class="fab fa-whatsapp"></i> Send via WhatsApp';
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Network error');
            btn.innerHTML = '<i class="fab fa-whatsapp"></i> Send via WhatsApp';
            btn.disabled = false;
        });
    }
    </script>
    <?php endif; ?>
</body>
</html>
    <?php
    return ob_get_clean();
}
