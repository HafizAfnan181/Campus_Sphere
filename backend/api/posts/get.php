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

    // is_saved needs the saved_posts table, and the ORDER BY needs the
    // pinned column — both added by backend/migrate.php. Try the full
    // query first; fall back a step at a time so the feed still loads on
    // an un-migrated install instead of erroring out.
    try {
        $stmt = $conn->prepare("
            SELECT p.*, u.username, u.full_name, u.profile_pic,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = :current_user) as user_liked,
            (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.id AND user_id = :current_user2) as is_saved
            FROM posts p
            JOIN users u ON p.user_id = u.id
            ORDER BY p.pinned DESC, p.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([':current_user' => $user_data['user_id'], ':current_user2' => $user_data['user_id']]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        try {
            $stmt = $conn->prepare("
                SELECT p.*, u.username, u.full_name, u.profile_pic,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = :current_user) as user_liked,
                0 as is_saved
                FROM posts p
                JOIN users u ON p.user_id = u.id
                ORDER BY p.pinned DESC, p.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([':current_user' => $user_data['user_id']]);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            // pinned column doesn't exist yet either — plain chronological feed.
            $stmt = $conn->prepare("
                SELECT p.*, u.username, u.full_name, u.profile_pic,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = :current_user) as user_liked,
                0 as is_saved
                FROM posts p
                JOIN users u ON p.user_id = u.id
                ORDER BY p.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([':current_user' => $user_data['user_id']]);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode(['success' => true, 'posts' => $posts]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>