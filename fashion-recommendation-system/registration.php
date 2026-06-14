<?php
session_start();
include 'db.php'; // Database connection

// Initialize variables for messages and form values
$message = '';
$username = $email = $full_name = $gender = $preferences = '';

// Process form when submitted
// Process form when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username     = mysqli_real_escape_string($conn, $_POST['username']);
    $email        = mysqli_real_escape_string($conn, $_POST['email']);
    $password     = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name    = mysqli_real_escape_string($conn, $_POST['full_name']);
    $gender       = mysqli_real_escape_string($conn, $_POST['gender']);
    $preferences  = mysqli_real_escape_string($conn, $_POST['preferences']);

    // Determine account type
    $account_type = ($_POST['account_type'] ?? 'user');
    $is_admin = ($account_type === 'admin') ? 1 : 0;

    // Check for existing username or email
    $chk = $conn->prepare("SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
    $chk->bind_param('ss', $username, $email);
    $chk->execute();
    $cres = $chk->get_result();
    if ($cres && $cres->num_rows > 0) {
        $r = $cres->fetch_assoc();
        if (strcasecmp($r['username'], $username) === 0) {
            $message = "<div class='alert alert-danger mt-3'>Error: Username already exists.</div>";
        } else {
            $message = "<div class='alert alert-danger mt-3'>Error: Email already registered.</div>";
        }
        // Keep entered values to refill form
        $username    = $_POST['username'];
        $email       = $_POST['email'];
        $full_name   = $_POST['full_name'];
        $gender      = $_POST['gender'];
        $preferences = $_POST['preferences'];
    } else {
        // Insert using prepared statement
        if ($is_admin) {
            $ins = $conn->prepare("INSERT INTO users (username, email, password, full_name, gender, preferences, is_admin) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $ins->bind_param('ssssss', $username, $email, $password, $full_name, $gender, $preferences);
        } else {
            $ins = $conn->prepare("INSERT INTO users (username, email, password, full_name, gender, preferences) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->bind_param('ssssss', $username, $email, $password, $full_name, $gender, $preferences);
        }

        try {
            if ($ins->execute()) {
                header("Location: index.php?msg=Registration successful! Please login");
                exit();
            } else {
                $message = "<div class='alert alert-danger mt-3'>Error: Could not create account.</div>";
            }
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger mt-3'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FashionDB - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f5f7fa, #d4fcff); min-height: 100vh; }
        .card { border-radius: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="d-flex align-items-center">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card p-4">
                <div class="text-center mb-4">
                    <h3 class="text-primary">👗 FashionDB</h3>
                    <p>Create Your Account</p>
                </div>

                <?php echo $message; ?>

                <form method="post" action="registration.php">
                    <div class="mb-3">
                        <input type="text" name="username" class="form-control" placeholder="Username" 
                               value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                    <div class="mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Email" 
                               value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                    <div class="mb-3">
                        <input type="text" name="full_name" class="form-control" placeholder="Full Name (Optional)" 
                               value="<?php echo htmlspecialchars($full_name); ?>">
                    </div>
                    <div class="mb-3">
                        <select name="gender" class="form-select">
                            <option value="">Select Gender (Optional)</option>
                            <option value="Male" <?php if($gender=='Male') echo 'selected'; ?>>Male</option>
                            <option value="Female" <?php if($gender=='Female') echo 'selected'; ?>>Female</option>
                            <option value="Other" <?php if($gender=='Other') echo 'selected'; ?>>Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <textarea name="preferences" class="form-control" rows="3" 
                                  placeholder="Style preferences (e.g., casual, formal, ethnic)"><?php echo htmlspecialchars($preferences); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Register As</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="account_type" id="acct_user" value="user" checked>
                                <label class="form-check-label" for="acct_user">User</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="account_type" id="acct_admin" value="admin">
                                <label class="form-check-label" for="acct_admin">Admin</label>
                            </div>
                        </div>
                        <div class="form-text">Choose account type. Admin accounts will be created with admin privileges.</div>
                    </div>

                        <button type="submit" class="btn btn-success btn-lg w-100">Register</button>
                </form>

                <p class="text-center mt-4">
                    Already have an account? <a href="index.php" class="text-decoration-none">Login here</a>
                </p>
            </div>
        </div>
    </div>
</div>
</body>
</html>