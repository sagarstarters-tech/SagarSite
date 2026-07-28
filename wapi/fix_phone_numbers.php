<?php
/**
 * WAPI - One-time Phone Number Normalization Script
 * Adds +91 country code to Indian numbers that are missing it.
 *
 * SAFE: Dry-run first, then confirm to apply.
 * DELETE THIS FILE after use.
 */
require_once __DIR__ . '/config/config.php';

// ─── Simple auth: only allow if logged in as admin ───────────────────────────
session_start();
$db = Database::getInstance();

// Allow access only from CLI or if logged-in admin
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    if (empty($_SESSION['user_id'])) {
        die('❌ Not logged in. Please login to your dashboard first, then run this script.');
    }
    $role = $db->fetchColumn("SELECT role FROM users WHERE id = ?", [$_SESSION['user_id']]);
    if ($role !== 'admin') {
        die('❌ Admin access required.');
    }
}

$dryRun = !isset($_GET['apply']); // ?apply=1 to actually update

// ─── Normalization function ───────────────────────────────────────────────────
function normalizeIndianPhone(string $phone): string {
    $original = $phone;
    $phone    = trim($phone);

    // Already correct: +91XXXXXXXXXX (12-13 chars)
    if (preg_match('/^\+91[6-9]\d{9}$/', $phone)) {
        return $phone; // Nothing to do
    }

    // Has + but not +91 (some other country code) → leave it
    if (str_starts_with($phone, '+') && !str_starts_with($phone, '+91')) {
        return $phone;
    }

    // Remove any existing + sign
    $phone = ltrim($phone, '+');

    // Starts with 91 and followed by Indian mobile digit (10 digits after 91 = 12 total)
    if (preg_match('/^91([6-9]\d{9})$/', $phone, $m)) {
        return '+91' . $m[1]; // just add +
    }

    // Starts with 0 → old format like 09876543210
    if (preg_match('/^0([6-9]\d{9})$/', $phone, $m)) {
        return '+91' . $m[1];
    }

    // Plain 10-digit Indian mobile number
    if (preg_match('/^([6-9]\d{9})$/', $phone)) {
        return '+91' . $phone;
    }

    // Anything else (landline, international, etc.) → leave unchanged
    return $original;
}

// ─── Fetch all contacts ───────────────────────────────────────────────────────
$allContacts = $db->fetchAll("SELECT id, user_id, name, phone FROM contacts ORDER BY user_id, id");

$toUpdate = [];
foreach ($allContacts as $c) {
    $normalized = normalizeIndianPhone($c['phone']);
    if ($normalized !== $c['phone']) {
        $toUpdate[] = [
            'id'       => $c['id'],
            'name'     => $c['name'],
            'old'      => $c['phone'],
            'new'      => $normalized,
            'user_id'  => $c['user_id'],
        ];
    }
}

// ─── Apply if requested ───────────────────────────────────────────────────────
$applied = 0;
if (!$dryRun && !empty($toUpdate)) {
    foreach ($toUpdate as $row) {
        $db->update('contacts', ['phone' => $row['new']], 'id = ?', [$row['id']]);
        $applied++;
    }
}

// ─── Output ───────────────────────────────────────────────────────────────────
?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Phone Number Fix – WAPI</title>
<style>
  body { font-family: sans-serif; max-width: 900px; margin: 30px auto; padding: 0 20px; }
  h1   { color: #1a1a2e; }
  .badge-ok  { background:#d1fae5; color:#065f46; padding:2px 8px; border-radius:4px; font-size:12px; }
  .badge-new { background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:4px; font-size:12px; }
  table { width:100%; border-collapse:collapse; margin-top:20px; }
  th,td{ padding:8px 12px; text-align:left; border-bottom:1px solid #e5e7eb; font-size:13px; }
  th { background:#f9fafb; font-weight:600; }
  .alert-success { background:#d1fae5; border:1px solid #a7f3d0; padding:14px 18px; border-radius:8px; margin:16px 0; color:#065f46; }
  .alert-info    { background:#dbeafe; border:1px solid #93c5fd; padding:14px 18px; border-radius:8px; margin:16px 0; color:#1e40af; }
  .btn { display:inline-block; padding:10px 22px; background:#22c55e; color:#fff; border-radius:8px; text-decoration:none; font-weight:600; margin-top:16px; }
  .btn-warn { background:#ef4444; }
  del { color:#9ca3af; }
</style>
</head>
<body>

<h1>📱 Phone Number Normalizer</h1>

<?php if (!$dryRun && $applied > 0): ?>
  <div class="alert-success">
    ✅ <strong><?= $applied ?> contacts</strong> updated successfully! All numbers now have <code>+91</code> prefix.
    <br><strong>⚠️ Delete this file from the server after confirming everything is correct.</strong>
  </div>
<?php elseif (!$dryRun && $applied === 0): ?>
  <div class="alert-success">✅ All phone numbers are already in correct format. Nothing to update.</div>
<?php elseif ($dryRun): ?>
  <div class="alert-info">
    ℹ️ <strong>DRY RUN</strong> — No changes made yet. Review the list below, then click <strong>"Apply Changes"</strong> to update.
  </div>
<?php endif; ?>

<?php if ($dryRun && empty($toUpdate)): ?>
  <p>✅ All <strong><?= count($allContacts) ?></strong> phone numbers already have correct format. Nothing to change!</p>
<?php elseif (!empty($toUpdate)): ?>

<p>Found <strong><?= count($toUpdate) ?></strong> numbers to normalize (out of <?= count($allContacts) ?> total contacts).</p>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Contact Name</th>
      <th>Old Phone</th>
      <th>New Phone</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($toUpdate as $i => $row): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td><?= htmlspecialchars($row['name']) ?></td>
      <td><del><?= htmlspecialchars($row['old']) ?></del></td>
      <td><span class="badge-new">+91 added</span> <?= htmlspecialchars($row['new']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if ($dryRun): ?>
  <a href="?apply=1" class="btn" onclick="return confirm('Are you sure? This will update <?= count($toUpdate) ?> phone numbers in database.')">
    ✅ Apply Changes (Update <?= count($toUpdate) ?> numbers)
  </a>
<?php endif; ?>

<?php endif; ?>

</body>
</html>
