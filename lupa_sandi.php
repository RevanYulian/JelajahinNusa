<?php
// ============================================================
//  lupa_sandi.php — Kirim link reset password ke email user
// ============================================================
require_once 'config.php';
session_start();

$session = getSession();
if ($session) {
    redirect($session['type'] === 'admin' ? 'dashboardadmin.php' : 'index.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Masukkan alamat email yang valid.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Selalu tampilkan pesan sukses meski email tidak ditemukan (keamanan)
        if ($user) {
            // Buat token unik + waktu expired 1 jam
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);

            // Simpan token ke tabel password_resets
            // Buat tabelnya jika belum ada
            $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
                id        INT AUTO_INCREMENT PRIMARY KEY,
                user_id   VARCHAR(64) NOT NULL,
                token     VARCHAR(128) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                used      TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token),
                INDEX idx_user (user_id)
            )");

            // Hapus token lama milik user ini
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);

            // Simpan token baru
            $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)")
                ->execute([$user['id'], $token, $expires]);

            // Kirim email
            $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                       . '://' . $_SERVER['HTTP_HOST']
                       . dirname($_SERVER['PHP_SELF']) . '/reset_sandi.php?token=' . $token;

            $subject = 'Reset Kata Sandi - JelajahinNusa';
            $message = "Halo {$user['username']},\r\n\r\n"
                     . "Kami menerima permintaan reset kata sandi untuk akun Anda.\r\n\r\n"
                     . "Klik link berikut untuk membuat kata sandi baru (berlaku 1 jam):\r\n"
                     . $resetLink . "\r\n\r\n"
                     . "Jika Anda tidak meminta ini, abaikan email ini.\r\n\r\n"
                     . "Salam,\r\nTim JelajahinNusa";
            $headers = "From: noreply@jelajahinnusa.com\r\nReply-To: noreply@jelajahinnusa.com";

            mail($email, $subject, $message, $headers);
        }

        $success = 'Jika email terdaftar, link reset kata sandi sudah dikirim. Periksa kotak masuk Anda.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Kata Sandi - JelajahinNusa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { 'cream-light': '#F3F5E9', 'black-main': '#1F1F1F' } } }
        }
    </script>
    <style type="text/tailwindcss">
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        html, body { overflow: hidden; max-width: 100vw; }
        body {
            font-family: 'Inter', sans-serif;
            @apply bg-black-main flex flex-col md:flex-row antialiased h-screen;
        }
        .login-container {
            @apply w-full md:w-1/2 p-6 md:p-14 flex flex-col justify-center h-screen overflow-hidden;
        }
        .form-wrapper {
            @apply max-w-md w-full mx-auto flex flex-col justify-center;
        }
        .login-title  { @apply text-white text-3xl font-bold mb-3; text-align:center }
        .login-sub    { @apply text-gray-400 text-sm mb-8 text-center; }
        .error-msg    { @apply text-red-500 font-medium text-sm mb-4; }
        .success-msg  { @apply text-green-400 font-medium text-sm mb-4 bg-green-900/30 border border-green-700 rounded-xl px-4 py-3; }
        .form-space   { @apply space-y-4; }
        .input-field  {
            @apply w-full px-5 py-3 bg-cream-light rounded-xl text-black placeholder-gray-600 focus:ring-0;
        }
        .input-field:focus { outline:none; box-shadow:0 0 0 2px rgba(255,255,255,0.5); }
        .btn-submit   {
            @apply w-full bg-white text-black font-semibold text-lg py-3 rounded-xl shadow-lg hover:bg-gray-100 transition duration-150;
        }
        .footer-text  { @apply mt-6 text-center text-gray-400; }
        .link-green   { @apply text-green-500 font-semibold hover:text-green-400 transition; }
        .image-side   { @apply hidden md:block md:w-1/2 h-screen; }
        .bg-image     {
            background-image: url('Gambar/setengah.jpg');
            @apply relative w-full h-screen bg-cover bg-center;
        }
        .overlay      { @apply absolute inset-0 bg-black opacity-60; }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="form-wrapper">

            <h2 class="login-title">Lupa Kata Sandi?</h2>
            <p class="login-sub">Masukkan email Anda dan kami akan mengirimkan link untuk membuat kata sandi baru.</p>

            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-msg">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" action="lupa_sandi.php" class="form-space">
                <div>
                    <input type="email" name="email" placeholder="Alamat Email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="input-field">
                </div>

                <div class="h-4"></div>

                <button type="submit" class="btn-submit">
                    Kirim Link Reset
                </button>
            </form>
            <?php endif; ?>

            <div class="footer-text">
                Ingat kata sandi? <a href="login.php" class="link-green">Masuk</a>
            </div>

        </div>
    </div>

    <div class="image-side">
        <div class="bg-image">
            <div class="overlay"></div>
        </div>
    </div>

</body>
</html>
