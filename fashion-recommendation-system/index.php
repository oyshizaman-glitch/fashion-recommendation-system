<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FashionDB - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #ffeef8, #e0f7fa); min-height: 100vh; }
        .login-card { border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
    </style>
</head>
<body class="d-flex align-items-center">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card login-card p-4">
                <div class="text-center mb-4">
                    <h2 class="text-primary">👗 FashionDB</h2>
                    <p>Fashion Outfit Recommendation System</p>
                </div>
                <?php if(isset($_GET['msg'])) echo "<div class='alert alert-info'>".$_GET['msg']."</div>"; ?>
                <form action="login.php" method="post">
                    <div class="mb-3">
                        <input type="text" name="username" class="form-control" placeholder="Username or Email" required>
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                    <input type="hidden" name="login_as" id="login_as" value="user">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Login (User)</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('login_as').value='admin'; this.form.submit();">Login (Admin)</button>
                    </div>
                </form>
                <p class="text-center mt-3">New user? <a href="registration.php">Register here</a></p>
            </div>
        </div>
    </div>
</div>
</body>
</html>