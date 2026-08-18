<?php
/**
 * ONE-TIME MIGRATION SCRIPT
 * ============================================================
 * Run this ONCE after uploading the updated code, by opening it in your
 * browser, e.g.:
 *     http://localhost/social-site/backend/migrate.php
 * (or via CLI: php migrate.php)
 *
 * What it does:
 *   1. Adds the new `follows` table (for Followers/Following counts).
 *   2. Adds an `updated_at` column to posts & comments (so edited
 *      posts/comments can be tracked) — safely skipped if it already exists.
 *   3. Adds 'follow' to the notifications type enum.
 *   4. Creates (or reuses) the "CampusSphere Community" system account with:
 *        full name : CampusSphere Community
 *        email     : hafizafnan181@gmail.com
 *        joined    : 20/07/2026, 12:00:00 AM
 *   5. Fixes the welcome-post bug: finds every existing "Welcome to
 *      CampusSphere" post (which were wrongly created under each new
 *      user's own name), keeps a single copy, re-assigns it to the
 *      CampusSphere Community account with the fixed date above, and
 *      deletes the duplicates. If no welcome post exists yet, creates one.
 *
 * Safe to run more than once — every step checks before it changes anything.
 * ============================================================
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

$db = new Database();
$conn = $db->getConnection();

function step($label) {
    echo "→ $label ... ";
}
function ok($msg = 'done') {
    echo "$msg\n";
}

echo "CampusSphere migration starting...\n\n";

// ------------------------------------------------------------
// 1. follows table
// ------------------------------------------------------------
step('Creating follows table');
$conn->exec("
    CREATE TABLE IF NOT EXISTS `follows` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `follower_id` int(11) NOT NULL,
      `following_id` int(11) NOT NULL,
      `created_at` datetime DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_follow` (`follower_id`,`following_id`),
      FOREIGN KEY (`follower_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`following_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
ok();

step('Creating saved_posts table');
$conn->exec("
    CREATE TABLE IF NOT EXISTS `saved_posts` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `post_id` int(11) NOT NULL,
      `created_at` datetime DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_saved` (`user_id`,`post_id`),
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
ok();

step('Adding users.cover_photo');
try {
    $conn->exec("ALTER TABLE users ADD COLUMN cover_photo varchar(255) DEFAULT NULL");
    ok('added');
} catch (PDOException $e) {
    ok('already exists, skipped');
}

// ------------------------------------------------------------
// 2. updated_at columns
// ------------------------------------------------------------
step('Adding posts.updated_at');
try {
    $conn->exec("ALTER TABLE posts ADD COLUMN updated_at datetime DEFAULT NULL");
    ok('added');
} catch (PDOException $e) {
    ok('already exists, skipped');
}

step('Adding comments.updated_at');
try {
    $conn->exec("ALTER TABLE comments ADD COLUMN updated_at datetime DEFAULT NULL");
    ok('added');
} catch (PDOException $e) {
    ok('already exists, skipped');
}

step('Adding posts.pinned');
try {
    $conn->exec("ALTER TABLE posts ADD COLUMN pinned tinyint(1) DEFAULT 0");
    ok('added');
} catch (PDOException $e) {
    ok('already exists, skipped');
}

// ------------------------------------------------------------
// 3. notifications enum + 'follow'
// ------------------------------------------------------------
step("Adding 'follow' to notifications.type enum");
try {
    $conn->exec("ALTER TABLE notifications MODIFY type ENUM('like','comment','friend_request','friend_accepted','follow') NOT NULL");
    ok();
} catch (PDOException $e) {
    ok('skipped (' . $e->getMessage() . ')');
}

step('Adding notifications.related_id');
try {
    $conn->exec("ALTER TABLE notifications ADD COLUMN related_id int(11) DEFAULT NULL");
    ok('added');
} catch (PDOException $e) {
    ok('already exists, skipped');
}

// ------------------------------------------------------------
// 4. System account: CampusSphere Community
// ------------------------------------------------------------
step('Creating/finding CampusSphere Community account');

$systemEmail = 'hafizafnan181@gmail.com';
$systemUsername = 'campussphere';
$systemFullName = 'CampusSphere Community';
$systemJoined = '2026-07-20 00:00:00'; // 20/7/26, 12:00:00 AM

$stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute([':email' => $systemEmail]);
$systemUser = $stmt->fetch(PDO::FETCH_ASSOC);

if ($systemUser) {
    $systemUserId = $systemUser['id'];
    // Keep its identity/date correct even if it already existed.
    $stmt = $conn->prepare("UPDATE users SET username = :username, full_name = :full_name, email_verified = 1, created_at = :created_at WHERE id = :id");
    $stmt->execute([
        ':username' => $systemUsername,
        ':full_name' => $systemFullName,
        ':created_at' => $systemJoined,
        ':id' => $systemUserId
    ]);
    ok("found (id=$systemUserId), info refreshed");
} else {
    // Random, never-shared password — nobody logs into this account normally.
    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO users (username, email, password, full_name, bio, profile_pic, email_verified, created_at)
        VALUES (:username, :email, :password, :full_name, :bio, 'default.jpg', 1, :created_at)
    ");
    $stmt->execute([
        ':username' => $systemUsername,
        ':email' => $systemEmail,
        ':password' => $randomPassword,
        ':full_name' => $systemFullName,
        ':bio' => 'Official CampusSphere community account 🎓',
        ':created_at' => $systemJoined
    ]);
    $systemUserId = $conn->lastInsertId();
    ok("created (id=$systemUserId)");
}

// ------------------------------------------------------------
// 5. Fix the welcome post(s)
// ------------------------------------------------------------
step('Fixing welcome post(s)');

$stmt = $conn->prepare("SELECT id FROM posts WHERE content LIKE :pattern ORDER BY id ASC");
$stmt->execute([':pattern' => '%Welcome to CampusSphere%']);
$welcomePosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($welcomePosts) > 0) {
    // Keep the very first one, re-home it to the system account with the fixed date.
    $keepId = $welcomePosts[0]['id'];
    $stmt = $conn->prepare("UPDATE posts SET user_id = :uid, created_at = :created_at, pinned = 1 WHERE id = :id");
    $stmt->execute([':uid' => $systemUserId, ':created_at' => $systemJoined, ':id' => $keepId]);

    // Delete the rest (one per past buggy registration).
    $deletedCount = 0;
    for ($i = 1; $i < count($welcomePosts); $i++) {
        $stmt = $conn->prepare("DELETE FROM posts WHERE id = :id");
        $stmt->execute([':id' => $welcomePosts[$i]['id']]);
        $deletedCount++;
    }
    ok("kept post #$keepId under CampusSphere Community, deleted $deletedCount duplicate(s), pinned to top");
} else {
    // No welcome post exists at all (fresh DB) — create the single shared one.
    $welcome_content = "🎓 Welcome to CampusSphere!\nWhere Students Connect, Learn, and Grow Together.\n\nWelcome to CampusSphere — a community built exclusively for students to connect, collaborate, and create meaningful opportunities.\n\nHere, you can:\n🚀 Share your ideas, achievements, and experiences.\n📚 Discover educational content and campus updates.\n🤝 Connect with classmates and expand your academic network.\n💬 Engage in discussions, exchange knowledge, and support one another.\n🌟 Build your digital student profile and showcase your journey.\n\nCampusSphere isn't just another social platform—it's a place where friendships begin, ideas come to life, and future leaders grow together.\n\nStart exploring, create your first post, connect with fellow students, and become an active part of the CampusSphere community.\n\nTogether, let's build a smarter, stronger, and more connected campus. 💙";

    $stmt = $conn->prepare("INSERT INTO posts (user_id, content, pinned, created_at) VALUES (:uid, :content, 1, :created_at)");
    $stmt->execute([':uid' => $systemUserId, ':content' => $welcome_content, ':created_at' => $systemJoined]);
    ok('none found, created a fresh one under CampusSphere Community, pinned to top');
}

// Safety net: make sure exactly this one welcome post is pinned and nothing
// else accidentally is (in case pinned was set by hand before on another row).
step('Making sure only the welcome post is pinned');
$conn->exec("UPDATE posts SET pinned = 0 WHERE user_id = " . (int)$systemUserId . " AND pinned = 1 AND id NOT IN (SELECT id FROM (SELECT id FROM posts WHERE user_id = " . (int)$systemUserId . " ORDER BY id ASC LIMIT 1) AS keep_id)");
ok();

echo "\nAll done! You can delete this file (migrate.php) now if you like — it's not needed again.\n";
