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
$request_id = $data['request_id'] ?? '';
$action = $data['action'] ?? ''; // 'accept' or 'reject'

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($action === 'accept') {
        $stmt = $conn->prepare("UPDATE friends SET status = 'accepted' WHERE id = :id AND receiver_id = :user_id");
        $stmt->execute([':id' => $request_id, ':user_id' => $user_data['user_id']]);

        // Get sender info for notification
        $stmt = $conn->prepare("SELECT sender_id FROM friends WHERE id = :id");
        $stmt->execute([':id' => $request_id]);
        $sender = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("INSERT INTO notifications (user_id, from_user_id, type, message) VALUES (:user_id, :from_user_id, 'friend_accepted', 'accepted your friend request')");
        $stmt->execute([':user_id' => $sender['sender_id'], ':from_user_id' => $user_data['user_id']]);
    } else {
        // Reject/cancel: delete the row outright (rather than marking it
        // 'rejected') so either person can send a fresh friend request
        // later instead of being permanently blocked by a leftover row.
        $stmt = $conn->prepare("DELETE FROM friends WHERE id = :id AND (sender_id = :user_id OR receiver_id = :user_id2)");
        $stmt->execute([':id' => $request_id, ':user_id' => $user_data['user_id'], ':user_id2' => $user_data['user_id']]);
    }

    echo json_encode(['success' => true, 'message' => "Friend request $action"]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>