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

$user_id = $user_data['user_id'];

// "profile" -> users.profile_pic, "cover" -> users.cover_photo
$type = $_POST['type'] ?? 'profile';
if (!in_array($type, ['profile', 'cover'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid photo type']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] != 0) {
    echo json_encode(['success' => false, 'message' => 'No image uploaded']);
    exit;
}

// SECURITY: check the REAL file content type, not just the filename — same
// approach as posts/create.php, so a disguised script can never be saved
// and treated as an image.
$allowedMimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$detectedType = mime_content_type($_FILES['image']['tmp_name']);

if (!isset($allowedMimeTypes[$detectedType])) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, or WEBP images are allowed']);
    exit;
}

if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Image must be smaller than 5MB']);
    exit;
}

$target_dir = "../../uploads/profiles/";
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0755, true);
}

// SECURITY: never trust the uploaded filename — generate our own random one.
$image_name = $type . '_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMimeTypes[$detectedType];
$target_file = $target_dir . $image_name;

if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image']);
    exit;
}

$new_path = 'uploads/profiles/' . $image_name;
$column = $type === 'profile' ? 'profile_pic' : 'cover_photo';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Remove the old file so uploads don't pile up, but never delete the
    // shared default picture.
    $stmt = $conn->prepare("SELECT $column FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($old && !empty($old[$column]) && $old[$column] !== 'default.jpg') {
        $old_file = '../../' . $old[$column];
        if (file_exists($old_file)) {
            @unlink($old_file);
        }
    }

    $stmt = $conn->prepare("UPDATE users SET $column = :path WHERE id = :id");
    $stmt->execute([':path' => $new_path, ':id' => $user_id]);

    echo json_encode(['success' => true, 'message' => 'Photo updated successfully', 'path' => $new_path]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
