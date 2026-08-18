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
$user_id = $user_data['user_id'];
$mark_all = $data['mark_all'] ?? false;
$notification_id = $data['notification_id'] ?? null;

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($mark_all) {
        // e.g. opening the notifications page — clears the bell badge.
        $stmt = $conn->prepare("UPDATE notifications SET seen = 1 WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
    } elseif ($notification_id) {
        $stmt = $conn->prepare("UPDATE notifications SET seen = 1 WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $notification_id, ':user_id' => $user_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'notification_id or mark_all is required']);
        exit;
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
