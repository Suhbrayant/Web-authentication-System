<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$host = "localhost";
$user = "root";
$password = "";
$database = "users_db";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: ". $conn->connect_error);
    # code...
}
$conn->set_charset("utf8mb4");

// ── Auth helpers 
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login_register.php");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header("Location: user_page.php");
        exit();
    }
}

function requireUser() {
    requireLogin();
    if ($_SESSION['role'] !== 'user') {
        header("Location: admin_page.php");
        exit();
    }
}

// ── Notification helper 
function addNotification($conn, $userId, $message, $type = 'info') {
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iss", $userId, $message, $type);
    $stmt->execute();
    $stmt->close();
}

// ── Unread counts for nav badges ─────────────────────────
function unreadMessages($conn, $userId) {
    $r = $conn->query("SELECT COUNT(*) c FROM messages WHERE receiver_id=$userId AND is_read=0");
    return $r->fetch_assoc()['c'];
}

function unreadNotifications($conn, $userId) {
    $r = $conn->query("SELECT COUNT(*) c FROM notifications WHERE user_id=$userId AND is_read=0");
    return $r->fetch_assoc()['c'];
}

// ── Upload directory (create if needed) ──────────────────
define('UPLOAD_DIR', __DIR__ . '/uploads/');
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
?>
