<?php
include_once 'auth.php';
require_login();
include_once 'db.php';
include_once 'csrf.php';

$user = get_logged_in_user();
$user_id = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        header('Location: requests.php?msg=Invalid+CSRF');
        exit();
    }
    $desc = trim($_POST['description'] ?? '');
    if ($desc !== '') {
        $stmt = $conn->prepare("INSERT INTO outfit_requests (user_id, description) VALUES (?, ?)");
        $stmt->bind_param('is', $user_id, $desc);
        $stmt->execute();
        header('Location: requests.php?msg=Request+submitted');
        exit();
    }
}

$res = $conn->prepare("SELECT * FROM outfit_requests WHERE user_id = ? ORDER BY created_at DESC");
$res->bind_param('i', $user_id);
$res->execute();
$requests = $res->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Outfit Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Request a Specific Outfit</h2>
    <a href="dashboard.php" class="btn btn-secondary mb-3">← Back</a>

    <form method="post">
        <?php echo csrf_input_field(); ?>
        <div class="mb-3">
            <label class="form-label">Describe what you want</label>
            <textarea name="description" class="form-control" rows="4" required></textarea>
        </div>
        <button class="btn btn-primary">Submit Request</button>
    </form>

    <h4 class="mt-4">Your Requests</h4>
    <?php if ($requests->num_rows == 0): ?>
        <div class="alert alert-info">No requests yet.</div>
    <?php else: ?>
        <ul class="list-group">
            <?php while($r = $requests->fetch_assoc()): ?>
                <li class="list-group-item">
                    <strong><?php echo htmlspecialchars($r['status']); ?></strong> — <?php echo htmlspecialchars($r['description']); ?>
                    <div class="text-muted small"><?php echo $r['created_at']; ?></div>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php endif; ?>
</div>
</body>
</html>
