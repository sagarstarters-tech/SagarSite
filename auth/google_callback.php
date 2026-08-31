<?php
require_once __DIR__ . '/../includes/session_setup.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/cart_functions.php';

if (empty($global_settings['google_login_enabled']) || $global_settings['google_login_enabled'] !== '1') {
    $_SESSION['error'] = 'Google Login is disabled.';
    header("Location: ../user/login.php");
    exit;
}

$client_id = $global_settings['google_client_id'] ?? '';
$client_secret = $global_settings['google_client_secret'] ?? '';

if (empty($client_id) || empty($client_secret)) {
    $_SESSION['error'] = 'Google Login configuration is incomplete.';
    header("Location: ../user/login.php");
    exit;
}

// Check for errors from Google
if (isset($_GET['error'])) {
    $_SESSION['error'] = 'Google Sign-In failed: ' . htmlspecialchars($_GET['error']);
    header("Location: ../user/login.php");
    exit;
}

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['state']) || !isset($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
        $_SESSION['error'] = 'Invalid state parameter. Possible CSRF attack.';
        header("Location: ../user/login.php");
        exit;
    }
    unset($_SESSION['google_oauth_state']); // consumed

    if (!isset($_GET['code'])) {
        $_SESSION['error'] = 'Authorization code not received.';
        header("Location: ../user/login.php");
        exit;
    }

    // Generate the absolute redirect URI (must match exactly with redirect_uri in google_redirect.php)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = defined('SITE_URL') ? SITE_URL : '';
    $site_base = (strpos($baseUrl, 'http') === 0) ? rtrim($baseUrl, '/') : ($protocol . '://' . $host . rtrim($baseUrl, '/'));
    $redirect_uri = $site_base . '/auth/google_callback.php';

    // Exchange code for token
    $token_url = 'https://oauth2.googleapis.com/token';
    $post_data = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code',
        'code' => $_GET['code']
    ];

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $token_info = json_decode($response, true);

    if ($http_code !== 200 || !isset($token_info['access_token'])) {
        $_SESSION['error'] = 'Failed to obtain access token from Google.';
        header("Location: ../user/login.php");
        exit;
    }

    $access_token = $token_info['access_token'];

    // Get user profile info
    $profile_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init($profile_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
    $profile_res = curl_exec($ch);
    curl_close($ch);

    $google_user = json_decode($profile_res, true);
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    // Handle Google One Tap token
    $id_token = $_POST['credential'];
    
    // Verify token with Google's tokeninfo endpoint
    $verify_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $id_token;
    $ch = curl_init($verify_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $verify_res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $token_data = json_decode($verify_res, true);

    if ($http_code !== 200 || !isset($token_data['aud']) || $token_data['aud'] !== $client_id) {
        $_SESSION['error'] = 'Invalid Google One Tap token.';
        header("Location: ../user/login.php");
        exit;
    }

    $google_user = [
        'id' => $token_data['sub'],
        'email' => $token_data['email'],
        'name' => $token_data['name'] ?? 'Google User',
        'picture' => $token_data['picture'] ?? ''
    ];
} else {
    header("Location: ../user/login.php");
    exit;
}

if (!isset($google_user['id']) || !isset($google_user['email'])) {
    $_SESSION['error'] = 'Failed to fetch user profile from Google.';
    header("Location: ../user/login.php");
    exit;
}

$google_id = $conn->real_escape_string($google_user['id']);
$email = $conn->real_escape_string($google_user['email']);
$name = $conn->real_escape_string($google_user['name'] ?? 'Google User');
$avatar = $conn->real_escape_string($google_user['picture'] ?? '');

// Check if user exists
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // User exists
    $user = $result->fetch_assoc();
    
    // Security check: Protect Admin
    if ($user['role'] === 'admin') {
        $_SESSION['error'] = 'Administrators must log in using their email and password.';
        header("Location: ../user/login.php");
        exit;
    }
    
    // Update Google ID and Avatar if missing or changed, set verified
    $update_stmt = $conn->prepare("UPDATE users SET google_id = ?, google_email = ?, google_avatar = ?, auth_provider = 'google', is_verified = 1 WHERE id = ?");
    $update_stmt->bind_param("sssi", $google_id, $email, $avatar, $user['id']);
    $update_stmt->execute();
    
    // Log them in
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['profile_photo'] = $user['profile_photo'] ?: $avatar; // Use existing photo if set, else Google avatar
    
    // Check if profile is incomplete
    if (empty($user['phone']) || empty($user['address'])) {
        $_SESSION['needs_profile_update'] = true;
        try {
            require_once __DIR__ . '/../includes/GoogleProfileReminderService.php';
            (new GoogleProfileReminderService($conn))->trackGoogleLogin($user['id'], $email, $user['name'] ?: $name);
        } catch (\Throwable $e) {}
    } else {
        try {
            require_once __DIR__ . '/../includes/GoogleProfileReminderService.php';
            (new GoogleProfileReminderService($conn))->markCompleted($user['id']);
        } catch (\Throwable $e) {}
    }
    
    // Load saved cart from DB
    load_cart_from_db($conn, $user['id']);
    
    header("Location: ../index.php");
    exit;
} else {
    // User does not exist, create new account
    // Generate a strong random password placeholder
    $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $empty = '';
    
    $insert_stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, address, city, state, country, zip_code, google_id, google_email, google_avatar, auth_provider, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'google', 'user', 1)");
    $insert_stmt->bind_param("ssssssssssss", $name, $email, $random_password, $empty, $empty, $empty, $empty, $empty, $empty, $google_id, $email, $avatar);
    
    if ($insert_stmt->execute()) {
        $new_id = $insert_stmt->insert_id;
        
        // Log them in
        $_SESSION['user_id'] = $new_id;
        $_SESSION['name'] = $name;
        $_SESSION['role'] = 'user';
        $_SESSION['profile_photo'] = $avatar;
        $_SESSION['needs_profile_update'] = true; // New Google users always need to complete profile
        
        try {
            require_once __DIR__ . '/../includes/GoogleProfileReminderService.php';
            (new GoogleProfileReminderService($conn))->trackGoogleLogin($new_id, $email, $name);
        } catch (\Throwable $e) {}
        
        // Sync any guest cart to the new user's DB record
        sync_cart_to_db($conn);
        
        $_SESSION['success'] = 'Account created successfully with Google!';
        header("Location: ../index.php");
        exit;
    } else {
        $_SESSION['error'] = 'Failed to create an account through Google.';
        header("Location: ../user/login.php");
        exit;
    }
}
?>
