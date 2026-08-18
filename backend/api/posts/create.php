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

// Read "content" correctly regardless of how the request was sent:
// - a text-only post arrives as JSON (fetch sends Content-Type: application/json),
//   and PHP's $_POST is ALWAYS empty for raw JSON bodies
// - a post with an image arrives as multipart/form-data, where $_POST works fine
// The old code only checked $_POST, so every text-only post (no image) was
// silently saved with EMPTY content — that's why posts appeared with just
// the username/like/comment row and nothing in the middle.
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    $content = trim($jsonInput['content'] ?? '');
} else {
    $content = trim($_POST['content'] ?? '');
}
$user_id = $user_data['user_id'];
$image_path = null;

// Handle image upload
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $target_dir = "../../uploads/posts/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    // SECURITY: check the REAL file content type, not just the filename —
    // a file named "photo.jpg" can still contain PHP code. Without this
    // check, anyone could upload a malicious script disguised as an image
    // and potentially run it on the server.
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

    // SECURITY: never trust the uploaded filename — generate our own
    // random one so a disguised extension (e.g. "shell.php.jpg") can
    // never end up saved with its original name.
    $image_name = 'post_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMimeTypes[$detectedType];
    $target_file = $target_dir . $image_name;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
        $image_path = 'uploads/posts/' . $image_name;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image']);
        exit;
    }
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("INSERT INTO posts (user_id, content, image) VALUES (:user_id, :content, :image)");
    $stmt->execute([':user_id' => $user_id, ':content' => $content, ':image' => $image_path]);

    echo json_encode([
        'success' => true,
        'message' => 'Post created successfully',
        'post_id' => $conn->lastInsertId()
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>