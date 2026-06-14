<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include_once __DIR__ . '/db.php';
// automatic migrations disabled to avoid runtime SQL errors during page loads
// If you need to run migrations manually, run migrate.php from CLI or re-enable this include.
// if (file_exists(__DIR__ . '/migrate.php')) include_once __DIR__ . '/migrate.php';

$current_user = null;
if (!empty($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
    // Fetch a safe baseline row without selecting potentially-missing columns
    $r = @mysqli_query($conn, "SELECT id, username, email FROM users WHERE id = $uid LIMIT 1");
    if ($r && mysqli_num_rows($r) > 0) {
        $current_user = mysqli_fetch_assoc($r);
        // Fetch optional columns if they exist: is_admin, points
        $colRes = @mysqli_query($conn, "SHOW COLUMNS FROM users WHERE Field IN ('is_admin','points')");
        $cols = [];
        if ($colRes) {
            while ($c = mysqli_fetch_assoc($colRes)) $cols[] = $c['Field'];
        }

        if (in_array('is_admin', $cols)) {
            $r2 = @mysqli_query($conn, "SELECT is_admin FROM users WHERE id = $uid LIMIT 1");
            if ($r2) {
                $row2 = mysqli_fetch_assoc($r2);
                $current_user['is_admin'] = isset($row2['is_admin']) ? $row2['is_admin'] : 0;
            } else {
                $current_user['is_admin'] = 0;
            }
        } else {
            $current_user['is_admin'] = 0;
        }

        if (in_array('points', $cols)) {
            $r3 = @mysqli_query($conn, "SELECT points FROM users WHERE id = $uid LIMIT 1");
            if ($r3) {
                $row3 = mysqli_fetch_assoc($r3);
                $current_user['points'] = intval($row3['points']);
            } else {
                $current_user['points'] = 0;
            }
        } else {
            $current_user['points'] = 0;
        }
    } else {
        $current_user = null;
    }
}

if (!function_exists('require_login')) {
    function require_login() {
        global $current_user;
        if (!$current_user) {
            header('Location: index.php');
            exit();
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin() {
        global $current_user;
        if (!$current_user || empty($current_user['is_admin'])) {
            header('Location: dashboard.php?msg=Admin+only');
            exit();
        }
    }
}

// Provide a non-conflicting helper for the current logged-in user
function get_logged_in_user() {
    global $current_user;
    return $current_user;
}

// Backwards-compatible alias for older code; avoid clobbering PHP's built-in get_current_user()
if (!function_exists('get_app_current_user')) {
    function get_app_current_user() {
        return get_logged_in_user();
    }
}

?>
