<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
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

$receiver_id = $_GET['receiver_id'] ?? '';
$user_id = $user_data['user_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get messages
    $stmt = $conn->prepare("
        SELECT m.*, u.username, u.profile_pic 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE (m.sender_id = :user_id AND m.receiver_id = :receiver_id) 
           OR (m.sender_id = :receiver_id2 AND m.receiver_id = :user_id2) 
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([
        ':user_id' => $user_id,
        ':receiver_id' => $receiver_id,
        ':receiver_id2' => $receiver_id,
        ':user_id2' => $user_id
    ]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark messages as seen
    $stmt = $conn->prepare("UPDATE messages SET seen = 1 WHERE sender_id = :sender_id AND receiver_id = :receiver_id AND seen = 0");
    $stmt->execute([':sender_id' => $receiver_id, ':receiver_id' => $user_id]);

    echo json_encode(['success' => true, 'messages' => $messages]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>