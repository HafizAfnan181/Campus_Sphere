<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../config/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id, username, email, password, full_name, profile_pic, email_verified, verification_code FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }

    if (!$user['email_verified']) {
        // Password was correct, so identity is already proven — safe to hand
        // back their pending verification code here (no email involved).
        echo json_encode([
            'success' => false,
            'message' => 'Please verify your account first',
            'needs_verification' => true,
            'email' => $user['email'],
            'verification_code' => $user['verification_code']
        ]);
        exit;
    }

    // Generate JWT token
    $token = JWT::generateToken($user['id'], $user['username']);

    // Update online status
    $stmt = $conn->prepare("UPDATE users SET online_status = 1, last_seen = NOW() WHERE id = :id");
    $stmt->execute([':id' => $user['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'profile_pic' => $user['profile_pic']
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>