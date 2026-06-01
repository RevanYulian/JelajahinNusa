<?php
// ============================================================
//  api.php — API Handler untuk semua request AJAX
//  Digunakan oleh Dashboard Admin (fetch/XHR dari JS)
// ============================================================
require_once 'config.php';
session_start();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ============================================================
// ---- USERS ----
// ============================================================

// GET api.php?action=get_users
if ($action === 'get_users') {
    requireAdmin();
    $pdo   = getDB();
    $users = $pdo->query("SELECT id, username, email, country, profile_photo, created_at FROM users WHERE type='user' ORDER BY created_at DESC")->fetchAll();
    jsonResponse(true, '', ['data' => $users]);
}

// GET api.php?action=get_user&id=xxx
if ($action === 'get_user') {
    requireAdmin();
    $id   = $_GET['id'] ?? '';
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id, username, email, country, profile_photo FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(false, 'Pengguna tidak ditemukan.');
    jsonResponse(true, '', ['data' => $user]);
}

// POST api.php?action=update_user
if ($action === 'update_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $id       = $_POST['id'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $country  = trim($_POST['country'] ?? 'Indonesia');
    if (!$id || !$username || !$email) jsonResponse(false, 'ID, username, dan email wajib diisi.');
    $pdo  = getDB();
    $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, country=?, updated_at=NOW() WHERE id=?");
    $stmt->execute([$username, $email, $country, $id]);
    jsonResponse(true, 'Pengguna berhasil diperbarui.');
}

// POST api.php?action=delete_user
if ($action === 'delete_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $id   = $_POST['id'] ?? '';
    if (!$id) jsonResponse(false, 'ID pengguna tidak valid.');
    $pdo  = getDB();
    $stmt = $pdo->prepare("DELETE FROM users WHERE id=? AND type='user'");
    $stmt->execute([$id]);
    jsonResponse(true, 'Pengguna berhasil dihapus.');
}

// ============================================================
// ---- STATS DASHBOARD ----
// ============================================================

// GET api.php?action=get_stats
if ($action === 'get_stats') {
    requireAdmin();
    $pdo  = getDB();
    $stats = $pdo->query("SELECT * FROM v_stats_dashboard")->fetch();
    jsonResponse(true, '', ['data' => $stats]);
}

// ============================================================
// ---- ARTIKEL ----
// ============================================================

// GET api.php?action=get_articles
if ($action === 'get_articles') {
    requireAdmin();
    $pdo      = getDB();
    $articles = $pdo->query("SELECT a.id, a.title, k.nama AS category, a.location, a.excerpt, a.image, a.file_html AS file, a.status, a.views, DATE_FORMAT(a.created_at,'%d %b %Y') AS date FROM artikel a JOIN kategori_artikel k ON a.kategori_id = k.id ORDER BY a.created_at DESC")->fetchAll();
    // Ambil juga daftar kategori untuk dropdown modal
    $categories = $pdo->query("SELECT id, nama FROM kategori_artikel ORDER BY nama")->fetchAll();
    jsonResponse(true, '', ['data' => $articles, 'categories' => $categories]);
}

// GET api.php?action=get_article&id=xxx
if ($action === 'get_article') {
    requireAdmin();
    $id   = $_GET['id'] ?? '';
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT a.*, k.nama AS category FROM artikel a JOIN kategori_artikel k ON a.kategori_id = k.id WHERE a.id=? LIMIT 1");
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    if (!$article) jsonResponse(false, 'Artikel tidak ditemukan.');
    jsonResponse(true, '', ['data' => $article]);
}

// POST api.php?action=add_article
if ($action === 'add_article' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $session     = getSession();
    $title       = trim($_POST['title'] ?? '');
    $kategori_id = (int)($_POST['kategori_id'] ?? 0);
    $location    = trim($_POST['location'] ?? '');
    $excerpt     = trim($_POST['excerpt'] ?? '');
    $image       = trim($_POST['image'] ?? 'Gambar/hero.jpg');
    $file_html   = trim($_POST['file'] ?? '#');
    $status      = in_array($_POST['status'] ?? '', ['published','draft']) ? $_POST['status'] : 'draft';
    if (!$title || !$kategori_id) jsonResponse(false, 'Judul dan kategori wajib diisi.');
    // Buat slug dari judul
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title)) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    $id   = bin2hex(random_bytes(16));
    $pdo  = getDB();
    $stmt = $pdo->prepare("INSERT INTO artikel (id, title, slug, kategori_id, location, excerpt, image, file_html, status, author_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
    $stmt->execute([$id, $title, $slug, $kategori_id, $location, $excerpt, $image, $file_html, $status, $session['id']]);
    jsonResponse(true, 'Artikel berhasil ditambahkan.');
}

// POST api.php?action=update_article
if ($action === 'update_article' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $id          = $_POST['id'] ?? '';
    $title       = trim($_POST['title'] ?? '');
    $kategori_id = (int)($_POST['kategori_id'] ?? 0);
    $location    = trim($_POST['location'] ?? '');
    $excerpt     = trim($_POST['excerpt'] ?? '');
    $image       = trim($_POST['image'] ?? 'Gambar/hero.jpg');
    $file_html   = trim($_POST['file'] ?? '#');
    $status      = in_array($_POST['status'] ?? '', ['published','draft']) ? $_POST['status'] : 'draft';
    if (!$id || !$title || !$kategori_id) jsonResponse(false, 'ID, judul, dan kategori wajib diisi.');
    $pdo  = getDB();
    $stmt = $pdo->prepare("UPDATE artikel SET title=?, kategori_id=?, location=?, excerpt=?, image=?, file_html=?, status=?, updated_at=NOW() WHERE id=?");
    $stmt->execute([$title, $kategori_id, $location, $excerpt, $image, $file_html, $status, $id]);
    jsonResponse(true, 'Artikel berhasil diperbarui.');
}

// POST api.php?action=delete_article
if ($action === 'delete_article' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $id   = $_POST['id'] ?? '';
    if (!$id) jsonResponse(false, 'ID artikel tidak valid.');
    $pdo  = getDB();
    $pdo->prepare("DELETE FROM artikel WHERE id=?")->execute([$id]);
    jsonResponse(true, 'Artikel berhasil dihapus.');
}

// ============================================================
// ---- ULASAN ----
// ============================================================

// GET api.php?action=get_reviews
if ($action === 'get_reviews') {
    requireAdmin();
    $pdo     = getDB();
    $reviews = $pdo->query("SELECT * FROM v_ulasan_lengkap ORDER BY created_at DESC")->fetchAll();
    jsonResponse(true, '', ['data' => $reviews]);
}

// POST api.php?action=update_review_status
if ($action === 'update_review_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $id     = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? '';
    if (!$id || !in_array($status, ['approved','rejected','pending'])) jsonResponse(false, 'Data tidak valid.');
    $pdo = getDB();
    $pdo->prepare("UPDATE ulasan SET status=?, updated_at=NOW() WHERE id=?")->execute([$status, $id]);
    jsonResponse(true, 'Status ulasan diperbarui.');
}

// POST api.php?action=delete_review
if ($action === 'delete_review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $id = $_POST['id'] ?? '';
    if (!$id) jsonResponse(false, 'ID ulasan tidak valid.');
    $pdo = getDB();
    $pdo->prepare("DELETE FROM ulasan WHERE id=?")->execute([$id]);
    jsonResponse(true, 'Ulasan berhasil dihapus.');
}

// ============================================================
// ---- ULASAN USER (kirim ulasan dari halaman artikel) ----
// ============================================================

// POST api.php?action=submit_review
if ($action === 'submit_review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    pingUserSession(); // Rekam aktivitas user untuk fitur "Online Sekarang"
    $session    = getSession();
    $artikel_id = trim($_POST['artikel_id'] ?? '');
    $rating     = (int)($_POST['rating'] ?? 5);
    $isi        = trim($_POST['isi_ulasan'] ?? '');
    if (!$artikel_id || !$isi) jsonResponse(false, 'Artikel dan isi ulasan wajib diisi.');
    if ($rating < 1 || $rating > 5) $rating = 5;
    $id  = bin2hex(random_bytes(16));
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO ulasan (id, user_id, artikel_id, rating, isi_ulasan, status, created_at) VALUES (?,?,?,?,?,'pending',NOW())");
    $stmt->execute([$id, $session['id'], $artikel_id, $rating, $isi]);
    jsonResponse(true, 'Ulasan dikirim dan menunggu persetujuan admin.');
}

// Jika action tidak dikenal
jsonResponse(false, 'Action tidak dikenal.');