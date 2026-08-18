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

try {
    $db = new Database();
    $conn = $db->getConnection();

    // related_id (added by backend/migrate.php) links a 'friend_request'
    // notification back to its row in `friends`, so we can show its LIVE
    // status (pending/accepted/rejected) — not just whether the
    // notification itself has been "seen". That's what decides whether
    // Accept/Reject buttons should still show on this notification.
    try {
        $stmt = $conn->prepare("
            SELECT n.*, u.username, u.full_name, u.profile_pic,
            f.status as friend_request_status
            FROM notifications n
            JOIN users u ON n.from_user_id = u.id
            LEFT JOIN friends f ON n.type = 'friend_request' AND f.id = n.related_id
            WHERE n.user_id = :user_id
            ORDER BY n.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([':user_id' => $user_data['user_id']]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // related_id column missing on an un-migrated install — fall back
        // without it so notifications still load.
        $stmt = $conn->prepare("
            SELECT n.*, u.username, u.full_name, u.profile_pic
            FROM notifications n
            JOIN users u ON n.from_user_id = u.id
            WHERE n.user_id = :user_id
            ORDER BY n.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([':user_id' => $user_data['user_id']]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = :user_id AND seen = 0");
    $stmt->execute([':user_id' => $user_data['user_id']]);
    $unread = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];

    echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
