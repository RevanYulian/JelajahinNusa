<?php
// ============================================================
//  config.php — Konfigurasi koneksi database & konstanta
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Ganti dengan user MySQL Anda
define('DB_PASS', '');              // Ganti dengan password MySQL Anda
define('DB_NAME', 'jelajahinnusa');
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', 'http://localhost/jelajahinnusa/'); // Sesuaikan dengan URL proyek

// Batas waktu sesi (detik): 8 jam
define('SESSION_LIFETIME', 8 * 60 * 60);

// ---- Koneksi PDO ----
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Koneksi database gagal.']));
        }
    }
    return $pdo;
}

// ---- Helper: Respon JSON ----
function jsonResponse(bool $success, string $message = '', array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// ---- Helper: Redirect ----
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

// ---- Helper: Cek sesi aktif ----
function getSession(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $user = $_SESSION['user'] ?? null;
    if (!$user) return null;
    // Verifikasi is_active dari DB setiap request (supaya ban langsung efektif)
    if (isset($user['id']) && isset($user['type']) && $user['type'] !== 'admin') {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
            if (!$row || $row['is_active'] == 0) {
                session_destroy();
                return null;
            }
        } catch (\Exception $e) {
            // Kalau DB error, tetap lanjutkan (jangan blokir admin)
        }
    }
    return $user;
}

// ---- Helper: Guard halaman user ----
function requireLogin(): void {
    $user = getSession();
    if (!$user) redirect(BASE_URL . 'login.php');
    // Blokir user yang di-ban
    if (isset($user['is_active']) && $user['is_active'] == 0) {
        session_destroy();
        redirect(BASE_URL . 'login.php?banned=1');
    }
}

// ---- Helper: Guard halaman admin ----
function requireAdmin(): void {
    $user = getSession();
    if (!$user || $user['type'] !== 'admin') redirect(BASE_URL . 'loginAdmin.php');
}

// ---- Helper: Rekam / perbarui sesi aktif user ----
// Panggil ini di setiap halaman yang membutuhkan login user.
function pingUserSession(): void {
    $user = getSession();
    if (!$user || ($user['type'] ?? '') !== 'user') return;
    try {
        $pdo = getDB();
        $sid = session_id();
        $uid = $user['id'];
        $pdo->prepare(
            "INSERT INTO user_sessions (session_id, user_id, last_seen)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE last_seen = NOW(), user_id = ?"
        )->execute([$sid, $uid, $uid]);
    } catch (\Exception $e) { /* silent */ }
}

// ---- Helper: Jumlah user aktif (aktif dalam 15 menit terakhir) ----
function getActiveUserCount(): int {
    try {
        $pdo = getDB();
        return (int)$pdo->query(
            "SELECT COUNT(DISTINCT user_id) FROM user_sessions
             WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        )->fetchColumn();
    } catch (\Exception $e) {
        return 0;
    }
}

// ---- Helper: Baca pengaturan dari DB ----
function getSiteSetting(string $key, string $default = ''): string {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT nilai FROM pengaturan_situs WHERE kunci = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['nilai'] : $default;
    } catch (\Exception $e) {
        return $default;
    }
}