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
$content = trim($data['content'] ?? '');
$user_id = $user_data['user_id'];

if (empty($post_id)) {
    echo json_encode(['success' => false, 'message' => 'Post ID is required']);
    exit;
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Post content cannot be empty']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Make sure the post exists AND belongs to the logged-in user —
    // never let a user edit someone else's post.
    $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = :id");
    $stmt->execute([':id' => $post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }

    if ($post['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'You can only edit your own posts']);
        exit;
    }

    // updated_at may not exist on older installs of the table — try with it
    // first, fall back gracefully so this still works without the migration.
    try {
        $stmt = $conn->prepare("UPDATE posts SET content = :content, updated_at = NOW() WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':content' => $content, ':id' => $post_id, ':user_id' => $user_id]);
    } catch (PDOException $e) {
        $stmt = $conn->prepare("UPDATE posts SET content = :content WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':content' => $content, ':id' => $post_id, ':user_id' => $user_id]);
    }

    echo json_encode(['success' => true, 'message' => 'Post updated successfully']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
