<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
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

$db = new Database();
$conn = $db->getConnection();

// GET profile
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = $_GET['user_id'] ?? $user_data['user_id'];
    
    // cover_photo needs the column added by backend/migrate.php — fall back
    // gracefully so this endpoint still works before that's run.
    try {
        $stmt = $conn->prepare("SELECT id, username, email, full_name, bio, profile_pic, cover_photo, online_status, last_seen, created_at FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $conn->prepare("SELECT id, username, email, full_name, bio, profile_pic, online_status, last_seen, created_at FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) $user['cover_photo'] = null;
    }

    // Get posts count
    $stmt = $conn->prepare("SELECT COUNT(*) as post_count FROM posts WHERE user_id = :id");
    $stmt->execute([':id' => $user_id]);
    $post_count = $stmt->fetch(PDO::FETCH_ASSOC)['post_count'];

    // Get friends count
    $stmt = $conn->prepare("SELECT COUNT(*) as friend_count FROM friends WHERE (sender_id = :id OR receiver_id = :id2) AND status = 'accepted'");
    $stmt->execute([':id' => $user_id, ':id2' => $user_id]);
    $friend_count = $stmt->fetch(PDO::FETCH_ASSOC)['friend_count'];

    // Followers = people who follow this user. Following = people this user follows.
    $following_count = 0;
    $followers_count = 0;
    $is_following = false;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM follows WHERE follower_id = :id");
        $stmt->execute([':id' => $user_id]);
        $following_count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['c'];

        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM follows WHERE following_id = :id");
        $stmt->execute([':id' => $user_id]);
        $followers_count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['c'];

        if ($user_id != $user_data['user_id']) {
            $stmt = $conn->prepare("SELECT id FROM follows WHERE follower_id = :me AND following_id = :them");
            $stmt->execute([':me' => $user_data['user_id'], ':them' => $user_id]);
            $is_following = $stmt->rowCount() > 0;
        }
    } catch (PDOException $e) {
        // 'follows' table missing on older installs — see backend/migration.sql
    }

    // Friend relationship between the logged-in user and the viewed profile:
    // 'none', 'pending_sent', 'pending_received', or 'accepted'. Lets the
    // frontend show the right button (Add Friend / Cancel / Accept / Unfriend).
    $friend_status = 'none';
    $friend_request_id = null;
    if ($user_id != $user_data['user_id']) {
        $stmt = $conn->prepare("
            SELECT id, sender_id, receiver_id, status
            FROM friends
            WHERE (sender_id = :me AND receiver_id = :them)
               OR (sender_id = :them2 AND receiver_id = :me2)
            LIMIT 1
        ");
        $stmt->execute([':me' => $user_data['user_id'], ':them' => $user_id, ':them2' => $user_id, ':me2' => $user_data['user_id']]);
        $rel = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($rel) {
            $friend_request_id = $rel['id'];
            if ($rel['status'] === 'accepted') {
                $friend_status = 'accepted';
            } elseif ($rel['status'] === 'pending') {
                $friend_status = ($rel['sender_id'] == $user_data['user_id']) ? 'pending_sent' : 'pending_received';
            }
        }
    }

    echo json_encode([
        'success' => true,
        'user' => $user,
        'post_count' => $post_count,
        'friend_count' => $friend_count,
        'following_count' => $following_count,
        'followers_count' => $followers_count,
        'is_following' => $is_following,
        'friend_status' => $friend_status,
        'friend_request_id' => $friend_request_id,
        'is_own_profile' => $user_id == $user_data['user_id']
    ]);
    exit;
}

// UPDATE profile
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $user_data['user_id'];
    
    $full_name = $data['full_name'] ?? '';
    $bio = $data['bio'] ?? '';
    
    $stmt = $conn->prepare("UPDATE users SET full_name = :full_name, bio = :bio WHERE id = :id");
    $stmt->execute([':full_name' => $full_name, ':bio' => $bio, ':id' => $user_id]);
    
    echo json_encode(['success' => true, 'message' => 'Profile updated']);
    exit;
}
?>