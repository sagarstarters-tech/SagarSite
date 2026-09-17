<?php
/**
 * One-Time Tool: Fix Cart Reminder Templates (Marketing → Utility)
 * Access: /admin/fix_cart_templates.php
 * DELETE THIS FILE after use!
 */
require_once 'admin_header.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Access denied.');
}

$ws = $conn->query("SELECT api_token, waba_id, phone_number_id FROM whatsapp_settings WHERE id = 1")->fetch_assoc();
$TOKEN    = trim($ws['api_token'] ?? '');
$WABA_ID  = trim($ws['waba_id'] ?? '');
$PHONE_ID = trim($ws['phone_number_id'] ?? '');

if (empty($TOKEN) || empty($WABA_ID)) {
    die('<div style="color:red;padding:20px">ERROR: API Token ya WABA ID missing. Admin - WhatsApp Settings mein set karo.</div>');
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

$current    = meta_call('GET', "https://graph.facebook.com/v21.0/{$WABA_ID}/message_templates?fields=name,category,status&limit=50", $TOKEN);
$cart_tpls  = array_filter($current['body']['data'] ?? [], fn($t) => strpos($t['name'], 'cart_reminder') !== false);
?>
<!DOCTYPE html>
<html>
<head>
<title>Fix Cart Templates</title>
<style>
body{font-family:sans-serif;padding:30px;background:#f4f6f9}
.card{background:#fff;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.1)}
h2{margin:0 0 16px;color:#1a1a2e}
.badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700}
.bm{background:#ffeeba;color:#856404}.bu{background:#d4edda;color:#155724}
.ba{background:#d4edda;color:#155724}.bp{background:#fff3cd;color:#856404}
table{width:100%;border-collapse:collapse}
th,td{padding:10px 14px;text-align:left;border-bottom:1px solid #eee}
th{background:#f8f9fa;font-weight:600}
.btn{display:inline-block;padding:12px 24px;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;border:none;text-decoration:none}
.bd{background:#dc3545;color:#fff}.bs{background:#28a745;color:#fff}.bi{background:#007bff;color:#fff}
.ok{color:#28a745}.er{color:#dc3545}
.warn{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:16px;margin-bottom:20px}
</style>
</head>
<body>

<div class="card">
  <h2>Cart Template Fix Tool</h2>
  <p>Marketing category templates delete karke Utility mein naye banayega.</p>
  <p><strong>WABA ID:</strong> <?=htmlspecialchars($WABA_ID)?> &nbsp;|&nbsp; <strong>Phone ID:</strong> <?=htmlspecialchars($PHONE_ID)?></p>
</div>

<div class="card">
  <h2>Current Cart Templates on Meta</h2>
  <?php if(empty($cart_tpls)): ?>
    <p>Koi cart_reminder template nahi mili — seedha Step 2 (Create) karo.</p>
  <?php else: ?>
  <table>
    <tr><th>Name</th><th>Category</th><th>Status</th></tr>
    <?php foreach($cart_tpls as $t): ?>
    <tr>
      <td><?=htmlspecialchars($t['name'])?></td>
      <td><span class="badge <?=strtoupper($t['category']??'')==='UTILITY'?'bu':'bm'?>"><?=htmlspecialchars($t['category']??'MARKETING')?></span></td>
      <td><span class="badge <?=strtoupper($t['status']??'')==='APPROVED'?'ba':'bp'?>"><?=htmlspecialchars($t['status']??'')?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<?php if(!empty($results)): ?>
<div class="card">
  <h2><?=$action==='delete'?'Delete Results':'Create Results'?></h2>
  <table>
    <tr><th>Template</th><th>HTTP</th><th>Result</th></tr>
    <?php foreach($results as $r): ?>
    <tr>
      <td><strong><?=htmlspecialchars($r['name'])?></strong></td>
      <td><?=$r['code']?></td>
      <td>
        <?php if(in_array($r['code'],[200,201])): ?>
          <span class="ok">OK - <?=$action==='delete'?'Deleted!':'Created! (Approval ~5 min)'?></span>
        <?php else: ?>
          <span class="er">ERROR: <?=htmlspecialchars(json_encode($r['body']))?></span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <h2>Actions</h2>
  <div class="warn">Pehle Step 1 (Delete), phir Step 2 (Create) karo.</div>
  <form method="POST" style="display:inline-block;margin-right:12px" onsubmit="return confirm('4 cart_reminder templates Meta se DELETE honge. Confirm?')">
    <input type="hidden" name="action" value="delete">
    <button class="btn bd">Step 1: Delete Old Marketing Templates</button>
  </form>
  <form method="POST" style="display:inline-block;margin-right:12px" onsubmit="return confirm('4 naye UTILITY templates create honge. Confirm?')">
    <input type="hidden" name="action" value="create">
    <button class="btn bs">Step 2: Create New Utility Templates</button>
  </form>
  <a href="manage_whatsapp_settings.php" class="btn bi">Back to WhatsApp Settings</a>
</div>

<div class="card" style="background:#fff3cd">
  <strong>SECURITY:</strong> Is file ko use ke baad DELETE karo!<br>
  Path: <code>admin/fix_cart_templates.php</code>
</div>
</body>
</html>
