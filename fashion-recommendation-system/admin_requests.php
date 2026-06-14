<?php
include_once 'auth.php';
require_login();
require_admin();
include_once 'db.php';
include_once 'csrf.php';

// process status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        header('Location: admin_requests.php?msg=Invalid+CSRF');
        exit();
    }
    $id = intval($_POST['id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $stmt = $conn->prepare("UPDATE outfit_requests SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();
    header('Location: admin_requests.php');
    exit();
}

$res = mysqli_query($conn, "SELECT r.*, u.username FROM outfit_requests r LEFT JOIN users u ON u.id = r.user_id ORDER BY r.created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Outfit Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Outfit Requests (Admin)</h2>
    <a href="dashboard.php" class="btn btn-secondary mb-3">← Back</a>
    <?php if (mysqli_num_rows($res) == 0): ?>
        <div class="alert alert-info">No requests.</div>
    <?php else: ?>
        <table class="table table-striped">
            <thead><tr><th>User</th><th>Description</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php while ($r = mysqli_fetch_assoc($res)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['username']); ?></td>
                    <td><?php echo htmlspecialchars($r['description']); ?></td>
                    <td><?php echo htmlspecialchars($r['status']); ?></td>
                    <td>
                        <form method="post" style="display:inline-block;">
                            <?php echo csrf_input_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <select name="status" class="form-select form-select-sm d-inline-block" style="width:auto;">
                                <option value="open" <?php if($r['status']=='open') echo 'selected'; ?>>open</option>
                                <option value="in_progress" <?php if($r['status']=='in_progress') echo 'selected'; ?>>in_progress</option>
                                <option value="fulfilled" <?php if($r['status']=='fulfilled') echo 'selected'; ?>>fulfilled</option>
                                <option value="closed" <?php if($r['status']=='closed') echo 'selected'; ?>>closed</option>
                            </select>
                            <button class="btn btn-sm btn-primary">Save</button>
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
