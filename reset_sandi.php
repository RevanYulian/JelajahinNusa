<?php
// ============================================================
//  reset_sandi.php — Form buat kata sandi baru via token email
// ============================================================
require_once 'config.php';
session_start();

$session = getSession();
if ($session) {
    redirect($session['type'] === 'admin' ? 'dashboardadmin.php' : 'index.php');
}

$token  = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error  = '';
$success = '';
$validToken = false;
$userId = null;

$pdo = getDB();

// ── Validasi token ──
if ($token) {
    try {
        $stmt = $pdo->prepare("
            SELECT pr.user_id, u.username, u.email
            FROM password_resets pr
            JOIN users u ON u.id = pr.user_id
            WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if ($row) {
            $validToken = true;
            $userId     = $row['user_id'];
        } else {
            $error = 'Link tidak valid atau sudah kedaluwarsa. Silakan minta ulang.';
        }
    } catch (\Exception $e) {
        $error = 'Terjadi kesalahan. Silakan coba lagi.';
    }
} else {
    $error = 'Token tidak ditemukan.';
}

// ── Proses simpan password baru ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Kata sandi minimal 8 karakter.';
    } elseif ($password !== $password2) {
        $error = 'Konfirmasi kata sandi tidak cocok.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$hash, $userId]);

        // Tandai token sudah digunakan
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")
            ->execute([$token]);

        $success = 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru Anda.';
        $validToken = false; // Sembunyikan form
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Kata Sandi - JelajahinNusa</title>
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
        .form-wrapper  { @apply max-w-md w-full mx-auto flex flex-col justify-center; }
        .login-title   { @apply text-white text-3xl font-bold mb-3; text-align:center }
        .login-sub     { @apply text-gray-400 text-sm mb-8 text-center; }
        .error-msg     { @apply text-red-500 font-medium text-sm mb-4; }
        .success-msg   { @apply text-green-400 font-medium text-sm mb-4 bg-green-900/30 border border-green-700 rounded-xl px-4 py-3; }
        .form-space    { @apply space-y-4; }
        .input-wrap    { @apply relative; }
        .input-field   {
            @apply w-full px-5 py-3 bg-cream-light rounded-xl text-black placeholder-gray-600 focus:ring-0 pr-12;
        }
        .input-field:focus { outline:none; box-shadow:0 0 0 2px rgba(255,255,255,0.5); }
        .toggle-eye    {
            @apply absolute right-4 top-1/2 -translate-y-1/2 cursor-pointer text-gray-500 hover:text-gray-700 select-none;
            font-size: 18px;
        }
        .hint-text     { @apply text-gray-500 text-xs mt-1 ml-1; }
        .strength-bar  { @apply h-1 rounded-full mt-2 transition-all duration-300; }
        .btn-submit    {
            @apply w-full bg-white text-black font-semibold text-lg py-3 rounded-xl shadow-lg hover:bg-gray-100 transition duration-150;
        }
        .footer-text   { @apply mt-6 text-center text-gray-400; }
        .link-green    { @apply text-green-500 font-semibold hover:text-green-400 transition; }
        .image-side    { @apply hidden md:block md:w-1/2 h-screen; }
        .bg-image      {
            background-image: url('Gambar/setengah.jpg');
            @apply relative w-full h-screen bg-cover bg-center;
        }
        .overlay       { @apply absolute inset-0 bg-black opacity-60; }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="form-wrapper">

            <h2 class="login-title">Buat Kata Sandi Baru</h2>
            <p class="login-sub">Masukkan kata sandi baru yang kuat untuk akun Anda.</p>

            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?>
                    <?php if (!$validToken && !$success): ?>
                        <br><a href="lupa_sandi.php" class="underline text-red-400 hover:text-red-300">Minta link baru</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-msg">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
                <div class="footer-text" style="margin-top:16px">
                    <a href="login.php" class="link-green">→ Masuk Sekarang</a>
                </div>

            <?php elseif ($validToken): ?>
            <form method="POST" action="reset_sandi.php" class="form-space" id="resetForm">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <!-- Password baru -->
                <div>
                    <div class="input-wrap">
                        <input type="password" name="password" id="pw1" placeholder="Kata Sandi Baru" required
                               class="input-field" oninput="checkStrength(this.value)">
                        <span class="toggle-eye" onclick="togglePw('pw1', this)">👁</span>
                    </div>
                    <div class="strength-bar" id="strengthBar" style="width:0;background:#e74c3c"></div>
                    <p class="hint-text" id="strengthLabel">Minimal 8 karakter</p>
                </div>

                <!-- Konfirmasi password -->
                <div>
                    <div class="input-wrap">
                        <input type="password" name="password2" id="pw2" placeholder="Konfirmasi Kata Sandi" required
                               class="input-field" oninput="checkMatch()">
                        <span class="toggle-eye" onclick="togglePw('pw2', this)">👁</span>
                    </div>
                    <p class="hint-text" id="matchLabel"></p>
                </div>

                <div class="h-2"></div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    Simpan Kata Sandi Baru
                </button>
            </form>

            <?php elseif (!$error): ?>
                <div class="error-msg">Token tidak valid.</div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <div class="footer-text">
                Ingat kata sandi? <a href="login.php" class="link-green">Masuk</a>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <div class="image-side">
        <div class="bg-image">
            <div class="overlay"></div>
        </div>
    </div>

    <script>
    function togglePw(id, icon) {
        const el = document.getElementById(id);
        const show = el.type === 'password';
        el.type = show ? 'text' : 'password';
        icon.textContent = show ? '🙈' : '👁';
    }

    function checkStrength(val) {
        const bar   = document.getElementById('strengthBar');
        const label = document.getElementById('strengthLabel');
        let score = 0;
        if (val.length >= 8)  score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { pct:'25%', color:'#e74c3c', text:'Lemah' },
            { pct:'50%', color:'#e67e22', text:'Cukup' },
            { pct:'75%', color:'#f1c40f', text:'Baik' },
            { pct:'100%', color:'#27ae60', text:'Kuat 💪' },
        ];
        const lv = val.length === 0 ? null : levels[Math.max(0, score - 1)];
        bar.style.width    = lv ? lv.pct   : '0';
        bar.style.background = lv ? lv.color : '#e74c3c';
        label.textContent  = lv ? lv.text  : 'Minimal 8 karakter';
        label.style.color  = lv ? lv.color : '#6b7280';
        checkMatch();
    }

    function checkMatch() {
        const v1  = document.getElementById('pw1').value;
        const v2  = document.getElementById('pw2').value;
        const lbl = document.getElementById('matchLabel');
        const btn = document.getElementById('submitBtn');
        if (!v2) { lbl.textContent = ''; return; }
        if (v1 === v2) {
            lbl.textContent = '✓ Kata sandi cocok';
            lbl.style.color = '#27ae60';
            btn.disabled = false;
        } else {
            lbl.textContent = '✗ Kata sandi tidak cocok';
            lbl.style.color = '#e74c3c';
            btn.disabled = true;
        }
    }
    </script>

</body>
</html>
