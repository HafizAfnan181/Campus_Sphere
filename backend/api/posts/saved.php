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

$user_id = $user_data['user_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        SELECT p.*, u.username, u.full_name, u.profile_pic,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = :current_user) as user_liked
        FROM saved_posts s
        JOIN posts p ON s.post_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE s.user_id = :user_id
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([':current_user' => $user_id, ':user_id' => $user_id]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'posts' => $posts]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
