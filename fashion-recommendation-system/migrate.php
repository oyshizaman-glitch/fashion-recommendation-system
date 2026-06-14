<?php
// Best-effort migration script. Safe to run multiple times.
if (!isset($conn)) {
    // require db.php if not already included
    if (file_exists(__DIR__ . '/db.php')) include_once __DIR__ . '/db.php';
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    return; // cannot migrate without DB connection
}

function run($sql) {
  global $conn;
  if (!$sql) return false;
  // Use error suppression to avoid throwing exceptions on unsupported syntax
  return @mysqli_query($conn, $sql);
}

// users table
run("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) DEFAULT NULL,
  gender ENUM('Male','Female','Other') DEFAULT NULL,
  preferences TEXT DEFAULT NULL,
  avatar VARCHAR(255) DEFAULT NULL,
  points INT DEFAULT 0,
  is_admin TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// items table
run("CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  price DECIMAL(10,2) DEFAULT NULL,
  image VARCHAR(255) DEFAULT NULL,
  stock INT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
)");

// wishlist
run("CREATE TABLE IF NOT EXISTS wishlist (
  user_id INT NOT NULL,
  item_id INT NOT NULL,
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, item_id)
)");

// ratings
run("CREATE TABLE IF NOT EXISTS ratings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  item_id INT NOT NULL,
  rating TINYINT NOT NULL,
  review TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// outfit_requests
run("CREATE TABLE IF NOT EXISTS outfit_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  description TEXT NOT NULL,
  status ENUM('open','in_progress','fulfilled','closed') DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// points transactions
run("CREATE TABLE IF NOT EXISTS points_tx (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  delta INT NOT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// orders
run("CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('pending','paid','cancelled','shipped') DEFAULT 'pending',
  payment_info TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// add foreign keys if possible (best-effort) — check information_schema first
function current_db() {
  global $conn;
  $r = mysqli_query($conn, "SELECT DATABASE() as db");
  if ($r) {
    $row = mysqli_fetch_assoc($r);
    return $row['db'];
  }
  return null;
}

function fk_exists($constraint_name) {
  global $conn;
  $db = current_db();
  if (!$db) return false;
  $sql = "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '".mysqli_real_escape_string($conn,$db)."' AND CONSTRAINT_NAME = '".mysqli_real_escape_string($conn,$constraint_name)."' LIMIT 1";
  $r = @mysqli_query($conn, $sql);
  return ($r && mysqli_num_rows($r) > 0);
}

$fks = [
  ['name' => 'fk_w_user', 'table' => 'wishlist', 'col' => 'user_id', 'ref' => 'users(id)'],
  ['name' => 'fk_w_item', 'table' => 'wishlist', 'col' => 'item_id', 'ref' => 'items(id)'],
  ['name' => 'fk_r_user', 'table' => 'ratings', 'col' => 'user_id', 'ref' => 'users(id)'],
  ['name' => 'fk_r_item', 'table' => 'ratings', 'col' => 'item_id', 'ref' => 'items(id)'],
];

foreach ($fks as $fk) {
  if (!fk_exists($fk['name'])) {
    $sql = "ALTER TABLE `".mysqli_real_escape_string($conn,$fk['table'])."` ADD CONSTRAINT `".mysqli_real_escape_string($conn,$fk['name'])."` FOREIGN KEY (`".mysqli_real_escape_string($conn,$fk['col'])."`) REFERENCES " . $fk['ref'] . " ON DELETE CASCADE";
    @run($sql);
  }
}

// indexes
run("CREATE INDEX IF NOT EXISTS idx_items_category ON items(category)");
run("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");

// ensure at least one admin user exists (best-effort)
$adminEmail = 'admin@example.com';
$check = mysqli_query($conn, "SELECT id FROM users WHERE is_admin=1 LIMIT 1");
if (!$check || mysqli_num_rows($check) == 0) {
    // if no admin exists, create default admin if not present
    $exists = mysqli_query($conn, "SELECT id FROM users WHERE email='" . mysqli_real_escape_string($conn, $adminEmail) . "' LIMIT 1");
    if ($exists && mysqli_num_rows($exists) == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        mysqli_query($conn, "INSERT INTO users (username, email, password, full_name, is_admin) VALUES ('admin', '" . mysqli_real_escape_string($conn, $adminEmail) . "', '" . mysqli_real_escape_string($conn, $hash) . "', 'Admin User', 1)");
    } else {
        // promote existing user with that email to admin
        mysqli_query($conn, "UPDATE users SET is_admin=1 WHERE email='" . mysqli_real_escape_string($conn, $adminEmail) . "'");
    }
}

?>
