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
$notification_id = $data['notification_id'] ?? '';
$clear_all = $data['clear_all'] ?? false;
$user_id = $user_data['user_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($clear_all) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        echo json_encode(['success' => true, 'message' => 'All notifications cleared']);
        exit;
    }

    if (empty($notification_id)) {
        echo json_encode(['success' => false, 'message' => 'notification_id is required']);
        exit;
    }

    // Only ever delete the logged-in user's OWN notification.
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $notification_id, ':user_id' => $user_id]);

    echo json_encode(['success' => true, 'message' => 'Notification deleted']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
