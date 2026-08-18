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

if (empty($post_id)) {
    echo json_encode(['success' => false, 'message' => 'Post ID is required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Confirm ownership before deleting anything.
    $stmt = $conn->prepare("SELECT user_id, image FROM posts WHERE id = :id");
    $stmt->execute([':id' => $post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }

    if ($post['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'You can only delete your own posts']);
        exit;
    }

    // Likes, comments (and notifications tied to the post via FK) cascade
    // automatically at the DB level — see backend/database.sql.
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $post_id, ':user_id' => $user_id]);

    // Clean up the uploaded image file from disk, if any.
    if (!empty($post['image'])) {
        $image_file = '../../' . $post['image'];
        if (file_exists($image_file)) {
            @unlink($image_file);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Post deleted successfully']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
