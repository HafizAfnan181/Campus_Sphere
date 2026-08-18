<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../config/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$full_name = trim($data['full_name'] ?? '');

// Validation
if (empty($username) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

// Only letters, numbers, underscores in username — keeps URLs/mentions clean
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Username must be 3-20 characters (letters, numbers, underscore only)']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check username and email SEPARATELY so the message is specific
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }

    // ============================================================
    // Email verification system removed on purpose — no SMTP/email
    // dependency anymore, so registration never gets blocked by mail
    // delivery issues. A 6-digit code is still generated (kept for the
    // existing verify-email screen/UX), but it's returned directly in
    // this response instead of being emailed, and the frontend carries
    // it straight into the verify page automatically.
    // ============================================================
    $verification_code = rand(100000, 999999);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO users (username, email, password, full_name, verification_code)
         VALUES (:username, :email, :password, :full_name, :verification_code)"
    );
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password' => $hashed_password,
        ':full_name' => $full_name,
        ':verification_code' => $verification_code
    ]);

    $new_user_id = $conn->lastInsertId();

    // NOTE: We used to insert a fresh "welcome post" here under $new_user_id
    // on every registration. That was the bug — it made the welcome message
    // appear to have been posted BY the new user themselves, under their own
    // name. There is now exactly one shared welcome post, owned by the
    // "CampusSphere Community" system account (created once — see
    // backend/migration.sql), which every user already sees in their normal
    // feed via posts/get.php. Nothing needs to be created here anymore.

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful!',
        'user_id' => $new_user_id,
        'verification_code' => $verification_code
    ]);

} catch (PDOException $e) {
    error_log('register.php DB error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => APP_DEBUG ? ('Database error: ' . $e->getMessage()) : 'Something went wrong. Please try again.'
    ]);
}
?>
