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
    echo json_encode(['success' => false, 'message' => 'post_id is required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id FROM saved_posts WHERE user_id = :user_id AND post_id = :post_id");
    $stmt->execute([':user_id' => $user_id, ':post_id' => $post_id]);

    if ($stmt->rowCount() > 0) {
        $stmt = $conn->prepare("DELETE FROM saved_posts WHERE user_id = :user_id AND post_id = :post_id");
        $stmt->execute([':user_id' => $user_id, ':post_id' => $post_id]);
        $saved = false;
    } else {
        $stmt = $conn->prepare("INSERT INTO saved_posts (user_id, post_id) VALUES (:user_id, :post_id)");
        $stmt->execute([':user_id' => $user_id, ':post_id' => $post_id]);
        $saved = true;
    }

    echo json_encode(['success' => true, 'saved' => $saved]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
