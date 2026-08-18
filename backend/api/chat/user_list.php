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

$user_id = $user_data['user_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get friends list
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.full_name, u.profile_pic, u.online_status,
        (SELECT message FROM messages WHERE (sender_id = u.id AND receiver_id = :user_id) OR (sender_id = :user_id2 AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = :user_id3 AND seen = 0) as unread_count
        FROM users u
        JOIN friends f ON (f.sender_id = u.id OR f.receiver_id = u.id)
        WHERE (f.sender_id = :user_id4 OR f.receiver_id = :user_id5)
        AND f.status = 'accepted'
        AND u.id != :user_id6
    ");
    $stmt->execute([
        ':user_id' => $user_id,
        ':user_id2' => $user_id,
        ':user_id3' => $user_id,
        ':user_id4' => $user_id,
        ':user_id5' => $user_id,
        ':user_id6' => $user_id
    ]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'users' => $users]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>