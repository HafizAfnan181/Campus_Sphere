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

    // Get pending friend requests sent TO current user
    $stmt = $conn->prepare("
        SELECT f.id as request_id, f.sender_id, u.username, u.full_name, u.profile_pic, f.created_at
        FROM friends f
        JOIN users u ON f.sender_id = u.id
        WHERE f.receiver_id = :user_id AND f.status = 'pending'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([':user_id' => $user_data['user_id']]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'requests' => $requests]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>