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

$query = $_GET['q'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id, username, full_name, profile_pic FROM users WHERE (username LIKE :q OR full_name LIKE :q2) AND id != :current_user ORDER BY id DESC LIMIT 100");
    $search_term = "%$query%";
    $stmt->execute([':q' => $search_term, ':q2' => $search_term, ':current_user' => $user_data['user_id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'users' => $users]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>