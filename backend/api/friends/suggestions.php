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

    // Suggested friends = everyone EXCEPT: me, people I'm already friends
    // with, and people who have a pending/rejected request between us in
    // either direction. This keeps the suggestions list short and relevant
    // instead of showing every registered user.
    $stmt = $conn->prepare("
        SELECT id, username, full_name, profile_pic
        FROM users
        WHERE id != :user_id
        AND id NOT IN (
            SELECT receiver_id FROM friends WHERE sender_id = :user_id2
            UNION
            SELECT sender_id FROM friends WHERE receiver_id = :user_id3
        )
        ORDER BY id DESC
        LIMIT 100
    ");
    $stmt->execute([
        ':user_id' => $user_id,
        ':user_id2' => $user_id,
        ':user_id3' => $user_id
    ]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'users' => $users]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
