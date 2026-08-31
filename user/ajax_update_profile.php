<?php
/**
 * AJAX Profile Auto-Save Endpoint
 * Detects autofill or manual input changes and saves profile data
 * without requiring a full page reload.
 * 
 * Only updates fields that are currently empty in the database,
 * preventing unwanted overwrites of existing user data.
 */
include_once __DIR__ . '/../includes/session_setup.php';
include_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

// ── Auth Check ────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// ── Only accept POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ── CSRF Check ────────────────────────────────────────────────
$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

// ── Fetch current user data ───────────────────────────────────
$stmt = $conn->prepare("SELECT name, phone, address, city, state, country, zip_code FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// ── Map incoming fields ───────────────────────────────────────
$field_map = [
    'name'     => 'name',
    'phone'    => 'phone',
    'address'  => 'address',
    'city'     => 'city',
    'state'    => 'state',
    'country'  => 'country',
    'zip_code' => 'zip_code',
];

$updates = [];
$params = [];
$types = '';

$force_update = isset($_POST['force_update']) && $_POST['force_update'] === '1';

foreach ($field_map as $post_key => $db_col) {
    if (!isset($_POST[$post_key])) continue;
    
    $new_val = trim($_POST[$post_key]);
    if (empty($new_val)) continue; // Don't save empty values
    
    // Only update if DB field is empty OR force_update is set
    if (empty($current[$db_col]) || $force_update) {
        $updates[] = "$db_col = ?";
        $params[] = $new_val;
        $types .= 's';
    }
}

if (empty($updates)) {
    echo json_encode([
        'success' => true, 
        'message' => 'No fields needed updating',
        'updated_count' => 0
    ]);
    exit;
}

// ── Duplicate phone check ─────────────────────────────────────
if (in_array('phone', array_keys(array_filter($field_map, function($col) use ($updates) {
    return in_array("$col = ?", $updates);
})))) {
    $phone_idx = array_search('phone = ?', $updates);
    if ($phone_idx !== false) {
        $phone_val = $params[$phone_idx];
        $phone_clean = str_replace([' ', '-', '(', ')', '+'], '', $phone_val);
        if (strlen($phone_clean) > 5) {
            $dup_stmt = $conn->prepare(
                "SELECT id FROM users WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ? AND id != ?"
            );
            $dup_stmt->bind_param("si", $phone_clean, $user_id);
            $dup_stmt->execute();
            if ($dup_stmt->get_result()->num_rows > 0) {
                // Remove phone from updates (skip silently)
                unset($updates[$phone_idx]);
                unset($params[$phone_idx]);
                $types = substr_replace($types, '', $phone_idx, 1);
                $updates = array_values($updates);
                $params = array_values($params);
            }
            $dup_stmt->close();
        }
    }
}

if (empty($updates)) {
    echo json_encode([
        'success' => true, 
        'message' => 'No fields needed updating',
        'updated_count' => 0
    ]);
    exit;
}

// ── Execute update ────────────────────────────────────────────
$sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
$types .= 'i';
$params[] = $user_id;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$result = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

// Clear the needs_profile_update flag if profile is now complete
$check_stmt = $conn->prepare("SELECT phone, address FROM users WHERE id = ?");
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$updated_user = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if (!empty($updated_user['phone']) && !empty($updated_user['address'])) {
    unset($_SESSION['needs_profile_update']);
    try {
        require_once __DIR__ . '/../includes/GoogleProfileReminderService.php';
        (new GoogleProfileReminderService($conn))->markCompleted($user_id);
    } catch (\Throwable $e) {}
}

echo json_encode([
    'success' => $result,
    'message' => $result ? 'Profile updated successfully' : 'Update failed',
    'updated_count' => count($updates) - 1 // minus the WHERE id param
]);
