<?php
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
$email = trim($data['email'] ?? '');
$code = trim($data['code'] ?? '');

if (empty($email) || empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Email and code are required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND verification_code = :code");
    $stmt->execute([':email' => $email, ':code' => $code]);

    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET email_verified = 1, verification_code = NULL WHERE email = :email");
    $stmt->execute([':email' => $email]);

    echo json_encode(['success' => true, 'message' => 'Email verified successfully! You can now login.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>