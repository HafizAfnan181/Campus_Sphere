<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
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

// GET comments
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $post_id = $_GET['post_id'] ?? '';
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT c.*, u.username, u.full_name, u.profile_pic 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.post_id = :post_id 
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([':post_id' => $post_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'comments' => $comments]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// POST comment (create / edit / delete — decided by the "action" field so
// existing calls that only send {post_id, comment} keep working as "create")
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'create';
$user_id = $user_data['user_id'];

// EDIT comment
if ($action === 'edit') {
    $comment_id = $data['comment_id'] ?? '';
    $comment = trim($data['comment'] ?? '');

    if (empty($comment)) {
        echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
        exit;
    }

    try {
        $db = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT user_id FROM comments WHERE id = :id");
        $stmt->execute([':id' => $comment_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Comment not found']);
            exit;
        }
        if ($existing['user_id'] != $user_id) {
            echo json_encode(['success' => false, 'message' => 'You can only edit your own comments']);
            exit;
        }

        try {
            $stmt = $conn->prepare("UPDATE comments SET comment = :comment, updated_at = NOW() WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':comment' => $comment, ':id' => $comment_id, ':user_id' => $user_id]);
        } catch (PDOException $e) {
            $stmt = $conn->prepare("UPDATE comments SET comment = :comment WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':comment' => $comment, ':id' => $comment_id, ':user_id' => $user_id]);
        }

        echo json_encode(['success' => true, 'message' => 'Comment updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// DELETE comment
if ($action === 'delete') {
    $comment_id = $data['comment_id'] ?? '';

    try {
        $db = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT user_id FROM comments WHERE id = :id");
        $stmt->execute([':id' => $comment_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Comment not found']);
            exit;
        }
        if ($existing['user_id'] != $user_id) {
            echo json_encode(['success' => false, 'message' => 'You can only delete your own comments']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM comments WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $comment_id, ':user_id' => $user_id]);

        echo json_encode(['success' => true, 'message' => 'Comment deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// CREATE comment (default)
$post_id = $data['post_id'] ?? '';
$comment = trim($data['comment'] ?? '');

if (empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("INSERT INTO comments (user_id, post_id, comment) VALUES (:user_id, :post_id, :comment)");
    $stmt->execute([':user_id' => $user_id, ':post_id' => $post_id, ':comment' => $comment]);

    // Add notification
    $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = :post_id");
    $stmt->execute([':post_id' => $post_id]);
    $post_owner = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($post_owner['user_id'] != $user_id) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, from_user_id, type, post_id, message) VALUES (:user_id, :from_user_id, 'comment', :post_id, :message)");
        $stmt->execute([
            ':user_id' => $post_owner['user_id'],
            ':from_user_id' => $user_id,
            ':post_id' => $post_id,
            ':message' => 'commented on your post'
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Comment added successfully']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>