<?php
// ============================================================
//  daftar.php — Halaman Pendaftaran Pengguna Baru (Style Bersih)
// ============================================================
require_once 'config.php';
session_start();


// Jika sudah login, langsung ke index
$session = getSession();
if ($session) redirect('index.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validasi
    if (!$username || !$email || !$password) {
        $error = 'Semua field harus diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Kata sandi minimal 6 karakter.';
    } else {
        $pdo = getDB();

        // Cek email duplikat
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar. Silakan gunakan email lain.';
        } else {
            // Simpan user baru
            $id   = bin2hex(random_bytes(16)); // UUID sederhana
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (id, username, email, password_hash, type, country, created_at)
                 VALUES (?, ?, ?, ?, 'user', 'Indonesia', NOW())"
            );
            $stmt->execute([$id, $username, $email, $hash]);

            // Tambahkan preferensi notifikasi default
            $pdo->prepare("INSERT INTO notifikasi_preferences (user_id) VALUES (?)")->execute([$id]);

            // Auto-login setelah daftar
            $_SESSION['user'] = [
                'id'       => $id,
                'username' => $username,
                'email'    => $email,
                'type'     => 'user',
                'photo'    => null,
            ];
            redirect('index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - JelajahinNusa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { 'cream-light': '#F3F5E9', 'black-main': '#1F1F1F' } } }
        }
    </script>
    <style type="text/tailwindcss">
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        
        html {
            overflow: hidden;
            height: 100%;
        }
        
        /* Base Style */
        body { 
            font-family: 'Inter', sans-serif;
            height: 100%;
            overflow: hidden;
            zoom: 90%;
            @apply bg-black-main flex flex-col md:flex-row antialiased;
        }

        /* Layout Wrapper */
        .register-container {
            @apply w-full md:w-1/2 p-6 md:p-14 flex flex-col justify-center;
            height: 100vh;
            overflow: hidden;
        }
        .form-wrapper {
            @apply max-w-md w-full mx-auto flex flex-col justify-center;
        }

        /* Typography */
        .title-welcome {
            @apply text-white text-3xl font-bold mb-1 text-center;
        }
        .title-sub {
            @apply text-white text-3xl font-bold mb-6 text-center;
        }
        .error-message {
            @apply text-red-400 text-sm text-center mb-4;
        }

        /* Form Elements */
        .input-group {
            @apply mb-4;
        }
        .input-field {
            @apply w-full px-5 py-3 bg-cream-light rounded-xl text-black placeholder-gray-600 focus:ring-0;
        }
        /* Sembunyikan ikon mata bawaan browser agar tidak dobel dengan custom icon */
        .input-field::-ms-reveal { display: none; }
        .input-field::-ms-clear { display: none; }

        /* Divider "Atau" */
        .divider-container {
            @apply relative py-5;
        }
        .divider-line-wrapper {
            @apply absolute inset-0 flex items-center;
        }
        .divider-line {
            @apply w-full border-t border-gray-600;
        }
        .divider-text-wrapper {
            @apply relative flex justify-center text-sm;
        }
        .divider-text {
            @apply px-3 bg-black-main text-gray-400;
        }

        /* Social Media Buttons */
        .social-container {
            @apply flex justify-center space-x-6 mb-8;
        }
        .social-btn {
            @apply w-12 h-12 flex items-center justify-center rounded-full hover:opacity-80 transition;
        }
        .social-img {
            @apply w-10 h-10;
        }

        /* Button & Footer Links */
        .btn-submit {
            @apply w-full bg-white text-black font-semibold text-lg py-3 rounded-xl shadow-lg hover:bg-gray-100 transition duration-150;
        }
        .footer-text {
            @apply mt-8 text-center text-gray-400;
        }
        .link-green {
            @apply text-green-500 font-semibold hover:text-green-400 transition;
        }

        /* Right Side Image Layout */
        .image-side {
            @apply hidden md:block md:w-1/2 h-screen;
            min-height: 111vh;
        }
        .bg-image {
            background-image: url('Gambar/setengah.jpg');
            @apply relative w-full h-full bg-cover bg-center;
        }
        .overlay {
            @apply absolute inset-0 bg-black opacity-60;
        }
    </style>
</head>
<body>

    <div class="register-container">
        <div class="form-wrapper">

            <h1 class="title-welcome">Selamat Datang</h1>
            <h2 class="title-sub">Penjelajah</h2>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="daftar.php">
                <div class="input-group">
                    <input type="text" name="username" placeholder="Username" required
                           autocomplete="off"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           class="input-field">
                </div>
                <div class="input-group">
                    <input type="email" name="email" placeholder="E-mail" required
                           autocomplete="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="input-field">
                </div>
                <div class="input-group" style="margin-bottom:6px">
                    <div style="position:relative">
                        <input type="password" name="password" id="regPw" placeholder="Kata Sandi (min. 6 karakter)" required
                               minlength="6" autocomplete="new-password"
                               class="input-field" style="padding-right:48px"
                               oninput="checkStrength(this.value)">
                        <i class="fas fa-eye" id="regPwIcon"
                           onclick="togglePw('regPw',this)"
                           style="position:absolute;right:16px;top:50%;transform:translateY(-50%);cursor:pointer;color:#666;font-size:16px;"></i>
                    </div>
                    <div style="height:4px;background:rgba(255,255,255,.1);border-radius:4px;margin-top:8px;overflow:hidden">
                        <div id="strengthBar" style="height:100%;width:0;border-radius:4px;transition:all .3s"></div>
                    </div>
                    <p id="strengthLabel" style="font-size:12px;color:#6b7280;margin-top:4px;margin-left:2px">Minimal 6 karakter</p>
                </div>

                <div class="divider-container">
                    <div class="divider-line-wrapper">
                        <div class="divider-line"></div>
                    </div>
                    <div class="divider-text-wrapper">
                        <span class="divider-text">Atau</span>
                    </div>
                </div>

                <div class="social-container">
                    <button type="button" class="social-btn">
                        <img src="Gambar/apple.png" class="social-img">
                    </button>
                    <button type="button" class="social-btn">
                        <img src="Gambar/google.png" class="social-img">
                    </button>
                    <button type="button" class="social-btn">
                        <img src="Gambar/facebook.png" class="social-img">
                    </button>
                </div>

                <button type="submit" class="btn-submit">
                    Daftar
                </button>
            </form>

            <div class="footer-text">
                Sudah Punya Akun?
                <a href="login.php" class="link-green">Masuk</a>
            </div>
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
        icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    }
    function checkStrength(val) {
        const bar   = document.getElementById("strengthBar");
        const label = document.getElementById("strengthLabel");
        if (!val) { bar.style.width="0"; label.textContent="Minimal 6 karakter"; label.style.color="#6b7280"; return; }
        let score = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        const lv = score <= 1 ? {w:"20%",c:"#e74c3c",t:"Lemah"}
                 : score === 2 ? {w:"45%",c:"#e67e22",t:"Cukup"}
                 : score === 3 ? {w:"70%",c:"#f1c40f",t:"Kuat"}
                 : {w:"100%",c:"#27ae60",t:"Sangat Kuat 💪"};
        bar.style.width = lv.w; bar.style.background = lv.c;
        label.textContent = lv.t; label.style.color = lv.c;
    }
    </script>
</body>
</html>