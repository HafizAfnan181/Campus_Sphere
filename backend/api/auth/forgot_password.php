<?php
// ============================================================
// FORGOT PASSWORD API — no email involved.
// User proves ownership by supplying BOTH the correct username
// AND the correct email together. If they match the same account,
// the password is updated immediately in this same request.
// Usage: POST { "username": "...", "email": "...", "new_password": "..." }
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$new_password = $data['new_password'] ?? '';

if (empty($username) || empty($email) || empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'Username, email, and new password are all required']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Username AND email must belong to the SAME account
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username AND email = :email");
    $stmt->execute([':username' => $username, ':email' => $email]);

    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Username or Email not matched']);
        exit;
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
    $stmt->execute([':password' => $hashed_password, ':id' => $user['id']]);

    echo json_encode(['success' => true, 'message' => 'Password updated successfully! You can now login.']);

} catch (PDOException $e) {
    error_log('forgot_password.php DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
?>
