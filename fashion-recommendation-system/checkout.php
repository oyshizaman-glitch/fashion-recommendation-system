<?php
include_once 'auth.php';
require_login();
include_once 'db.php';
include_once 'csrf.php';
include_once 'stripe_config.php';

$user = get_logged_in_user();
$user_id = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // verify CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        die('Invalid CSRF token');
    }

    $total = floatval($_POST['total'] ?? 0);
    $use_points = isset($_POST['use_points']) ? intval($_POST['use_points']) : 0;
    // Convert points to currency: 100 points = 1 currency unit
    $discount = 0;
    if ($use_points) {
        // make sure user has enough points
        $pts = intval($user['points'] ?? 0);
        $to_use = min($pts, $use_points);
        $discount = ($to_use / 100.0);
    }
    $final = max(0, $total - $discount);

    // create order
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total, status, payment_info) VALUES (?, ?, 'pending', ?)");
    $payinfo = json_encode(['use_points' => $use_points, 'discount' => $discount]);
    $stmt->bind_param('ids', $user_id, $final, $payinfo);
    $stmt->execute();
    $order_id = $stmt->insert_id;

    if (!empty(STRIPE_SECRET)) {
        // Use Stripe Checkout
        // call create_checkout.php via fetch from client (we'll redirect using response)
        header('Location: mock_payment.php?order_id=' . $order_id . '&use_points=' . intval($use_points));
        exit();
    } else {
        // fallback to mock
        header('Location: mock_payment.php?order_id=' . $order_id . '&use_points=' . intval($use_points));
        exit();
    }
}

// Simple checkout sample form
?>
<!DOCTYPE html>
<html>
<head>
    <title>Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Checkout</h2>
    <a href="dashboard.php" class="btn btn-secondary mb-3">← Back</a>

    <form id="checkoutForm" method="post">
        <?php echo csrf_input_field(); ?>
        <div class="mb-3">
            <label class="form-label">Total amount</label>
            <input type="text" name="total" class="form-control" value="0.00" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Use points (you have <?php echo intval($user['points']); ?>)</label>
            <input type="number" name="use_points" class="form-control" value="0">
            <div class="form-text">100 points = 1.00 discount</div>
        </div>
        <button id="payBtn" class="btn btn-primary">Proceed to Payment</button>
    </form>
</div>
<script>
document.getElementById('payBtn').addEventListener('click', function(e){
    e.preventDefault();
    const total = parseFloat(document.querySelector('input[name="total"]').value||0);
    const use_points = parseInt(document.querySelector('input[name="use_points"]').value||0);
    const token = document.querySelector('input[name="csrf_token"]').value;

    fetch('create_order_and_checkout.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ total: total, use_points: use_points, csrf_token: token })
    }).then(r=>r.json()).then(j=>{
        if (j.error) {
            alert('Payment error: ' + (j.error||'unknown'));
            return;
        }
        if (j.url) {
            window.location = j.url;
        } else {
            alert('No redirect url returned');
        }
    }).catch(err=>{ alert('Network error'); });
});
</script>
</body>
</html>
