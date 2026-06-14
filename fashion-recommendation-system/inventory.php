<?php
include_once 'auth.php';
require_login();
// Only admins should manage inventory
require_admin();
$user = get_logged_in_user();

// Fetch items
$res = mysqli_query($conn, "SELECT * FROM items ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inventory - FashionDB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Inventory Management</h2>
    <a href="dashboard.php" class="btn btn-secondary mb-3">← Back to Dashboard</a>
    <a href="add_item.php" class="btn btn-success mb-3">+ Add Item</a>

    <?php if (mysqli_num_rows($res) == 0): ?>
        <div class="alert alert-info">No items in inventory.</div>
    <?php else: ?>
        <table class="table table-striped table-bordered">
            <thead class="table-primary">
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while($item = mysqli_fetch_assoc($res)): ?>
                <tr>
                    <td style="width:120px;">
                        <?php
                            $img = '';
                            if (!empty($item['image'])) $img = $item['image'];
                            elseif (!empty($item['image_url'])) $img = $item['image_url'];
                            else $img = 'https://via.placeholder.com/100x80?text=No+Image';
                        ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="max-width:100px; height:auto;" onerror="this.src='https://via.placeholder.com/100x80?text=No+Image'">
                    </td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                    <td><?php echo ($item['price'] !== null ? number_format($item['price'],2) : '-'); ?></td>
                    <td><?php echo htmlspecialchars($item['stock']); ?></td>
                    <td style="width:200px;">
                        <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                        <form method="post" action="edit_item.php" style="display:inline-block;" onsubmit="return confirm('Delete this item?');">
                            <input type="hidden" name="delete_id" value="<?php echo $item['id']; ?>">
                            <button class="btn btn-sm btn-danger">Delete</button>
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