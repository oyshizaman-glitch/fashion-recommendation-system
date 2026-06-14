<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db.php';
$user_id = $_SESSION['user_id'];
include_once 'csrf.php';

// === ADD/REMOVE WISHLIST ===
// Handle add/remove via POST for safer operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['action']) && !empty($_POST['item_id'])) {
        $item_id = intval($_POST['item_id']);
        if ($_POST['action'] === 'add') {
            $stmt = $conn->prepare("INSERT IGNORE INTO wishlist (user_id, item_id) VALUES (?, ?)");
            $stmt->bind_param('ii', $user_id, $item_id);
            $stmt->execute();
        } elseif ($_POST['action'] === 'remove') {
            $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND item_id = ?");
            $stmt->bind_param('ii', $user_id, $item_id);
            $stmt->execute();
        }
    }
    header("Location: wishlist.php");
    exit();
}

// Backwards compatibility: allow GET removal when clicking old links
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['remove'])) {
    $item_id = intval($_GET['remove']);
    $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND item_id = ?");
    $stmt->bind_param('ii', $user_id, $item_id);
    $stmt->execute();
    header("Location: wishlist.php");
    exit();
}

// === FETCH WISHLIST ITEMS ===
$result = mysqli_query($conn, "SELECT i.* FROM items i JOIN wishlist w ON i.id = w.item_id WHERE w.user_id = $user_id");
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Wishlist</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>My Wishlist</h2>
    <a href="dashboard.php" class="btn btn-secondary mb-3">← Back to Dashboard</a>

    <?php if (mysqli_num_rows($result) == 0): ?>
        <div class="alert alert-info">
            Your wishlist is empty.
        </div>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead class="table-primary">
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = mysqli_fetch_assoc($result)): ?>
                <tr>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                        <td style="width:140px;">
                            <?php
                                $img = '';
                                if (!empty($item['image'])) $img = $item['image'];
                                elseif (!empty($item['image_url'])) $img = $item['image_url'];
                                elseif (!empty($item['image_path'])) $img = $item['image_path'];
                                else $img = 'https://via.placeholder.com/120x90?text=No+Image';
                            ?>
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="max-width:120px; height:auto;" onerror="this.src='https://via.placeholder.com/120x90?text=No+Image'">
                        </td>
                        <td>
                            <form method="post" action="wishlist.php" style="display:inline-block;" onsubmit="return confirm('Remove this item?');">
                                <?php echo csrf_input_field(); ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <button class="btn btn-danger btn-sm">Remove</button>
                            </form>
                        </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>