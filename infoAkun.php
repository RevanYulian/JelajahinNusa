<?php
// ============================================================
//  infoAkun.php — Halaman Profil & Pengaturan Akun User
// ============================================================
require_once 'config.php';
session_start();
requireLogin();
pingUserSession(); // Rekam aktivitas user untuk fitur "Online Sekarang"
$session = getSession();
$pdo     = getDB();

// ---- Ambil data user lengkap dari DB ----
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$session['id']]);
$user = $stmt->fetch();

// ---- Ambil preferensi notifikasi ----
$stmtN = $pdo->prepare("SELECT * FROM notifikasi_preferences WHERE user_id = ? LIMIT 1");
$stmtN->execute([$session['id']]);
$notif = $stmtN->fetch();

// ---- Ambil foto galeri milik user (pakai user_id, sesuai artikel_viewer.php) ----
$stmtG = $pdo->prepare("
    SELECT g.*, a.title AS judul_artikel, a.id AS art_id
    FROM galeri g
    LEFT JOIN artikel a ON a.id = g.artikel_id
    WHERE g.user_id = ?
    ORDER BY g.created_at DESC
");
$stmtG->execute([$session['id']]);
$userGaleri = $stmtG->fetchAll();

$toast     = '';
$toastType = 'success';

// Baca flash message dari session (hasil PRG redirect)
if (!empty($_SESSION['flash_msg'])) {
    $toast     = $_SESSION['flash_msg'];
    $toastType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// ============================================================
// POST Handler
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';

    // --- Simpan data akun ---
    if ($form === 'akun') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $country  = trim($_POST['country'] ?? 'Indonesia');

        if (!$username || !$email) {
            $toast = 'Username dan email wajib diisi.'; $toastType = 'error';
        } else {
            $ck = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=? LIMIT 1");
            $ck->execute([$email, $session['id']]);
            if ($ck->fetch()) {
                $toast = 'Email sudah digunakan akun lain.'; $toastType = 'error';
            } else {
                $pdo->prepare("UPDATE users SET username=?,email=?,country=?,updated_at=NOW() WHERE id=?")
                    ->execute([$username, $email, $country, $session['id']]);
                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['email']    = $email;
                $toast = 'Data akun berhasil diperbarui.';
                $stmt->execute([$session['id']]);
                $user = $stmt->fetch();
            }
        }
    }

    // --- Ganti password ---
    if ($form === 'password') {
        $old  = $_POST['password_lama'] ?? '';
        $new1 = $_POST['password_baru'] ?? '';
        $new2 = $_POST['password_konfirm'] ?? '';

        if (!$old || !$new1 || !$new2) {
            $toast = 'Semua field kata sandi wajib diisi.'; $toastType = 'error';
        } elseif (!password_verify($old, $user['password_hash'])) {
            $toast = 'Kata sandi lama tidak sesuai.'; $toastType = 'error';
        } elseif ($new1 !== $new2) {
            $toast = 'Konfirmasi kata sandi baru tidak cocok.'; $toastType = 'error';
        } elseif (strlen($new1) < 6) {
            $toast = 'Kata sandi baru minimal 6 karakter.'; $toastType = 'error';
        } else {
            $hash = password_hash($new1, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?")
                ->execute([$hash, $session['id']]);
            $toast = 'Kata sandi berhasil diperbarui.';
        }
    }

    // --- Simpan preferensi notifikasi ---
    if ($form === 'notifikasi') {
        $data = [
            'notif_email'          => isset($_POST['notif_email'])          ? 1 : 0,
            'notif_sistem'         => isset($_POST['notif_sistem'])         ? 1 : 0,
            'notif_populer'        => isset($_POST['notif_populer'])        ? 1 : 0,
            'notif_artikel_baru'   => isset($_POST['notif_artikel_baru'])   ? 1 : 0,
            'notif_balasan_ulasan' => isset($_POST['notif_balasan_ulasan']) ? 1 : 0,
        ];
        $pdo->prepare("INSERT INTO notifikasi_preferences (user_id, notif_email, notif_sistem, notif_populer, notif_artikel_baru, notif_balasan_ulasan)
                       VALUES (?, ?, ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE notif_email=VALUES(notif_email), notif_sistem=VALUES(notif_sistem),
                       notif_populer=VALUES(notif_populer), notif_artikel_baru=VALUES(notif_artikel_baru),
                       notif_balasan_ulasan=VALUES(notif_balasan_ulasan)")
            ->execute([$session['id'], $data['notif_email'], $data['notif_sistem'], $data['notif_populer'], $data['notif_artikel_baru'], $data['notif_balasan_ulasan']]);
        $stmtN->execute([$session['id']]);
        $notif = $stmtN->fetch();
        $toast = 'Preferensi notifikasi disimpan.';
    }

    // --- Hapus akun ---
    if ($form === 'hapus_akun') {
        $konfirm = $_POST['konfirm_hapus'] ?? '';
        if ($konfirm !== 'HAPUS') {
            $toast = 'Ketik HAPUS untuk konfirmasi penghapusan akun.'; $toastType = 'error';
        } else {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$session['id']]);
            session_destroy();
            redirect('login.php');
        }
    }

    // --- Hapus foto galeri milik sendiri ---
    if ($form === 'hapus_galeri') {
        $galeriId = trim($_POST['galeri_id'] ?? '');

        if (!$galeriId) {
            $toast = 'ID foto tidak valid.'; $toastType = 'error';
        } else {
            // Ambil data foto — pastikan milik user yang login (cek user_id)
            $cek = $pdo->prepare("SELECT id, image_path, user_id, artikel_id FROM galeri WHERE id = ? LIMIT 1");
            $cek->execute([$galeriId]);
            $foto = $cek->fetch();

            if (!$foto) {
                $toast = 'Foto tidak ditemukan.'; $toastType = 'error';
            } elseif ($foto['user_id'] !== $session['id']) {
                $toast = 'Kamu tidak memiliki izin untuk menghapus foto ini.'; $toastType = 'error';
            } else {
                $artId = $foto['artikel_id'];

                // Hapus dari database
                $pdo->prepare("DELETE FROM galeri WHERE id = ? AND user_id = ?")
                    ->execute([$galeriId, $session['id']]);

                // Rebuild gallery_json artikel terkait (jika ada)
                if ($artId) {
                    $approved = $pdo->prepare("SELECT id, image_path, judul FROM galeri WHERE artikel_id=? AND status='approved' AND user_id IS NOT NULL ORDER BY created_at ASC");
                    $approved->execute([$artId]);
                    $galArr = array_map(fn($r) => ['galeri_id'=>$r['id'],'src'=>$r['image_path'],'caption'=>$r['judul']??''], $approved->fetchAll(PDO::FETCH_ASSOC));
                    $pdo->prepare("UPDATE artikel SET gallery_json=?, updated_at=NOW() WHERE id=?")
                        ->execute([$galArr ? json_encode($galArr, JSON_UNESCAPED_UNICODE) : null, $artId]);
                }

                // Hapus file fisik
                $path = $foto['image_path'];
                if ($path && !str_starts_with($path, 'Gambar/') === false && file_exists($path)) {
                    @unlink($path);
                }

                $_SESSION['flash_msg']  = 'Foto berhasil dihapus.';
                $_SESSION['flash_type'] = 'success';
                header('Location: infoAkun.php?tab=galeri');
                exit;
            }
        }
    }

    // --- Upload / ganti foto profil ---
    if ($form === 'foto_profil') {
        if (empty($_FILES['foto_profil']['name'])) {
            $toast = 'Pilih foto terlebih dahulu.'; $toastType = 'error';
        } else {
            $file    = $_FILES['foto_profil'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            $maxSize = 3 * 1024 * 1024;

            if (!in_array($ext, $allowed)) {
                $toast = 'Format tidak didukung. Gunakan JPG, PNG, atau WEBP.'; $toastType = 'error';
            } elseif ($file['size'] > $maxSize) {
                $toast = 'Ukuran maksimal 3 MB.'; $toastType = 'error';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $toast = 'Gagal mengunggah file.'; $toastType = 'error';
            } else {
                $uploadDir = 'Gambar/profil/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $filename = 'profil_' . $session['id'] . '_' . time() . '.' . $ext;
                $destPath = $uploadDir . $filename;

                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    $toast = 'Gagal menyimpan foto.'; $toastType = 'error';
                } else {
                    $oldPhoto = $user['profile_photo'] ?? '';
                    if ($oldPhoto && str_starts_with($oldPhoto, 'Gambar/profil/') && file_exists($oldPhoto)) {
                        unlink($oldPhoto);
                    }
                    $pdo->prepare("UPDATE users SET profile_photo=?,updated_at=NOW() WHERE id=?")
                        ->execute([$destPath, $session['id']]);
                    $_SESSION['user']['profile_photo'] = $destPath;
                    $_SESSION['user']['photo']         = $destPath;
                    $_SESSION['flash_msg']  = 'Foto profil berhasil diperbarui!';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: infoAkun.php?tab=akun');
                    exit;
                }
            }
        }
    }
}

function sw(array $notif, string $key): string {
    return ($notif[$key] ?? 0) ? 'checked' : '';
}

// Hitung statistik galeri
$totalFoto    = count($userGaleri);
$fotoPending  = count(array_filter($userGaleri, fn($g) => ($g['status'] ?? '') === 'pending'));
$fotoApproved = count(array_filter($userGaleri, fn($g) => ($g['status'] ?? '') === 'approved'));
$fotoRejected = count(array_filter($userGaleri, fn($g) => ($g['status'] ?? '') === 'rejected'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Info Akun - JelajahinNusa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root{ --dark-bg:#000; --dark-bg-secondary:#1A1A1A; --text-primary:#FFF; --text-secondary:#AAA; --accent-green:#1F4529; }
        *{ margin:0; padding:0; box-sizing:border-box; }
        body{ font-family:'Poppins',Arial,sans-serif; background:var(--dark-bg); color:var(--text-primary); zoom:90%; }
        a{ text-decoration:none; color:inherit; }
        .account-page{ display:flex; min-height:100vh; }
        /* Sidebar Nav */
        .sidebar{ width:260px; background:var(--dark-bg-secondary); border-right:1px solid #2a2a2a; display:flex; flex-direction:column; justify-content:space-between; padding:30px 0; position:fixed; top:0; left:0; height:100vh; }
        .sidebar-top{ padding:0 20px; }
        .sidebar .logo{ font-size:20px; font-weight:600; margin-bottom:40px; }
        .sidebar h4{ color:var(--text-secondary); font-size:12px; text-transform:uppercase; letter-spacing:1px; padding:8px 0; margin-top:12px; }
        .nav-menu-sidebar a{ display:flex; align-items:center; padding:13px 16px; margin:3px 0; color:var(--text-secondary); transition:.25s; border-radius:8px; cursor:pointer; }
        .nav-menu-sidebar a:hover{ color:#fff; background:rgba(255,255,255,.05); }
        .nav-menu-sidebar a.active{ background:rgba(31,69,41,.4); color:#fff; font-weight:500; }
        .nav-menu-sidebar i{ margin-right:14px; width:18px; text-align:center; }
        .sidebar-bottom{ padding:0 20px; }
        .logout-link{ display:flex; align-items:center; padding:13px 16px; color:#e74c3c; border-radius:8px; font-weight:500; }
        .logout-link:hover{ background:rgba(231,76,60,.12); }
        .logout-link i{ margin-right:14px; }
        /* Main */
        .main-content{ margin-left:260px; flex-grow:1; padding:40px 50px; }
        .page-header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:36px; }
        .page-header h2{ font-size:26px; font-weight:600; }
        .back-link{ color:var(--text-secondary); font-size:14px; display:flex; align-items:center; gap:8px; transition:.2s; }
        .back-link:hover{ color:#fff; }
        /* Tabs */
        .tab-content{ display:none; }
        .tab-content.active{ display:block; }
        /* Profile section */
        .profile-section{ background:var(--dark-bg-secondary); border-radius:14px; padding:30px; max-width:600px; }
        .profile-section.wide{ max-width:1000px; }
        /* Profile picture editable */
        .profile-picture-wrap{ position:relative; width:80px; height:80px; margin-bottom:24px; cursor:pointer; }
        .profile-picture{ width:80px; height:80px; border-radius:50%; background:#333; display:flex; justify-content:center; align-items:center; font-size:34px; color:#aaa; overflow:hidden; }
        .profile-picture img{ width:100%; height:100%; object-fit:cover; }
        .profile-picture-overlay{ position:absolute; inset:0; border-radius:50%; background:rgba(0,0,0,.55); display:flex; flex-direction:column; align-items:center; justify-content:center; opacity:0; transition:.25s; gap:3px; }
        .profile-picture-wrap:hover .profile-picture-overlay{ opacity:1; }
        .profile-picture-overlay i{ font-size:16px; color:#fff; }
        .profile-picture-overlay span{ font-size:10px; color:#fff; font-weight:500; }
        .profile-picture-wrap input[type=file]{ display:none; }
        /* Field editable */
        .input-edit-wrap{ position:relative; display:flex; align-items:center; }
        .input-edit-wrap .edit-btn{ position:absolute; right:12px; background:none; border:none; color:var(--text-secondary); cursor:pointer; font-size:14px; padding:4px; transition:.2s; }
        .input-edit-wrap .edit-btn:hover{ color:#fff; }
        .input-edit-wrap .input-field.locked{ opacity:.5; cursor:not-allowed; }
        .input-edit-wrap .input-field.unlocked{ opacity:1; cursor:text; border-color:var(--accent-green); }
        .input-edit-wrap .edit-btn.active{ color:#6fcf97; }
        /* Form */
        .form-row{ margin-bottom:20px; }
        .form-row label{ display:block; font-size:13px; color:var(--text-secondary); margin-bottom:7px; }
        .input-group{ position:relative; display:flex; align-items:center; }
        .input-field{ width:100%; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.12); border-radius:9px; padding:12px 16px; color:#fff; font-family:'Poppins',sans-serif; font-size:15px; outline:none; transition:border-color .25s; }
        .input-field:focus{ border-color:var(--accent-green); }
        .input-field[readonly]{ opacity:.5; cursor:not-allowed; }
        select.input-field{ appearance:none; cursor:pointer; }
        .toggle-password{ position:absolute; right:14px; cursor:pointer; color:var(--text-secondary); }
        .btn-save{ background:var(--accent-green); color:#fff; border:none; padding:11px 28px; border-radius:9px; cursor:pointer; font-weight:600; font-family:'Poppins',sans-serif; font-size:14px; margin-top:10px; transition:.25s; }
        .btn-save:hover{ background:#2e623b; }
        .btn-danger{ background:#a00; color:#fff; border:none; padding:11px 28px; border-radius:9px; cursor:pointer; font-weight:600; font-family:'Poppins',sans-serif; font-size:14px; margin-top:10px; transition:.25s; }
        .btn-danger:hover{ background:#c0392b; }
        /* Security items */
        .security-item{ display:flex; align-items:center; justify-content:space-between; padding:18px 0; border-bottom:1px solid rgba(255,255,255,.07); gap:20px; }
        .security-item:last-of-type{ border-bottom:none; }
        .item-details h4{ font-size:15px; font-weight:600; margin-bottom:4px; }
        .item-details p{ font-size:13px; color:var(--text-secondary); }
        .action-button{ background:rgba(255,255,255,.1); color:#fff; border:1px solid rgba(255,255,255,.2); padding:9px 18px; border-radius:8px; cursor:pointer; font-family:'Poppins',sans-serif; font-size:13px; transition:.2s; }
        .action-button:hover{ background:rgba(255,255,255,.2); }
        .modal-pw{ display:none; margin-top:18px; background:rgba(255,255,255,.04); border-radius:10px; padding:20px; border:1px solid rgba(255,255,255,.08); }
        .modal-pw.open{ display:block; }
        /* Toggle Switch */
        .switch{ position:relative; display:inline-block; width:46px; height:22px; }
        .switch input{ opacity:0; width:0; height:0; }
        .slider{ position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:#555; transition:.3s; border-radius:22px; }
        .slider:before{ position:absolute; content:""; height:14px; width:14px; left:4px; bottom:4px; background:#fff; transition:.3s; border-radius:50%; }
        input:checked + .slider{ background:var(--accent-green); }
        input:checked + .slider:before{ transform:translateX(24px); }
        /* Notification items */
        .notification-item{ display:flex; align-items:center; justify-content:space-between; padding:16px 0; border-bottom:1px solid rgba(255,255,255,.07); gap:20px; }
        .notification-item:last-of-type{ border-bottom:none; }
        .item-info h3{ font-size:15px; font-weight:600; margin-bottom:3px; }
        .item-info p{ font-size:13px; color:var(--text-secondary); }
        /* Stats galeri */
        .galeri-stats{ display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
        .gstat-box{ background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:14px 16px; text-align:center; cursor:pointer; transition:.2s; }
        .gstat-box:hover{ background:rgba(255,255,255,.08); }
        .gstat-box.active{ border-color:rgba(111,207,151,.4); background:rgba(111,207,151,.07); }
        .gstat-box .gnum{ font-size:22px; font-weight:700; }
        .gstat-box .glabel{ font-size:11px; color:#888; margin-top:3px; }
        /* Gallery */
        .gallery-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; }
        .gallery-card{ border-radius:10px; overflow:hidden; position:relative; aspect-ratio:1; background:#222; }
        .gallery-card img{ width:100%; height:100%; object-fit:cover; display:block; transition:transform .3s; }
        .gallery-card:hover img{ transform:scale(1.04); }
        .gallery-caption{ position:absolute; bottom:0; left:0; right:0; padding:8px 12px; background:rgba(0,0,0,.65); font-size:12px; }
        /* Status badge pada foto */
        .foto-status-badge{ position:absolute; top:8px; left:8px; padding:3px 8px; border-radius:20px; font-size:10px; font-weight:700; letter-spacing:.4px; }
        .foto-status-badge.pending  { background:rgba(243,156,18,.85); color:#fff; }
        .foto-status-badge.approved { background:rgba(39,174,96,.85); color:#fff; }
        .foto-status-badge.rejected { background:rgba(192,57,43,.85); color:#fff; }
        .options-btn{ position:absolute; top:8px; right:8px; background:rgba(0,0,0,.6); border:none; color:#fff; width:28px; height:28px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; z-index:2; }
        .card-dropdown{ display:none; position:absolute; top:40px; right:8px; background:#222; border:1px solid #333; border-radius:8px; z-index:10; min-width:160px; box-shadow:0 8px 24px rgba(0,0,0,.5); }
        .card-dropdown.active{ display:block; }
        .card-dropdown a{ display:flex; align-items:center; gap:8px; padding:9px 14px; font-size:13px; color:#ccc; transition:.2s; }
        .card-dropdown a:hover{ color:#fff; background:rgba(255,255,255,.05); }
        .card-dropdown a.view-artikel{ color:#6fcf97; }
        .card-dropdown a.view-artikel:hover{ color:#8fefb7; }
        /* Modal Konfirmasi Hapus */
        .modal-upload-overlay{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.8); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
        .modal-upload-overlay.open{ display:flex; }
        .modal-upload-box{ background:#1a1a1a; border:1px solid #2e2e2e; border-radius:18px; padding:36px; width:90%; max-width:440px; max-height:90vh; overflow-y:auto; box-shadow:0 24px 60px rgba(0,0,0,.7); animation:modalIn .25s ease; }
        @keyframes modalIn{ from{opacity:0;transform:translateY(20px) scale(.97)} to{opacity:1;transform:translateY(0) scale(1)} }
        .modal-upload-box h3{ font-size:20px; font-weight:700; margin-bottom:22px; display:flex; justify-content:space-between; align-items:center; }
        .modal-upload-box h3 button{ background:none; border:none; color:#aaa; font-size:22px; cursor:pointer; line-height:1; padding:0; }
        .modal-upload-box h3 button:hover{ color:#fff; }
        /* Header galeri */
        .galeri-header{ display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
        .galeri-header h3{ font-size:17px; font-weight:600; }
        .galeri-header-note{ font-size:13px; color:#666; margin-top:4px; line-height:1.5; }
        .galeri-filter-bar{ display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
        .galeri-filter-btn{ background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); color:#aaa; padding:7px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-family:'Poppins',sans-serif; transition:.2s; }
        .galeri-filter-btn:hover{ color:#fff; border-color:rgba(255,255,255,.25); }
        .galeri-filter-btn.active{ background:rgba(31,69,41,.4); color:#fff; border-color:rgba(111,207,151,.35); }
        /* Artikel link badge */
        .artikel-link-badge{ display:inline-flex; align-items:center; gap:5px; font-size:10px; color:#6fcf97; background:rgba(111,207,151,.12); border:1px solid rgba(111,207,151,.25); padding:2px 8px; border-radius:20px; margin-top:3px; }
        /* Toast */
        .toast{ position:fixed; bottom:30px; right:30px; background:#1F4529; color:#fff; padding:14px 22px; border-radius:10px; font-size:14px; z-index:99999; opacity:0; transform:translateY(20px); transition:.3s; pointer-events:none; }
        .toast.show{ opacity:1; transform:translateY(0); }
        .toast.error{ background:#C0392B; }
        /* Bottom section */
        .bottom-section{ display:flex; justify-content:space-between; align-items:center; margin-top:28px; padding-top:18px; border-top:1px solid rgba(255,255,255,.08); }
        .footer-links a{ color:var(--text-secondary); font-size:13px; margin-left:18px; }
        .footer-links a:hover{ color:#fff; }
        /* Modal actions */
        .modal-actions-upload{ display:flex; gap:10px; margin-top:6px; }
        .btn-cancel-upload{ background:rgba(255,255,255,.08); color:#aaa; border:1px solid #333; padding:12px 20px; border-radius:9px; font-family:'Poppins',sans-serif; font-size:14px; cursor:pointer; transition:.25s; }
        .btn-cancel-upload:hover{ color:#fff; background:rgba(255,255,255,.12); }
    </style>
</head>
<body>

<div class="account-page">
    <aside class="sidebar">
        <div class="sidebar-top">
            <a href="index.php" class="logo">JelajahinNusa</a>
            <h4>Akun Saya</h4>
            <nav class="nav-menu-sidebar" id="sidebarNav">
                <a data-tab="akun" class="active"><i class="fas fa-user-circle"></i> <span>Akun</span></a>
                <a data-tab="keamanan"><i class="fas fa-lock"></i> <span>Keamanan & Privasi</span></a>
                <a data-tab="notifikasi"><i class="fas fa-bell"></i> <span>Notifikasi</span></a>
                <a data-tab="galeri"><i class="fas fa-images"></i> <span>Manajemen Galeri</span></a>
            </nav>
        </div>
        <div class="sidebar-bottom">
            <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Keluar</a>
        </div>
    </aside>

    <div class="main-content">
        <div class="page-header">
            <h2>Pengaturan Akun</h2>
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
        </div>

        <?php if ($toast): ?>
        <div id="serverToast" data-msg="<?= htmlspecialchars($toast) ?>" data-type="<?= $toastType ?>"></div>
        <?php endif; ?>

        <!-- ============ TAB: AKUN ============ -->
        <div id="tab-akun" class="tab-content active">
            <div class="profile-section">
                <form method="POST" action="infoAkun.php?tab=akun" enctype="multipart/form-data" id="formFotoProfil">
                    <input type="hidden" name="form" value="foto_profil">
                    <div class="profile-picture-wrap" onclick="document.getElementById('inputFotoProfil').click()" title="Klik untuk ganti foto profil">
                        <div class="profile-picture">
                            <?php if ($user['profile_photo']): ?>
                                <img src="<?= htmlspecialchars($user['profile_photo']) ?>" alt="Foto Profil" id="previewFotoProfil">
                            <?php else: ?>
                                <i class="fas fa-user" id="iconFotoProfil"></i>
                            <?php endif; ?>
                        </div>
                        <div class="profile-picture-overlay">
                            <i class="fas fa-camera"></i>
                            <span>Ganti</span>
                        </div>
                        <input type="file" name="foto_profil" id="inputFotoProfil" accept="image/*"
                               onchange="previewProfil(this)">
                    </div>
                </form>

                <form method="POST" action="infoAkun.php?tab=akun">
                    <input type="hidden" name="form" value="akun">

                    <div class="form-row">
                        <label>Nama Pengguna</label>
                        <div class="input-edit-wrap">
                            <input type="text" name="username" id="fUsername" class="input-field locked" readonly
                                   value="<?= htmlspecialchars($user['username']) ?>" style="padding-right:42px">
                            <button type="button" class="edit-btn" onclick="toggleEdit('fUsername',this)" title="Edit">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-row">
                        <label>ID Pengguna</label>
                        <input type="text" class="input-field" readonly value="<?= htmlspecialchars($user['id']) ?>" style="opacity:.4;cursor:not-allowed;">
                    </div>

                    <div class="form-row">
                        <label>E-mail</label>
                        <div class="input-edit-wrap">
                            <input type="email" name="email" id="fEmail" class="input-field locked" readonly
                                   value="<?= htmlspecialchars($user['email']) ?>" style="padding-right:42px">
                            <button type="button" class="edit-btn" onclick="toggleEdit('fEmail',this)" title="Edit">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-row">
                        <label>Negara</label>
                        <div class="input-edit-wrap">
                            <select name="country" id="fCountry" class="input-field locked" disabled
                                    onchange="document.getElementById('hCountry').value=this.value" style="padding-right:42px">
                                <?php
                                $countries = ['Indonesia','Malaysia','Singapura','Thailand','Filipina','Vietnam','Australia','Jepang','Korea Selatan','Lainnya'];
                                foreach ($countries as $c):
                                    $sel = ($user['country'] === $c) ? 'selected' : '';
                                ?>
                                <option value="<?= $c ?>" <?= $sel ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="country" id="hCountry" value="<?= htmlspecialchars($user['country'] ?? 'Indonesia') ?>">
                            <button type="button" class="edit-btn" onclick="toggleEditSelect('fCountry',this)" title="Edit">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-save">Simpan Perubahan</button>
                </form>

                <div class="bottom-section">
                    <div></div>
                    <div class="footer-links">
                        <a href="tentangKami.php">Tentang Kami</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ TAB: KEAMANAN ============ -->
        <div id="tab-keamanan" class="tab-content">
            <div class="profile-section">
                <div class="security-item">
                    <div class="item-details">
                        <h4>Ubah Kata Sandi</h4>
                        <p>Perbarui kata sandi Anda secara berkala untuk menjaga keamanan akun.</p>
                    </div>
                    <button class="action-button" onclick="document.getElementById('formGantiPw').classList.toggle('open')">
                        Perbarui
                    </button>
                </div>

                <div class="modal-pw" id="formGantiPw">
                    <form method="POST" action="infoAkun.php?tab=keamanan">
                        <input type="hidden" name="form" value="password">
                        <div class="form-row">
                            <label>Kata Sandi Lama</label>
                            <div class="input-group">
                                <input type="password" name="password_lama" class="input-field" placeholder="Masukkan kata sandi lama" id="pwLama">
                                <i class="fas fa-eye-slash toggle-password" onclick="togglePw('pwLama',this)"></i>
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Kata Sandi Baru</label>
                            <div class="input-group">
                                <input type="password" name="password_baru" class="input-field" placeholder="Minimal 6 karakter" id="pwBaru">
                                <i class="fas fa-eye-slash toggle-password" onclick="togglePw('pwBaru',this)"></i>
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Konfirmasi Kata Sandi Baru</label>
                            <div class="input-group">
                                <input type="password" name="password_konfirm" class="input-field" placeholder="Ulangi kata sandi baru" id="pwKonfirm">
                                <i class="fas fa-eye-slash toggle-password" onclick="togglePw('pwKonfirm',this)"></i>
                            </div>
                        </div>
                        <button type="submit" class="btn-save">Simpan Kata Sandi</button>
                    </form>
                </div>

                <div class="security-item">
                    <div class="item-details">
                        <h4>Autentikasi Dua Faktor (2FA)</h4>
                        <p>Tambahkan lapisan keamanan ekstra pada akun Anda.</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="security-item">
                    <div class="item-details">
                        <h4>Hapus Akun</h4>
                        <p>Tindakan ini bersifat permanen dan tidak dapat dibatalkan.</p>
                    </div>
                    <button class="action-button" style="background:#A00;border-color:#A00;"
                            onclick="document.getElementById('formHapusAkun').classList.toggle('open')">
                        Hapus Akun
                    </button>
                </div>

                <div class="modal-pw" id="formHapusAkun">
                    <form method="POST" action="infoAkun.php?tab=keamanan">
                        <input type="hidden" name="form" value="hapus_akun">
                        <div class="form-row">
                            <label>Ketik <strong>HAPUS</strong> untuk konfirmasi</label>
                            <input type="text" name="konfirm_hapus" class="input-field" placeholder="HAPUS">
                        </div>
                        <button type="submit" class="btn-danger">Hapus Akun Saya</button>
                    </form>
                </div>

                <div class="bottom-section">
                    <div></div>
                    <div class="footer-links"><a href="tentangKami.php">Tentang Kami</a></div>
                </div>
            </div>
        </div>

        <!-- ============ TAB: NOTIFIKASI ============ -->
        <div id="tab-notifikasi" class="tab-content">
            <div class="profile-section">
                <form method="POST" action="infoAkun.php?tab=notifikasi">
                    <input type="hidden" name="form" value="notifikasi">

                    <div class="notification-item">
                        <div class="item-info">
                            <h3>Notifikasi Email</h3>
                            <p>Terima rangkuman mingguan dan rekomendasi destinasi lewat email.</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notif_email" <?= sw($notif??[], 'notif_email') ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="notification-item">
                        <div class="item-info">
                            <h3>Pembaruan Sistem &amp; Keamanan</h3>
                            <p>Dapatkan pemberitahuan penting terkait akun dan keamanan.</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notif_sistem" <?= sw($notif??[], 'notif_sistem') ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="notification-item">
                        <div class="item-info">
                            <h3>Notifikasi Populer</h3>
                            <p>Beritahu saya tentang destinasi dan unggahan yang sedang tren.</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notif_populer" <?= sw($notif??[], 'notif_populer') ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="notification-item">
                        <div class="item-info">
                            <h3>Artikel Baru</h3>
                            <p>Beri tahu saya ketika ada artikel wisata baru diterbitkan.</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notif_artikel_baru" <?= sw($notif??[], 'notif_artikel_baru') ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="notification-item">
                        <div class="item-info">
                            <h3>Balasan Ulasan</h3>
                            <p>Beri tahu saya saat ulasan saya mendapat respon.</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notif_balasan_ulasan" <?= sw($notif??[], 'notif_balasan_ulasan') ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <button type="submit" class="btn-save" style="margin-top:20px">Simpan Preferensi</button>
                </form>

                <div class="bottom-section">
                    <div></div>
                    <div class="footer-links"><a href="tentangKami.php">Tentang Kami</a></div>
                </div>
            </div>
        </div>

        <!-- ============ TAB: GALERI ============ -->
        <div id="tab-galeri" class="tab-content">
            <div class="profile-section wide">

                <!-- Statistik foto -->
                <div class="galeri-stats">
                    <div class="gstat-box active" id="gstat-semua" onclick="filterGaleri('semua')">
                        <div class="gnum"><?= $totalFoto ?></div>
                        <div class="glabel">Semua Foto</div>
                    </div>
                    <div class="gstat-box" id="gstat-approved" onclick="filterGaleri('approved')">
                        <div class="gnum" style="color:#27AE60"><?= $fotoApproved ?></div>
                        <div class="glabel">Disetujui</div>
                    </div>
                    <div class="gstat-box" id="gstat-pending" onclick="filterGaleri('pending')">
                        <div class="gnum" style="color:#F39C12"><?= $fotoPending ?></div>
                        <div class="glabel">Menunggu Review</div>
                    </div>
                    <div class="gstat-box" id="gstat-rejected" onclick="filterGaleri('rejected')">
                        <div class="gnum" style="color:#E74C3C"><?= $fotoRejected ?></div>
                        <div class="glabel">Ditolak</div>
                    </div>
                </div>

                <!-- Header galeri -->
                <div class="galeri-header">
                    <div>
                        <h3>Foto Saya</h3>
                        <p class="galeri-header-note">
                            Untuk menambah foto, buka halaman artikel dan klik
                            <strong style="color:#aaa;">Bagikan Pengalaman Anda</strong>.
                            Foto akan tampil di galeri artikel setelah disetujui admin.
                        </p>
                    </div>
                    <a href="artikel.php" style="display:inline-flex;align-items:center;gap:8px;background:var(--accent-green);color:#fff;border:none;padding:10px 20px;border-radius:9px;font-weight:600;font-size:14px;white-space:nowrap;">
                        <i class="fas fa-plus"></i> Tambah Foto
                    </a>
                </div>

                <!-- Filter bar -->
                <div class="galeri-filter-bar">
                    <button class="galeri-filter-btn active" id="fbtn-semua"    onclick="filterGaleri('semua')">Semua</button>
                    <button class="galeri-filter-btn"        id="fbtn-approved" onclick="filterGaleri('approved')">✓ Disetujui</button>
                    <button class="galeri-filter-btn"        id="fbtn-pending"  onclick="filterGaleri('pending')">⏳ Menunggu</button>
                    <button class="galeri-filter-btn"        id="fbtn-rejected" onclick="filterGaleri('rejected')">✕ Ditolak</button>
                </div>

                <div class="gallery-grid" id="galeriGrid">
                    <?php foreach ($userGaleri as $g): ?>
                    <?php
                        $status      = $g['status'] ?? 'pending';
                        $judul_art   = $g['judul_artikel'] ?? '';
                        $art_id      = $g['art_id'] ?? $g['artikel_id'] ?? '';
                        $statusLabel = ['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak'][$status] ?? $status;
                    ?>
                    <div class="gallery-card" id="card-<?= htmlspecialchars($g['id']) ?>" data-status="<?= $status ?>">
                        <!-- Status badge -->
                        <span class="foto-status-badge <?= $status ?>"><?= $statusLabel ?></span>

                        <!-- Options dropdown -->
                        <button class="options-btn" onclick="toggleDropCard('<?= $g['id'] ?>', event)">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="card-dropdown" id="dd-<?= htmlspecialchars($g['id']) ?>">
                            <?php if ($art_id): ?>
                            <a href="artikel_viewer.php?id=<?= urlencode($art_id) ?>" class="view-artikel">
                                <i class="fas fa-external-link-alt"></i> Lihat di Artikel
                            </a>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($g['image_path']) ?>" download>
                                <i class="fas fa-download"></i> Unduh
                            </a>
                            <a href="#" onclick="konfirmasiHapus('<?= htmlspecialchars($g['id']) ?>', '<?= htmlspecialchars(addslashes($g['judul'] ?? 'foto ini')) ?>');return false;" style="color:#ff5252;">
                                <i class="fas fa-trash-alt"></i> Hapus
                            </a>
                        </div>

                        <img src="<?= htmlspecialchars($g['image_path']) ?>"
                             alt="<?= htmlspecialchars($g['judul'] ?? '') ?>"
                             onerror="this.src='Gambar/logo2.png'">

                        <div class="gallery-caption">
                            <div><?= htmlspecialchars($g['judul'] ?? $g['lokasi'] ?? 'Foto') ?></div>
                            <?php if ($judul_art): ?>
                            <div class="artikel-link-badge">
                                <i class="fas fa-file-alt" style="font-size:9px"></i>
                                <?= htmlspecialchars(mb_strimwidth($judul_art, 0, 28, '…')) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($userGaleri)): ?>
                    <div id="emptyState" style="grid-column:1/-1;text-align:center;padding:60px 20px;color:#555;">
                        <i class="fas fa-images" style="font-size:48px;margin-bottom:14px;display:block;"></i>
                        <p style="font-size:15px;margin-bottom:6px;color:#aaa;">Belum ada foto yang diunggah</p>
                        <p style="font-size:13px;">Buka halaman <a href="artikel.php" style="color:#6fcf97;">Artikel</a>, lalu klik <strong>Bagikan Pengalaman Anda</strong> untuk mulai berbagi.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Empty state saat filter kosong -->
                <div id="filterEmptyState" style="display:none;text-align:center;padding:50px 20px;color:#555;">
                    <i class="fas fa-filter" style="font-size:36px;margin-bottom:12px;display:block;"></i>
                    <p style="color:#aaa;font-size:14px;">Tidak ada foto dengan status ini.</p>
                </div>

                <div class="bottom-section">
                    <div></div>
                    <div class="footer-links"><a href="tentangKami.php">Tentang Kami</a></div>
                </div>
            </div>
        </div>

    </div><!-- /.main-content -->
</div>

<!-- ===== FORM HAPUS FOTO (hidden, submit via JS) ===== -->
<form method="POST" action="infoAkun.php?tab=galeri" id="formHapusGaleri" style="display:none;">
    <input type="hidden" name="form" value="hapus_galeri">
    <input type="hidden" name="galeri_id" id="hapusGaleriId" value="">
</form>

<!-- ===== MODAL KONFIRMASI HAPUS ===== -->
<div class="modal-upload-overlay" id="modalKonfirmasiHapus">
    <div class="modal-upload-box" style="max-width:400px;text-align:center;">
        <div style="font-size:46px;color:#e74c3c;margin-bottom:16px;">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h3 style="justify-content:center;font-size:18px;margin-bottom:10px;">Hapus Foto?</h3>
        <p id="hapusNamaFoto" style="color:#aaa;font-size:14px;margin-bottom:26px;line-height:1.6;">
            Foto ini akan dihapus permanen dan tidak bisa dikembalikan.
        </p>
        <div class="modal-actions-upload">
            <button type="button" class="btn-cancel-upload"
                    onclick="document.getElementById('modalKonfirmasiHapus').classList.remove('open');document.body.style.overflow='';">
                Batal
            </button>
            <button type="button"
                    onclick="document.getElementById('formHapusGaleri').submit();"
                    style="flex:1;background:#c0392b;color:#fff;border:none;padding:12px;border-radius:9px;font-weight:600;font-family:'Poppins',sans-serif;font-size:14px;cursor:pointer;">
                <i class="fas fa-trash-alt"></i> Ya, Hapus
            </button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
    // ---- Tab switching ----
    const activeTab = '<?= htmlspecialchars($_GET['tab'] ?? 'akun') ?>';
    document.querySelectorAll('#sidebarNav a').forEach(link => {
        link.addEventListener('click', function(e){
            e.preventDefault();
            const tab = this.dataset.tab;
            document.querySelectorAll('#sidebarNav a').forEach(a => a.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
            // Update URL tanpa reload
            history.replaceState(null, '', 'infoAkun.php?tab=' + tab);
        });
    });
    // Restore active tab from URL
    if (activeTab) {
        document.querySelectorAll('#sidebarNav a').forEach(a => {
            a.classList.toggle('active', a.dataset.tab === activeTab);
        });
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        const el = document.getElementById('tab-' + activeTab);
        if (el) el.classList.add('active');
    }

    // ---- Toast dari server ----
    const serverToast = document.getElementById('serverToast');
    if (serverToast) {
        const msg  = serverToast.dataset.msg;
        const type = serverToast.dataset.type;
        const t    = document.getElementById('toast');
        t.textContent = msg;
        t.className   = 'toast show' + (type === 'error' ? ' error' : '');
        setTimeout(() => t.className = 'toast', 3500);
    }

    // ---- Toggle password visibility ----
    function togglePw(id, icon) {
        const f = document.getElementById(id);
        const show = f.type === 'password';
        f.type = show ? 'text' : 'password';
        icon.classList.toggle('fa-eye-slash', !show);
        icon.classList.toggle('fa-eye', show);
    }

    // ---- Dropdown galeri ----
    function toggleDropCard(id, e) {
        e.stopPropagation();
        const dd = document.getElementById('dd-' + id);
        document.querySelectorAll('.card-dropdown').forEach(d => { if (d !== dd) d.classList.remove('active'); });
        dd.classList.toggle('active');
    }
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.options-btn')) {
            document.querySelectorAll('.card-dropdown').forEach(d => d.classList.remove('active'));
        }
    });

    // ---- Filter galeri berdasarkan status ----
    function filterGaleri(status) {
        // Update stat boxes
        ['semua','approved','pending','rejected'].forEach(s => {
            document.getElementById('gstat-'+s)?.classList.toggle('active', s === status);
            document.getElementById('fbtn-'+s)?.classList.toggle('active', s === status);
        });

        let visibleCount = 0;
        document.querySelectorAll('#galeriGrid .gallery-card').forEach(card => {
            const cardStatus = card.dataset.status;
            const show = status === 'semua' || cardStatus === status;
            card.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        // Show/hide empty state
        const emptyEl = document.getElementById('filterEmptyState');
        const mainEmpty = document.getElementById('emptyState');
        if (emptyEl) emptyEl.style.display = (visibleCount === 0 && !mainEmpty) ? 'block' : 'none';
    }

    // ---- Konfirmasi & hapus foto galeri ----
    function konfirmasiHapus(id, judul) {
        document.getElementById('hapusGaleriId').value = id;
        document.getElementById('hapusNamaFoto').textContent =
            'Foto "' + judul + '" akan dihapus permanen dan tidak bisa dikembalikan.';
        document.getElementById('modalKonfirmasiHapus').classList.add('open');
        document.body.style.overflow = 'hidden';
        document.querySelectorAll('.card-dropdown').forEach(d => d.classList.remove('active'));
    }
    document.getElementById('modalKonfirmasiHapus').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
            document.body.style.overflow = '';
        }
    });

    // ---- Preview & auto-submit foto profil ----
    function previewProfil(input) {
        if (!input.files || !input.files[0]) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const pic = document.querySelector('.profile-picture');
            pic.innerHTML = '<img src="' + e.target.result + '" alt="Preview" id="previewFotoProfil">';
            document.getElementById('formFotoProfil').submit();
        };
        reader.readAsDataURL(input.files[0]);
    }

    // ---- Toggle edit field teks ----
    function toggleEdit(fieldId, btn) {
        const field = document.getElementById(fieldId);
        const isLocked = field.readOnly;
        field.readOnly = !isLocked;
        field.classList.toggle('locked', isLocked);
        field.classList.toggle('unlocked', !isLocked);
        btn.classList.toggle('active', !isLocked);
        const icon = btn.querySelector('i');
        icon.className = isLocked ? 'fas fa-check' : 'fas fa-pencil-alt';
        if (!isLocked) field.focus();
    }

    // ---- Toggle edit select (negara) ----
    function toggleEditSelect(fieldId, btn) {
        const field = document.getElementById(fieldId);
        const hidden = document.getElementById('hCountry');
        const isDisabled = field.disabled;
        field.disabled = !isDisabled;
        field.classList.toggle('locked', isDisabled);
        field.classList.toggle('unlocked', !isDisabled);
        btn.classList.toggle('active', !isDisabled);
        const icon = btn.querySelector('i');
        icon.className = isDisabled ? 'fas fa-check' : 'fas fa-pencil-alt';
        if (hidden) hidden.value = field.value;
    }
</script>
</body>
</html>