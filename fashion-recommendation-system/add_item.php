<?php
include_once 'auth.php';
require_login();
require_admin();
include_once 'db.php';
include_once 'csrf.php';
include_once 'uploads_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid CSRF token.';
    }
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = trim($_POST['price'] ?? '');

    // Handle image upload
    $imgPath = null;
    if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed)) {
            $error = 'Invalid image type. Allowed: jpg, png, gif, webp.';
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $error = 'Image too large (max 5MB).';
        } else {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('img_', true) . '.' . $ext;
            $targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $target = $targetDir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $imgPath = 'uploads/' . $filename;
                // create thumbnail
                $thumb = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'thumb_' . $filename;
                @make_thumb($target, $thumb, 400, 300);
            } else {
                $error = 'Failed to move uploaded file.';
            }
        }
    }

    if (empty($name)) $error = 'Name is required.';

    if (empty($error)) {
        // Ensure `image` column exists (create if missing)
        $colRes = mysqli_query($conn, "SHOW COLUMNS FROM items LIKE 'image'");
        if (mysqli_num_rows($colRes) == 0) {
            mysqli_query($conn, "ALTER TABLE items ADD COLUMN image VARCHAR(255) NULL");
        }

        // Detect if `price` column exists to insert accordingly
        $cols = [];
        $res = mysqli_query($conn, "SHOW COLUMNS FROM items");
        while ($r = mysqli_fetch_assoc($res)) $cols[] = $r['Field'];

        if (in_array('price', $cols)) {
            $stmt = $conn->prepare("INSERT INTO items (name, category, price, image) VALUES (?, ?, ?, ?)");
            $priceVal = ($price === '' ? null : $price);
            $stmt->bind_param('ssss', $name, $category, $priceVal, $imgPath);
        } else {
            $stmt = $conn->prepare("INSERT INTO items (name, category, image) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $name, $category, $imgPath);
        }
        $stmt->execute();
        header('Location: dashboard.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Item</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Add Item</h2>
    <a href="dashboard.php" class="btn btn-secondary mb-3">← Back</a>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?php echo csrf_input_field(); ?>
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" required value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Category</label>
            <input type="text" name="category" class="form-control" value="<?php echo isset($category) ? htmlspecialchars($category) : ''; ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Price (optional)</label>
            <input type="text" name="price" class="form-control" value="<?php echo isset($price) ? htmlspecialchars($price) : ''; ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Image (optional)</label>
            <input type="file" name="image" accept="image/*" class="form-control">
        </div>
        <button class="btn btn-primary">Add Item</button>
    </form>
</div>
</body>
</html>