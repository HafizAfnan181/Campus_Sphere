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
$post_id = $data['post_id'] ?? '';
$user_id = $user_data['user_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check if already liked
    $stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = :user_id AND post_id = :post_id");
    $stmt->execute([':user_id' => $user_id, ':post_id' => $post_id]);

    if ($stmt->rowCount() > 0) {
        // Unlike
        $stmt = $conn->prepare("DELETE FROM likes WHERE user_id = :user_id AND post_id = :post_id");
        $stmt->execute([':user_id' => $user_id, ':post_id' => $post_id]);
        $liked = false;
    } else {
        // Like
        $stmt = $conn->prepare("INSERT INTO likes (user_id, post_id) VALUES (:user_id, :post_id)");
        $stmt->execute([':user_id' => $user_id, ':post_id' => $post_id]);
        $liked = true;

        // Add notification
        $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = :post_id");
        $stmt->execute([':post_id' => $post_id]);
        $post_owner = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($post_owner['user_id'] != $user_id) {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, from_user_id, type, post_id, message) VALUES (:user_id, :from_user_id, 'like', :post_id, 'liked your post')");
            $stmt->execute([':user_id' => $post_owner['user_id'], ':from_user_id' => $user_id, ':post_id' => $post_id]);
        }
    }

    echo json_encode(['success' => true, 'liked' => $liked]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>