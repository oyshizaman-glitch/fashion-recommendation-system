<?php
session_start();
include_once __DIR__ . '/../auth.php';
require_login();
include_once __DIR__ . '/../db.php';
include_once __DIR__ . '/../csrf.php';
include_once __DIR__ . '/../stripe_config.php';

// Local debug mode: when enabled, simulate gateway redirect to local success page
$LOCAL_PAYMENT_DEBUG = false;

// Basic POST handling
$total = $_POST['total'] ?? null; // expected decimal or integer
$payment_method = $_POST['payment_method'] ?? 'SSLCommerz';
$item_id = intval($_POST['item_id'] ?? 0);
$user_id = intval($_SESSION['user_id'] ?? 0);

// CSRF check
$token = $_POST['csrf_token'] ?? null;
if (!verify_csrf_token($token)) {
    die('invalid_csrf');
}

if (!$user_id || !$total) {
    die('Missing user or total');
}

// Insert an order record (status initiated)
$stmt = $conn->prepare("INSERT INTO orders (user_id, total, status, payment_info, created_at) VALUES (?, ?, 'initiated', ?, NOW())");
$pinfo = $payment_method;
$stmt->bind_param('ids', $user_id, $total, $pinfo);
$stmt->execute();
$order_id = $conn->insert_id;

// Detect AJAX (fetch) requests
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (!empty($_POST['ajax']) && $_POST['ajax']=='1');

if ($payment_method === 'cod') {
    // Cash on Delivery: mark order as COD pending
    $stmt = $conn->prepare("UPDATE orders SET status = 'cod', payment_info = 'cod' WHERE id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    if ($is_ajax) {
        echo json_encode(['success'=>true,'method'=>'cod','order_id'=>$order_id]);
        exit;
    } else {
        header('Location: ../dashboard.php?msg=Order+placed+COD');
        exit;
    }
}

if ($payment_method === 'SSLCommerz') {
    // If local debug, simulate a gateway redirect to our success handler
    if (!empty($LOCAL_PAYMENT_DEBUG)) {
        $sim = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/success.php?order=' . $order_id;
        if ($is_ajax) {
            echo json_encode(['redirect'=>$sim,'debug'=>true]);
            exit;
        }
        header('Location: ' . $sim);
        exit;
    }
    // Prepare SSLCommerz sandbox request
    $data = [
      'store_id' => 'testbox',
      'store_passwd' => 'qwerty',
      'total_amount' => $total,
      'currency' => 'BDT',
      'tran_id' => (string)$order_id,
      'success_url' => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/success.php',
      'fail_url' => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/fail.php',
      'cancel_url' => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/cancel.php',
      'cus_name' => $_SESSION['username'] ?? 'Customer',
      'cus_email' => $_SESSION['email'] ?? 'test@example.com'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://sandbox.sslcommerz.com/gwprocess/v4/api.php");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    if (!empty($result['GatewayPageURL'])) {
        if ($is_ajax) {
            echo json_encode(['redirect'=>$result['GatewayPageURL']]);
            exit;
        }
        header('Location: ' . $result['GatewayPageURL']);
        exit;
    } else {
        if ($is_ajax) {
            echo json_encode(['error'=>'gateway_error','detail'=>$response]);
            exit;
        }
        die('Payment gateway error');
    }

} elseif ($payment_method === 'stripe') {
    // Create Stripe Checkout session using REST API (requires STRIPE_SECRET)
    // Local debug: simulate success redirect
    if (!empty($LOCAL_PAYMENT_DEBUG)) {
        $sim = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/success.php?order=' . $order_id;
        if ($is_ajax) {
            echo json_encode(['redirect'=>$sim,'debug'=>true]);
            exit;
        }
        header('Location: ' . $sim);
        exit;
    }
    if (!defined('STRIPE_SECRET') || empty(STRIPE_SECRET)) {
        die('Stripe not configured on server');
    }

    // total expected in cents; if user posted a value in main currency, convert
    $amount_cents = is_numeric($total) && intval($total) == $total ? intval($total) : intval(round(floatval($total) * 100));

    $post = http_build_query([
        'payment_method_types[]' => 'card',
        'mode' => 'payment',
        'line_items[0][price_data][currency]' => 'usd',
        'line_items[0][price_data][product_data][name]' => 'Order #' . $order_id,
        'line_items[0][price_data][unit_amount]' => $amount_cents,
        'line_items[0][quantity]' => 1,
        'success_url' => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/cancel.php',
        'client_reference_id' => (string)$order_id
    ]);

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET . ':');
    $res = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $js = json_decode($res, true);
    if ($http_status >= 200 && !empty($js['url'])) {
        if ($is_ajax) {
            echo json_encode(['redirect'=>$js['url']]);
            exit;
        }
        header('Location: ' . $js['url']);
        exit;
    } else {
        file_put_contents(__DIR__ . '/../upload_debug.log', date('c') . " - stripe_session_error: " . $res . "\n", FILE_APPEND);
        if ($is_ajax) {
            echo json_encode(['error'=>'stripe_error','detail'=>$res]);
            exit;
        }
        die('Stripe error');
    }
} else {
    die('Unsupported payment method');
}

?>
