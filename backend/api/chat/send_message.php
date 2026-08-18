<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/jwt.php';

$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
$user_data = JWT::validateToken($token);

if (!$user_data) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$receiver_id = $data['receiver_id'] ?? '';
$message = trim($data['message'] ?? '');
$sender_id = $user_data['user_id'];

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (:sender_id, :receiver_id, :message)");
    $stmt->execute([':sender_id' => $sender_id, ':receiver_id' => $receiver_id, ':message' => $message]);

    echo json_encode([
        'success' => true,
        'message' => 'Message sent',
        'message_id' => $conn->lastInsertId()
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>