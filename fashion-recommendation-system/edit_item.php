<?php
include_once 'auth.php';
require_login();
require_admin();
include_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle delete
    if (isset($_POST['delete_id'])) {
        $del = intval($_POST['delete_id']);
        // Delete item
        $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
        $stmt->bind_param('i', $del);
        $stmt->execute();
        header('Location: inventory.php');
        exit();
    }

    // Handle update
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = $_POST['price'] !== '' ? floatval($_POST['price']) : null;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 1;

    // Handle image replacement
    $imgPath = null;
    if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        if (in_array($mime, $allowed)) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('img_') . '.' . $ext;
            $targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $target = $targetDir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $imgPath = 'uploads/' . $filename;
            }
        }
    }

    // Build update query
    if ($imgPath !== null) {
        $stmt = $conn->prepare("UPDATE items SET name=?, category=?, price=?, stock=?, image=? WHERE id=?");
        $stmt->bind_param('ssdisi', $name, $category, $price, $stock, $imgPath, $id);
    } else {
        $stmt = $conn->prepare("UPDATE items SET name=?, category=?, price=?, stock=? WHERE id=?");
        $stmt->bind_param('ssdii', $name, $category, $price, $stock, $id);
    }
    $stmt->execute();
    header('Location: inventory.php');
    exit();
}

// GET: show form
$id = intval($_GET['id'] ?? 0);
$item = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    if (!$item) {
        header('Location: inventory.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $item ? 'Edit' : 'Add'; ?> Item - FashionDB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2><?php echo $item ? 'Edit' : 'Add'; ?> Item</h2>
    <a href="inventory.php" class="btn btn-secondary mb-3">← Back</a>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $item ? $item['id'] : 0; ?>">
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" required value="<?php echo $item ? htmlspecialchars($item['name']) : ''; ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Category</label>
            <input type="text" name="category" class="form-control" value="<?php echo $item ? htmlspecialchars($item['category']) : ''; ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Price</label>
            <input type="text" name="price" class="form-control" value="<?php echo $item && $item['price'] !== null ? htmlspecialchars($item['price']) : ''; ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Stock</label>
            <input type="number" name="stock" class="form-control" value="<?php echo $item ? intval($item['stock']) : 1; ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Image (replace)</label>
            <input type="file" name="image" accept="image/*" class="form-control">
            <?php if ($item && !empty($item['image'])): ?>
                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="img" style="max-width:120px; margin-top:8px;" onerror="this.src='https://via.placeholder.com/120x80?text=No+Image'">
            <?php endif; ?>
        </div>
        <button class="btn btn-primary"><?php echo $item ? 'Save Changes' : 'Add Item'; ?></button>
        <?php if ($item): ?>
            <button type="submit" class="btn btn-danger" name="_delete" value="1" formaction="edit_item.php" formmethod="post" onclick="return confirm('Delete this item?');">Delete</button>
        <?php endif; ?>
    </form>
</div>
</body>
</html>