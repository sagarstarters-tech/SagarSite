<?php
/**
 * One-Time Tool: Fix Cart Reminder Templates (Marketing → Utility)
 * Access: /admin/fix_cart_templates.php
 * DELETE THIS FILE after use!
 */
// admin_header.php already handles auth (redirects to login if not admin)
require_once 'admin_header.php';

// Double-check using the correct auth method
if (!AuthMiddleware::isAdmin()) {
    die('Access denied.');
}

$ws = $conn->query("SELECT api_token, waba_id, phone_number_id FROM whatsapp_settings WHERE id = 1")->fetch_assoc();
$TOKEN    = trim($ws['api_token'] ?? '');
$WABA_ID  = trim($ws['waba_id'] ?? '');
$PHONE_ID = trim($ws['phone_number_id'] ?? '');

if (empty($TOKEN) || empty($WABA_ID)) {
    echo '<div style="color:red;padding:20px;font-family:sans-serif">ERROR: API Token ya WABA ID missing. Admin &rarr; WhatsApp Settings mein set karo pehle.</div>';
    exit;
}

function meta_call($method, $url, $token, $data = null) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_CUSTOMREQUEST  => $method,
    ];
    if ($data !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    curl_setopt_array($ch, $opts);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => json_decode($res, true), 'code' => $code, 'raw' => $res];
}

$templates = [
    [
        'name'         => 'cart_reminder_01',
        'body'         => "Hi {{1}}! You left items in your cart.\n\nItems: {{2}}\nTotal: Rs.{{3}}\n\nComplete your order before stock runs out!",
        'body_example' => [['Rahul', '5 HP Starter, Float Switch', '5600']],
        'footer'       => "Sagar Starter's",
        'btn_text'     => 'Complete Order',
        'btn_url'      => 'https://www.sagarstarters.com/cart.php',
    ],
    [
        'name'         => 'cart_reminder_02',
        'body'         => "Hi {{1}}! Your cart is still waiting.\n\nItems: {{2}}\nTotal: Rs.{{3}}\n\nComplete your purchase today!",
        'body_example' => [['Rahul', '5 HP Starter, Float Switch', '5600']],
        'footer'       => "Sagar Starter's",
        'btn_text'     => 'Go to Cart',
        'btn_url'      => 'https://www.sagarstarters.com/cart.php',
    ],
    [
        'name'         => 'cart_reminder_03',
        'body'         => "Hi {{1}}! Last chance reminder.\n\nItems: {{2}}\nTotal: Rs.{{3}}\n\nYour cart will expire soon. Order now!",
        'body_example' => [['Rahul', '5 HP Starter, Float Switch', '5600']],
        'footer'       => "Sagar Starter's",
        'btn_text'     => 'Buy Now',
        'btn_url'      => 'https://www.sagarstarters.com/cart.php',
    ],
    [
        'name'         => 'cart_reminder_04',
        'body'         => "Hi {{1}}! We saved your cart.\n\nItems: {{2}}\nTotal: Rs.{{3}}\n\nComplete your order and we will ship immediately!",
        'body_example' => [['Rahul', '5 HP Starter, Float Switch', '5600']],
        'footer'       => "Sagar Starter's",
        'btn_text'     => 'Order Now',
        'btn_url'      => 'https://www.sagarstarters.com/cart.php',
    ],
];

// Handle POST actions (CSRF already verified by admin_header → AuthMiddleware)
$action  = $_POST['action'] ?? '';
$results = [];

if ($action === 'delete') {
    foreach ($templates as $tpl) {
        $url = "https://graph.facebook.com/v21.0/{$WABA_ID}/message_templates?name={$tpl['name']}";
        $r   = meta_call('DELETE', $url, $TOKEN);
        $results[] = ['action' => 'DELETE', 'name' => $tpl['name'], 'code' => $r['code'], 'body' => $r['body']];
    }
}

if ($action === 'create') {
    foreach ($templates as $tpl) {
        $payload = [
            'name'       => $tpl['name'],
            'category'   => 'UTILITY',
            'language'   => 'en',
            'components' => [
                [
                    'type'    => 'BODY',
                    'text'    => $tpl['body'],
                    'example' => ['body_text' => $tpl['body_example']],
                ],
                ['type' => 'FOOTER', 'text' => $tpl['footer']],
                [
                    'type'    => 'BUTTONS',
                    'buttons' => [['type' => 'URL', 'text' => $tpl['btn_text'], 'url' => $tpl['btn_url']]],
                ],
            ],
        ];
        $url = "https://graph.facebook.com/v21.0/{$WABA_ID}/message_templates";
        $r   = meta_call('POST', $url, $TOKEN, $payload);
        $results[] = ['action' => 'CREATE', 'name' => $tpl['name'], 'code' => $r['code'], 'body' => $r['body']];
    }
}

// Fetch current cart_reminder templates from Meta
$current   = meta_call('GET', "https://graph.facebook.com/v21.0/{$WABA_ID}/message_templates?fields=name,category,status&limit=50", $TOKEN);
$cart_tpls = array_filter($current['body']['data'] ?? [], fn($t) => strpos($t['name'], 'cart_reminder') !== false);

// Generate CSRF token for forms
$csrf = csrf_token();
?>
<div style="padding:30px;font-family:sans-serif;max-width:900px">

<div style="background:#fff;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.1)">
  <h2 style="margin:0 0 12px">🛠️ Cart Template Fix Tool</h2>
  <p>Marketing category templates delete karke <strong>Utility</strong> mein naye banayega.</p>
  <p><strong>WABA ID:</strong> <?=htmlspecialchars($WABA_ID)?> &nbsp;|&nbsp; <strong>Phone ID:</strong> <?=htmlspecialchars($PHONE_ID)?></p>
</div>

<!-- Current Status -->
<div style="background:#fff;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.1)">
  <h2 style="margin:0 0 12px">📋 Current Cart Templates on Meta</h2>
  <?php if(empty($cart_tpls)): ?>
    <p style="color:#666">Koi cart_reminder template nahi mili — seedha Step 2 (Create) karo.</p>
  <?php else: ?>
  <table style="width:100%;border-collapse:collapse">
    <tr style="background:#f8f9fa"><th style="padding:10px;text-align:left">Name</th><th style="padding:10px;text-align:left">Category</th><th style="padding:10px;text-align:left">Status</th></tr>
    <?php foreach($cart_tpls as $t):
      $cat = strtoupper($t['category'] ?? '');
      $sts = strtoupper($t['status'] ?? '');
      $cbg = $cat === 'UTILITY' ? '#d4edda' : '#ffeeba';
      $cfg = $cat === 'UTILITY' ? '#155724' : '#856404';
      $sbg = $sts === 'APPROVED' ? '#d4edda' : '#fff3cd';
      $sfg = $sts === 'APPROVED' ? '#155724' : '#856404';
    ?>
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:10px"><?=htmlspecialchars($t['name'])?></td>
      <td style="padding:10px"><span style="background:<?=$cbg?>;color:<?=$cfg?>;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700"><?=$cat?></span></td>
      <td style="padding:10px"><span style="background:<?=$sbg?>;color:<?=$sfg?>;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700"><?=$sts?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<!-- Results -->
<?php if(!empty($results)): ?>
<div style="background:#fff;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.1)">
  <h2 style="margin:0 0 12px"><?=$action==='delete'?'🗑️ Delete Results':'✅ Create Results'?></h2>
  <table style="width:100%;border-collapse:collapse">
    <tr style="background:#f8f9fa"><th style="padding:10px;text-align:left">Template</th><th style="padding:10px;text-align:left">HTTP</th><th style="padding:10px;text-align:left">Result</th></tr>
    <?php foreach($results as $r): ?>
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:10px"><strong><?=htmlspecialchars($r['name'])?></strong></td>
      <td style="padding:10px"><?=$r['code']?></td>
      <td style="padding:10px">
        <?php if(in_array($r['code'],[200,201])): ?>
          <span style="color:#28a745;font-weight:700">✅ <?=$action==='delete'?'Deleted!':'Created! (Approval ~5-10 min)'?></span>
        <?php else: ?>
          <span style="color:#dc3545">❌ <?=htmlspecialchars(json_encode($r['body']))?></span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<!-- Action Buttons -->
<div style="background:#fff;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.1)">
  <h2 style="margin:0 0 12px">⚡ Actions</h2>
  <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:14px;margin-bottom:16px">
    ⚠️ <strong>Order:</strong> Pehle Step 1 (Delete) karo, phir Step 2 (Create) karo.
  </div>

  <form method="POST" style="display:inline-block;margin-right:12px"
        onsubmit="return confirm('4 cart_reminder templates Meta se DELETE honge. Confirm?')">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
    <button type="submit" style="padding:12px 24px;background:#dc3545;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer">
      🗑️ Step 1: Delete Old Marketing Templates
    </button>
  </form>

  <form method="POST" style="display:inline-block;margin-right:12px"
        onsubmit="return confirm('4 naye UTILITY templates create honge. Confirm?')">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
    <button type="submit" style="padding:12px 24px;background:#28a745;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer">
      ✅ Step 2: Create New Utility Templates
    </button>
  </form>
</div>

<div style="background:#fff3cd;border-radius:12px;padding:16px;font-family:sans-serif">
  <strong>⚠️ SECURITY:</strong> Is file ko use ke baad DELETE karo!<br>
  Hostinger File Manager → <code>public_html/admin/fix_cart_templates.php</code> → Delete
</div>

</div>
