<?php
/**
 * One-Time Tool: Create NEW Utility Cart Templates (different names)
 * Access: /admin/fix_cart_templates.php
 * DELETE THIS FILE after use!
 *
 * NOTE: Meta doesn't allow re-creating same-named templates with different
 * category for 30 days. So we create new templates with names: cart_util_01..04
 * and update DB settings to point to these new names.
 */
require_once 'admin_header.php';

if (!AuthMiddleware::isAdmin()) {
    die('Access denied.');
}

$ws = $conn->query("SELECT api_token, waba_id, phone_number_id FROM whatsapp_settings WHERE id = 1")->fetch_assoc();
$TOKEN    = trim($ws['api_token'] ?? '');
$WABA_ID  = trim($ws['waba_id'] ?? '');
$PHONE_ID = trim($ws['phone_number_id'] ?? '');

if (empty($TOKEN) || empty($WABA_ID)) {
    echo '<div style="color:red;padding:20px">ERROR: API Token ya WABA ID missing.</div>';
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

// NEW template names (different from old cart_reminder_01..04 to bypass Meta restriction)
$new_templates = [
    1 => [
        'name'         => 'cart_util_01',
        'body'         => "Hi {{1}}! You left items in your cart.\n\nItems: {{2}}\nCart Total: Rs.{{3}}\n\nComplete your order before stock runs out!",
        'body_example' => [['Rahul', '5 HP Starter, Float Switch', '5600']],
        'footer'       => "Sagar Starter's",
        'btn_text'     => 'Complete Order',
        'btn_url'      => 'https://www.sagarstarters.com/cart.php',
    ],
    2 => [
        'name'         => 'cart_util_02',
        'body'         => "Hi {{1}}! Your cart is still waiting.\n\nItems: {{2}}\nCart Total: Rs.{{3}}\n\nComplete your purchase today!",
        'body_example' => [['Rahul', '5 HP Starter, Float Switch', '5600']],
        'footer'       => "Sagar Starter's",
        'btn_text'     => 'Go to Cart',
        'btn_url'      => 'https://www.sagarstarters.com/cart.php',
    ],
    3 => [
        'name'         => 'cart_util_03',
        'body'         => "Hi {{1}}! Last chance reminder.\n\nItems: {{2}}\nCart Total: Rs.{{3}}\n\nYour cart will expire soon. Order now!",
        'body_example' => [['Rahul', '5 HP Starter, Float Switch', '5600']],
        'footer'       => "Sagar Starter's",
        'btn_text'     => 'Buy Now',
        'btn_url'      => 'https://www.sagarstarters.com/cart.php',
    ],
    4 => [
        'name'         => 'cart_util_04',
        'body'         => "Hi {{1}}! We saved your cart.\n\nItems: {{2}}\nCart Total: Rs.{{3}}\n\nComplete your order and we will ship immediately!",
        'body_example' => [['Rahul', '5 HP Starter, Float Switch', '5600']],
        'footer'       => "Sagar Starter's",
        'btn_text'     => 'Order Now',
        'btn_url'      => 'https://www.sagarstarters.com/cart.php',
    ],
];

$action  = $_POST['action'] ?? '';
$results = [];

// Action: Create new UTILITY templates
if ($action === 'create_utility') {
    foreach ($new_templates as $level => $tpl) {
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
        $results[] = [
            'action' => 'CREATE',
            'name'   => $tpl['name'],
            'level'  => $level,
            'code'   => $r['code'],
            'body'   => $r['body'],
        ];
    }
}

// Action: Update DB settings to use new template names
if ($action === 'update_db') {
    $updated = 0;
    foreach ($new_templates as $level => $tpl) {
        $key = "meta_template_{$level}";
        $val = $tpl['name'];
        // Update abandoned_cart_settings
        $stmt = $conn->prepare("INSERT INTO abandoned_cart_settings (setting_key, setting_value)
                                VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE setting_value = ?");
        if ($stmt) {
            $stmt->bind_param('sss', $key, $val, $val);
            $stmt->execute();
            $stmt->close();
            $updated++;
        }
    }
    $results[] = [
        'action' => 'DB_UPDATE',
        'name'   => 'abandoned_cart_settings',
        'level'  => 0,
        'code'   => $updated === 4 ? 200 : 500,
        'body'   => ['updated_rows' => $updated, 'message' => "$updated settings updated in DB"],
    ];
}

// Fetch all templates from Meta for display
$current   = meta_call('GET', "https://graph.facebook.com/v21.0/{$WABA_ID}/message_templates?fields=name,category,status&limit=100", $TOKEN);
$all_tpls  = $current['body']['data'] ?? [];
$cart_tpls = array_filter($all_tpls, fn($t) => strpos($t['name'], 'cart_') !== false);

// Fetch current DB settings
$db_settings = [];
$dsr = $conn->query("SELECT setting_key, setting_value FROM abandoned_cart_settings WHERE setting_key LIKE 'meta_template_%'");
if ($dsr) {
    while ($row = $dsr->fetch_assoc()) {
        $db_settings[$row['setting_key']] = $row['setting_value'];
    }
}

$csrf = csrf_token();
?>
<div style="padding:30px;font-family:sans-serif;max-width:950px">

<div style="background:#fff;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.1)">
  <h2 style="margin:0 0 8px">🛠️ Cart Template Fix Tool v2</h2>
  <p style="color:#666;margin:0 0 12px">Meta ki 30-day restriction ki wajah se <em>cart_reminder_01..04</em> naye naam se nahi ban sakte.<br>
  Isliye <strong>cart_util_01..04</strong> (Utility category) banate hain aur DB update karte hain.</p>
  <p><strong>WABA ID:</strong> <?=htmlspecialchars($WABA_ID)?> &nbsp;|&nbsp; <strong>Phone ID:</strong> <?=htmlspecialchars($PHONE_ID)?></p>
</div>

<!-- Current Templates on Meta -->
<div style="background:#fff;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.1)">
  <h2 style="margin:0 0 12px">📋 Cart Templates on Meta</h2>
  <?php if(empty($cart_tpls)): ?>
    <p style="color:#888">Koi cart template nahi mili.</p>
  <?php else: ?>
  <table style="width:100%;border-collapse:collapse">
    <tr style="background:#f8f9fa">
      <th style="padding:9px;text-align:left">Name</th>
      <th style="padding:9px;text-align:left">Category</th>
      <th style="padding:9px;text-align:left">Status</th>
    </tr>
    <?php foreach($cart_tpls as $t):
      $cat=$t['category']??''; $sts=$t['status']??'';
      $cbg=strtoupper($cat)==='UTILITY'?'#d4edda':'#ffeeba';
      $cfg=strtoupper($cat)==='UTILITY'?'#155724':'#856404';
      $sbg=strtoupper($sts)==='APPROVED'?'#d4edda':'#fff3cd';
      $sfg=strtoupper($sts)==='APPROVED'?'#155724':'#856404';
    ?>
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:9px"><?=htmlspecialchars($t['name'])?></td>
      <td style="padding:9px"><span style="background:<?=$cbg?>;color:<?=$cfg?>;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700"><?=strtoupper($cat)?></span></td>
      <td style="padding:9px"><span style="background:<?=$sbg?>;color:<?=$sfg?>;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700"><?=strtoupper($sts)?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<!-- DB Settings -->
<div style="background:#fff;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.1)">
  <h2 style="margin:0 0 12px">🗄️ Current DB Template Settings</h2>
  <?php if(empty($db_settings)): ?>
    <p style="color:#888">meta_template_1..4 settings DB mein nahi hain — Step 2 ke baad yahan show hongi.</p>
  <?php else: ?>
  <table style="width:100%;border-collapse:collapse">
    <tr style="background:#f8f9fa"><th style="padding:9px;text-align:left">Key</th><th style="padding:9px;text-align:left">Template Name</th></tr>
    <?php foreach($db_settings as $k => $v): ?>
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:9px"><code><?=htmlspecialchars($k)?></code></td>
      <td style="padding:9px"><strong><?=htmlspecialchars($v)?></strong></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<!-- Results -->
<?php if(!empty($results)): ?>
<div style="background:#fff;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.1)">
  <h2 style="margin:0 0 12px">📊 Results</h2>
  <table style="width:100%;border-collapse:collapse">
    <tr style="background:#f8f9fa"><th style="padding:9px;text-align:left">Name</th><th style="padding:9px;text-align:left">HTTP</th><th style="padding:9px;text-align:left">Result</th></tr>
    <?php foreach($results as $r): ?>
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:9px"><strong><?=htmlspecialchars($r['name'])?></strong></td>
      <td style="padding:9px"><?=$r['code']?></td>
      <td style="padding:9px">
        <?php if(in_array($r['code'],[200,201])): ?>
          <span style="color:#28a745;font-weight:700">✅ <?=$r['action']==='CREATE'?'Created! (Meta approval ~5-10 min)':($r['action']==='DB_UPDATE'?'DB Updated!':'Done!')?></span>
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
  <h2 style="margin:0 0 12px">⚡ 2-Step Process</h2>

  <div style="background:#e8f5e9;border:1px solid #4caf50;border-radius:8px;padding:14px;margin-bottom:16px">
    <strong>Step 1:</strong> Meta par <code>cart_util_01..04</code> (UTILITY) templates create karo<br>
    <strong>Step 2:</strong> Database update karo taaki service in naye templates use kare
  </div>

  <form method="POST" style="display:inline-block;margin-right:12px"
        onsubmit="return confirm('cart_util_01..04 UTILITY templates Meta par create honge. Confirm?')">
    <input type="hidden" name="action" value="create_utility">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
    <button type="submit" style="padding:12px 28px;background:#28a745;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer">
      ✅ Step 1: Create Utility Templates on Meta
    </button>
  </form>

  <form method="POST" style="display:inline-block"
        onsubmit="return confirm('DB settings update honge: meta_template_1..4 = cart_util_01..04. Confirm?')">
    <input type="hidden" name="action" value="update_db">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
    <button type="submit" style="padding:12px 28px;background:#007bff;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer">
      🗄️ Step 2: Update DB Settings
    </button>
  </form>
</div>

<div style="background:#fff3cd;border-radius:12px;padding:16px">
  <strong>⚠️ Security:</strong> Dono steps hone ke baad is file ko DELETE karo!<br>
  Hostinger → <code>public_html/admin/fix_cart_templates.php</code> → Delete
</div>
</div>
