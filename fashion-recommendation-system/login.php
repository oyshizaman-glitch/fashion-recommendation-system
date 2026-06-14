<?php
session_start();
include 'db.php';

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $login_as = $_POST['login_as'] ?? 'user';

    $sql = "SELECT * FROM users WHERE username='$input' OR email='$input'";
    $result = mysqli_query($conn, $sql);
    
    if(mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        if(password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            // If admin login requested, ensure the user is an admin
            if ($login_as === 'admin') {
                $is_admin = isset($user['is_admin']) ? $user['is_admin'] : null;
                if ($is_admin === null) {
                    // fetch explicitly
                    $r2 = mysqli_query($conn, "SELECT is_admin FROM users WHERE id=" . intval($user['id']) . " LIMIT 1");
                    if ($r2 && mysqli_num_rows($r2) > 0) {
                        $row2 = mysqli_fetch_assoc($r2);
                        $is_admin = $row2['is_admin'];
                    } else {
                        $is_admin = 0;
                    }
                }
                if (empty($is_admin)) {
                    // not allowed
                    session_unset(); session_destroy();
                    header("Location: index.php?msg=Admin+login+failed");
                    exit;
                }
            }
            header("Location: dashboard.php");
        } else {
            header("Location: index.php?msg=Invalid password");
        }
    } else {
        header("Location: index.php?msg=User not found");
    }
}
?>