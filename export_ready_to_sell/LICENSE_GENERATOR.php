<?php
/**
 * ============================================================
 *  OFFICIAL LICENSE KEY GENERATOR (FOR SAGAR / VENDOR ONLY)
 * ============================================================
 *  Use this tool to generate single-domain license keys for buyers.
 *  Keep this tool private. Do NOT share with buyers.
 * ============================================================
 */

require_once __DIR__ . '/../classes/LicenseManager.php';

$generatedKey = '';
$domain = '';
$clientName = '';
$expiry = '';
$whatsappMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain = trim($_POST['domain'] ?? '');
    $clientName = trim($_POST['client_name'] ?? 'Customer');
    $expiry = trim($_POST['expiry'] ?? '');

    if (!empty($domain)) {
        $expiryVal = !empty($expiry) ? $expiry : null;
        $generatedKey = LicenseManager::generateKey($domain, $clientName, $expiryVal);
        $cleanDomain = LicenseManager::cleanDomain($domain);

        $whatsappMsg = "Namaste {$clientName} Ji 🙏\n\nAapki website ({$cleanDomain}) ke liye official Single-Domain License Key generate kar di gayi hai:\n\n🔑 *License Key:*\n`{$generatedKey}`\n\nIs key ko apni website ki `.env` file mein daalein:\n`LICENSE_KEY={$generatedKey}`\n\nYeh key sirf aapke domain ({$cleanDomain}) par kaam karegi. Kisi aur ko share na karein.\n\nDhanyawad!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Commerce License Key Generator</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0b1120;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }
        .container {
            max-width: 720px;
            width: 100%;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 20px;
            padding: 35px 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            overflow: hidden;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 26px;
            font-weight: 800;
            color: #38bdf8;
            margin-bottom: 8px;
        }
        .header p {
            color: #94a3b8;
            font-size: 14px;
        }
        .badge-vendor {
            display: inline-block;
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.3);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 8px;
        }
        input[type="text"], input[type="date"] {
            width: 100%;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 12px 16px;
            color: #f8fafc;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        input[type="text"]:focus, input[type="date"]:focus {
            border-color: #38bdf8;
        }
        .help-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.4);
        }
        .btn-submit:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }
        .result-box {
            margin-top: 30px;
            background: #0f172a;
            border: 1px solid #0284c7;
            border-radius: 12px;
            padding: 24px;
        }
        .result-title {
            font-size: 14px;
            font-weight: 700;
            color: #38bdf8;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .key-display {
            background: #1e293b;
            border: 1px dashed #475569;
            padding: 14px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 13px;
            color: #22c55e;
            word-break: break-all;
            margin-bottom: 15px;
            user-select: all;
        }
        .copy-btn {
            background: #334155;
            color: #f8fafc;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .copy-btn:hover {
            background: #475569;
        }
        .whatsapp-preview {
            background: #064e3b;
            border: 1px solid #059669;
            border-radius: 10px;
            padding: 16px;
            margin-top: 15px;
            color: #ecfdf5;
            font-size: 13px;
            white-space: pre-wrap;
            line-height: 1.6;
            word-break: break-all;
            overflow-wrap: anywhere;
            overflow: hidden;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <span class="badge-vendor">🔒 Vendor Admin Tool</span>
        <h1>License Key Generator</h1>
        <p>Generate single-domain cryptographically signed keys for your clients.</p>
    </div>

    <form method="POST">
        <div class="form-group">
            <label>Client Live Domain Name *</label>
            <input type="text" name="domain" placeholder="e.g. clientstore.com or www.abcclothing.in" value="<?php echo htmlspecialchars($domain); ?>" required>
            <div class="help-text">Enter the exact domain where the client will host their website.</div>
        </div>

        <div class="form-group">
            <label>Client / Business Name</label>
            <input type="text" name="client_name" placeholder="e.g. Ramesh Cloth Store" value="<?php echo htmlspecialchars($clientName); ?>">
        </div>

        <div class="form-group">
            <label>License Expiry Date (Optional - Leave blank for Lifetime)</label>
            <input type="date" name="expiry" value="<?php echo htmlspecialchars($expiry); ?>">
            <div class="help-text">Leave blank for unlimited / lifetime single-domain license.</div>
        </div>

        <button type="submit" class="btn-submit">⚡ Generate License Key</button>
    </form>

    <?php if (!empty($generatedKey)): ?>
    <div class="result-box">
        <div class="result-title">
            <span>✅ Official License Key Generated:</span>
            <button type="button" class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($generatedKey); ?>', this)">📋 Copy Key</button>
        </div>
        <div class="key-display" id="keyField"><?php echo htmlspecialchars($generatedKey); ?></div>

        <div class="result-title" style="margin-top: 18px;">
            <span>💬 Ready-to-Send WhatsApp Message:</span>
            <button type="button" class="copy-btn" onclick="copyToClipboard(document.getElementById('waText').innerText, this)">📋 Copy Message</button>
        </div>
        <div class="whatsapp-preview" id="waText"><?php echo htmlspecialchars($whatsappMsg); ?></div>
    </div>
    <?php endif; ?>
</div>

<script>
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerText;
        btn.innerText = '✅ Copied!';
        setTimeout(() => { btn.innerText = orig; }, 2000);
    });
}
</script>

</body>
</html>
