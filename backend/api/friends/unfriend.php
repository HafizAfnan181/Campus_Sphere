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
$friend_id = $data['friend_id'] ?? '';
$user_id = $user_data['user_id'];

if (empty($friend_id)) {
    echo json_encode(['success' => false, 'message' => 'friend_id is required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Delete the friendship row in either direction (sender/receiver), but
    // only if it was actually an accepted friendship. Deleting (rather than
    // just changing status) lets the two users send a fresh friend request
    // to each other again later.
    $stmt = $conn->prepare("
        DELETE FROM friends
        WHERE status = 'accepted'
        AND ((sender_id = :user_id AND receiver_id = :friend_id)
             OR (sender_id = :friend_id2 AND receiver_id = :user_id2))
    ");
    $stmt->execute([
        ':user_id' => $user_id,
        ':friend_id' => $friend_id,
        ':friend_id2' => $friend_id,
        ':user_id2' => $user_id
    ]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'You are not friends with this user']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Friend removed']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
