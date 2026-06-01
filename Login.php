<?php
// ============================================================
//  login.php — Halaman Login Tunggal (Style Bersih di <style>)
// ============================================================
require_once 'config.php';
session_start();

$session = getSession();
if ($session) {
    if ($session['type'] === 'admin') {
        redirect('dashboardadmin.php');
    } else {
        redirect('index.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Isi email dan kata sandi.';
    } else {
        $pdo = getDB();
        // Cari user tanpa filter is_active agar bisa bedakan banned vs salah password
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Password benar — cek apakah di-ban
            if ((int)$user['is_active'] === 0) {
                $error = 'banned';
            } else {
                $_SESSION['user'] = [
                    'id'       => $user['id'],
                    'username' => $user['username'],
                    'email'    => $user['email'],
                    'type'     => $user['type'],
                    'photo'    => $user['profile_photo'],
                ];
                if ($user['type'] === 'admin') {
                    redirect('dashboardadmin.php');
                } else {
                    redirect('index.php');
                }
            }
        } else {
            $error = 'Email atau kata sandi salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - JelajahinNusa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { 'cream-light': '#F3F5E9', 'black-main': '#1F1F1F' } } }
        }
    </script>
    <style type="text/tailwindcss">
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');

        html, body {
            overflow: hidden;
            max-width: 100vw;
        }
        
        /* Base Style */
        body { 
            font-family: 'Inter', sans-serif; 
            @apply bg-black-main flex flex-col md:flex-row antialiased h-screen;
        }

        /* Layout Wrapper */
        .login-container {
            @apply w-full md:w-1/2 p-6 md:p-14 flex flex-col justify-center h-screen overflow-hidden;
        }
        .form-wrapper {
            @apply max-w-md w-full mx-auto flex flex-col justify-center;
        }

        /* Typography & Components */
        .login-title {
            @apply text-white text-3xl font-bold mb-8;
            text-align: center
        }
        .error-message {
            @apply text-red-500 font-medium text-sm mb-4;
        }
        .form-space {
            @apply space-y-4;
        }

        /* Input Fields */
        .input-field {
            @apply w-full px-5 py-3 bg-cream-light rounded-xl text-black placeholder-gray-600 focus:ring-0;
        }
        .input-field:focus { 
            outline: none; 
            box-shadow: 0 0 0 2px rgba(255,255,255,0.5); 
        }

        /* Button & Links */
        .btn-submit {
            @apply w-full bg-white text-black font-semibold text-lg py-3 rounded-xl shadow-lg hover:bg-gray-100 transition duration-150;
        }
        .footer-text {
            @apply mt-6 text-center text-gray-400;
        }
        .link-green {
            @apply text-green-500 font-semibold hover:text-green-400 transition;
        }

        /* Right Side Image Layout */
        .image-side {
            @apply hidden md:block md:w-1/2 h-screen;
        }
        .bg-image {
            background-image: url('Gambar/setengah.jpg');
            @apply relative w-full h-screen bg-cover bg-center;
        }
        .overlay {
            @apply absolute inset-0 bg-black opacity-60;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="form-wrapper">

            <h2 class="login-title">Selamat Datang</h2>

            <?php if ($error === 'banned'): ?>
                <div class="error-message" style="background:rgba(180,30,30,0.15);border:1px solid rgba(220,50,50,0.4);border-radius:12px;padding:12px 16px;text-align:center;line-height:1.6">
                    🚫 <strong>Akun Anda telah dinonaktifkan.</strong><br>
                    <span style="font-weight:400;font-size:12px;opacity:.85">Hubungi admin jika Anda merasa ini adalah kesalahan.
                        <a href="mailto:revaneuy123@gmail.com?subject=Permohonan%20Aktivasi%20Akun&body=Halo%20Admin%2C%0A%0ASaya%20ingin%20mengajukan%20permohonan%20aktivasi%20akun%20saya%20yang%20telah%20dinonaktifkan.%0A%0AEmail%20akun%3A%20<?= urlencode($_POST['email'] ?? '') ?>%0A%0ATerima%20kasih."
                           style="color:#f87171;text-decoration:underline;font-weight:600;white-space:nowrap">→ Hubungi Admin</a>
                    </span>
                </div>
            <?php elseif ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="form-space">
                <div>
                    <input type="email" name="email" placeholder="E-mail" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="input-field">
                </div>
                <div>
                    <div style="position:relative">
                        <input type="password" name="password" id="loginPw" placeholder="Kata Sandi" required
                               class="input-field" style="padding-right:48px">
                        <span onclick="togglePw('loginPw',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:#888;display:flex;align-items:center;line-height:1;user-select:none"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="12" rx="10" ry="6"/><circle cx="12" cy="12" r="3" fill="currentColor" stroke="none"/></svg></span>
                    </div>
                    <div style="text-align:right;margin-top:6px">
                        <a href="lupa_sandi.php" class="link-green" style="font-size:13px">Lupa Kata Sandi?</a>
                    </div>
                </div>

                <div class="h-8"></div>

                <button type="submit" class="btn-submit">
                    Masuk
                </button>
            </form>

            <div class="footer-text">
                Belum punya akun? 
                <a href="daftar.php" class="link-green">Daftar</a>
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
        el.type = el.type === 'password' ? 'text' : 'password';
        icon.innerHTML = el.type === 'password'
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="12" rx="10" ry="6"/><circle cx="12" cy="12" r="3" fill="currentColor" stroke="none"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18"/><path d="M10.5 10.68A3 3 0 0 0 14.32 14.5"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2 12s4 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><path d="M17.63 17.63A13.5 13.5 0 0 0 22 12s-4-7-10-7a9.77 9.77 0 0 0-4.95 1.37"/></svg>';
    }
    </script>
</body>
</html>