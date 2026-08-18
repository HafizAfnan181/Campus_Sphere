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
$sender_id = $user_data['user_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check existing request
    $stmt = $conn->prepare("SELECT id FROM friends WHERE (sender_id = :sender AND receiver_id = :receiver) OR (sender_id = :receiver2 AND receiver_id = :sender2)");
    $stmt->execute([':sender' => $sender_id, ':receiver' => $receiver_id, ':receiver2' => $receiver_id, ':sender2' => $sender_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Friend request already exists']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO friends (sender_id, receiver_id) VALUES (:sender_id, :receiver_id)");
    $stmt->execute([':sender_id' => $sender_id, ':receiver_id' => $receiver_id]);
    $friend_request_id = $conn->lastInsertId();

    // Notification — related_id links back to this friend request row so
    // the notification itself can carry working Accept/Reject buttons.
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, from_user_id, type, related_id, message) VALUES (:user_id, :from_user_id, 'friend_request', :related_id, 'sent you a friend request')");
        $stmt->execute([':user_id' => $receiver_id, ':from_user_id' => $sender_id, ':related_id' => $friend_request_id]);
    } catch (PDOException $e) {
        // related_id column missing on an un-migrated install — still record the notification.
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, from_user_id, type, message) VALUES (:user_id, :from_user_id, 'friend_request', 'sent you a friend request')");
        $stmt->execute([':user_id' => $receiver_id, ':from_user_id' => $sender_id]);
    }

    echo json_encode(['success' => true, 'message' => 'Friend request sent']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>