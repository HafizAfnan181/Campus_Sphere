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
$target_id = $data['user_id'] ?? '';
$follower_id = $user_data['user_id'];

if (empty($target_id)) {
    echo json_encode(['success' => false, 'message' => 'user_id is required']);
    exit;
}

if ($target_id == $follower_id) {
    echo json_encode(['success' => false, 'message' => "You can't follow yourself"]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id FROM follows WHERE follower_id = :follower_id AND following_id = :following_id");
    $stmt->execute([':follower_id' => $follower_id, ':following_id' => $target_id]);

    if ($stmt->rowCount() > 0) {
        // Already following -> unfollow
        $stmt = $conn->prepare("DELETE FROM follows WHERE follower_id = :follower_id AND following_id = :following_id");
        $stmt->execute([':follower_id' => $follower_id, ':following_id' => $target_id]);
        $following = false;
    } else {
        $stmt = $conn->prepare("INSERT INTO follows (follower_id, following_id) VALUES (:follower_id, :following_id)");
        $stmt->execute([':follower_id' => $follower_id, ':following_id' => $target_id]);
        $following = true;

        // Notification is best-effort — if the 'follow' type isn't in the
        // notifications enum yet (older DB), don't let that break the follow.
        try {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, from_user_id, type, message) VALUES (:user_id, :from_user_id, 'follow', 'started following you')");
            $stmt->execute([':user_id' => $target_id, ':from_user_id' => $follower_id]);
        } catch (PDOException $e) {
            // ignore — see backend/migration.sql to enable follow notifications
        }
    }

    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM follows WHERE following_id = :id");
    $stmt->execute([':id' => $target_id]);
    $followers_count = $stmt->fetch(PDO::FETCH_ASSOC)['c'];

    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM follows WHERE follower_id = :id");
    $stmt->execute([':id' => $target_id]);
    $following_count = $stmt->fetch(PDO::FETCH_ASSOC)['c'];

    echo json_encode([
        'success' => true,
        'following' => $following,
        'followers_count' => $followers_count,
        'following_count' => $following_count
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
