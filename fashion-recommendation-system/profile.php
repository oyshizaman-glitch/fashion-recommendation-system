<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';
$user_id = $_SESSION['user_id'];

// Fetch user
$stmt = $conn->prepare("SELECT id, username, email, full_name, gender, preferences, avatar FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

$message = '';
$error = '';

include_once 'csrf.php';
include_once 'uploads_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid CSRF token.';
    }
    // Update profile fields
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name'] ?? '');
    $gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
    $preferences = mysqli_real_escape_string($conn, $_POST['preferences'] ?? '');

    // Handle avatar upload
    if (empty($error) && !empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed)) {
            $error = 'Invalid avatar type.';
        } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            $error = 'Avatar too large (max 2MB).';
        } else {
            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('av_') . '.' . $ext;
            $targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR;
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $target = $targetDir . $filename;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target)) {
                $avatarPath = 'uploads/avatars/' . $filename;
                // create thumbnail
                $thumb = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR . 'thumb_' . $filename;
                @make_thumb($target, $thumb, 200, 200);
                // Delete old avatar (optional)
                if (!empty($user['avatar']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $user['avatar'])) {
                    @unlink(__DIR__ . DIRECTORY_SEPARATOR . $user['avatar']);
                }
            } else {
                $error = 'Failed to move uploaded avatar.';
            }
        }
    }

    if (empty($error)) {
        if (isset($avatarPath)) {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, gender=?, preferences=?, avatar=? WHERE id=?");
            $stmt->bind_param('ssssi', $full_name, $gender, $preferences, $avatarPath, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, gender=?, preferences=? WHERE id=?");
            $stmt->bind_param('sssi', $full_name, $gender, $preferences, $user_id);
        }
        if ($stmt->execute()) {
            $message = 'Profile updated successfully.';
            // Refresh user data for display
            $stmt = $conn->prepare("SELECT id, username, email, full_name, gender, preferences, avatar FROM users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } else {
            $error = 'Failed to update profile.';
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile - FashionDB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Your Profile</h2>
    <a href="dashboard.php" class="btn btn-secondary mb-3">← Back</a>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card p-3 text-center">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" class="img-fluid rounded mb-3" style="max-height:200px;">
                <?php else: ?>
                    <img src="https://via.placeholder.com/200x200?text=Avatar" alt="Avatar" class="img-fluid rounded mb-3">
                <?php endif; ?>
                <h5><?php echo htmlspecialchars($user['username']); ?></h5>
                <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                <?php if (!empty($user['avatar'])): ?>
                    <form method="post" action="remove_avatar.php" class="mt-2">
                        <?php echo csrf_input_field(); ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove avatar?')">Remove Avatar</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-8">
            <form method="post" enctype="multipart/form-data">
                <?php echo csrf_input_field(); ?>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">Not specified</option>
                        <option value="Male" <?php if($user['gender']=='Male') echo 'selected'; ?>>Male</option>
                        <option value="Female" <?php if($user['gender']=='Female') echo 'selected'; ?>>Female</option>
                        <option value="Other" <?php if($user['gender']=='Other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Style Preferences</label>
                    <textarea name="preferences" class="form-control" rows="4"><?php echo htmlspecialchars($user['preferences']); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Avatar (optional)</label>
                    <input type="file" name="avatar" accept="image/*" class="form-control">
                </div>
                <button class="btn btn-primary">Save Profile</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>