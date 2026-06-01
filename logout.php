<?php
// ============================================================
//  logout.php — Handler Logout Tunggal (User & Admin)
// ============================================================
require_once 'config.php';
session_start();

// Hapus semua data sesi
$_SESSION = [];

// Hancurkan sesi yang berjalan
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Cukup arahkan ke satu halaman login gabungan
redirect('index.php');
?>