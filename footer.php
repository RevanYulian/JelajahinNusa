<?php
// ============================================================
//  footer.php — Komponen Footer + CSS Footer + Toast
//
//  CARA PAKAI:
//    require_once 'footer.php';
//    renderFooter();           // tanpa toast
//    renderFooter($toast);     // dengan toast
// ============================================================

function renderFooterCSS(): void { ?>
<style>
/* ── USUL ARTIKEL ── */
.usul-section{background:var(--dark-bg-secondary);padding:60px 0 0;}
.usul-box{background:#222;border-radius:20px;padding:50px;text-align:center;margin-bottom:0;}
.usul-box h3{font-size:24px;font-weight:600;margin-bottom:10px;}
.usul-box p{color:var(--text-secondary);margin-bottom:30px;}
.usul-form{display:flex;justify-content:center;gap:15px;max-width:600px;margin:0 auto;}
.usul-input{flex:1;padding:15px 25px;border-radius:15px;border:none;background:var(--dark-bg);color:var(--text-primary);font-family:'Poppins',sans-serif;font-size:12px;outline:none;}
.usul-input::placeholder{color:#666;}
.usul-submit{padding:15px 30px;border-radius:15px;background:var(--text-primary);color:#000;font-size:14px;font-weight:600;border:none;cursor:pointer;transition:.25s;white-space:nowrap;}
.usul-submit:hover{opacity:.85;}
.usul-submit:disabled{opacity:.4;cursor:not-allowed;}

footer{background:var(--dark-bg-secondary);padding:50px 0 28px;}
.footer-main{display:grid;grid-template-columns:repeat(4,1fr);gap:36px;margin-bottom:36px;}
.footer-col h4{font-size:15px;font-weight:600;margin-bottom:14px;}
.footer-col p,.footer-col li,.footer-col span{color:var(--text-secondary);font-size:13px;line-height:1.9;}
.footer-col a:hover{color:#fff;}
.social-icons{display:flex;gap:12px;}
.social-icons a{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;transition:.3s;}
.social-icons a:hover{background:var(--accent-green);color:#fff;}
.footer-bottom{text-align:center;padding-top:22px;border-top:1px solid #333;color:var(--text-secondary);font-size:13px;}
/* Toast */
.toast{position:fixed;bottom:28px;right:28px;background:#1F4529;color:#fff;padding:13px 22px;border-radius:10px;font-size:14px;z-index:99999;opacity:0;transform:translateY(16px);transition:.3s;pointer-events:none;}
.toast.show{opacity:1;transform:translateY(0);}
/* Btn */
.btn{display:inline-block;padding:12px 28px;border-radius:30px;font-weight:500;font-size:16px;transition:.3s;border:none;cursor:pointer;}
.btn-primary{background:var(--text-primary);color:#000;}
.btn-primary:hover{opacity:.9;}
/* Modal Login Usul */
.usul-login-overlay{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:10000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:.25s;}
.usul-login-overlay.open{opacity:1;pointer-events:all;}
.usul-login-box{background:#1a1a1a;border:1px solid #2e2e2e;border-radius:20px;padding:40px 36px;max-width:420px;width:90%;text-align:center;transform:translateY(20px) scale(.97);transition:.25s;}
.usul-login-overlay.open .usul-login-box{transform:translateY(0) scale(1);}
.usul-login-icon{width:64px;height:64px;border-radius:50%;background:rgba(31,69,41,.25);border:1px solid rgba(31,69,41,.5);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:26px;color:#4caf70;}
.usul-login-box h4{font-size:18px;font-weight:600;margin-bottom:8px;}
.usul-login-box p{font-size:13px;color:var(--text-secondary);margin-bottom:24px;line-height:1.7;}
.usul-login-actions{display:flex;gap:12px;justify-content:center;}
.usul-btn-login{flex:1;padding:12px 20px;border-radius:12px;background:var(--text-primary);color:#000;font-size:13px;font-weight:600;border:none;cursor:pointer;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:7px;}
.usul-btn-login:hover{opacity:.85;}
.usul-btn-daftar{flex:1;padding:12px 20px;border-radius:12px;background:transparent;color:var(--text-primary);font-size:13px;font-weight:600;border:1px solid #333;cursor:pointer;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:7px;}
.usul-btn-daftar:hover{background:#2a2a2a;}
.usul-login-close{position:absolute;top:14px;right:18px;background:none;border:none;color:#666;font-size:22px;cursor:pointer;line-height:1;}
.usul-login-box-inner{position:relative;}
/* Input non-login — tampilkan pointer hint */
.usul-input-wrap{position:relative;flex:1;}
.usul-input-wrap .usul-input{width:100%;}
@media(max-width:768px){
    .footer-main{grid-template-columns:1fr 1fr;}
    .usul-form{flex-direction:column;}
    .usul-login-actions{flex-direction:column;}
}
</style>
<?php }

function renderFooter(string $toast = ''): void {
    renderFooterCSS();

    // Handle POST usul artikel
    $usulToast = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'usul_artikel') {
        $pesan   = trim($_POST['usul_pesan'] ?? '');
        $sess    = getSession();
        $user_id = $sess['id'] ?? null;
        $user_id = $user_id ? (string)$user_id : null;
        if ($pesan === '') {
            $usulToast = 'Pesan usulan tidak boleh kosong.';
        } else {
            try {
                $pdo = getDB();
                $pdo->prepare("INSERT INTO usul_artikel (pesan, user_id, created_at) VALUES (?,?,NOW())")
                    ->execute([$pesan, $user_id]);
                $usulToast = 'Terima kasih! Usulan kamu sudah kami terima. 🙏';
            } catch (\Throwable $e) {
                $log = date('Y-m-d H:i:s') . " | user_id:{$user_id} | $pesan\n";
                file_put_contents(__DIR__ . '/usul_artikel.log', $log, FILE_APPEND);
                $usulToast = 'Terima kasih! Usulan kamu sudah kami terima. 🙏';
            }
        }
        if (!$toast) $toast = $usulToast;
    }
?>

<!-- ===== USUL ARTIKEL ===== -->
<?php $sessUsul = getSession(); ?>
<section class="usul-section">
    <div class="container">
        <div class="usul-box">
            <h3>Bagikan Permata Tersembunyi Favoritmu!</h3>
            <p>Punya tempat <em>healing</em> favorit yang belum ada di sini? Beritahu kami agar bisa kami bagikan ke petualang lain!</p>

            <?php if ($sessUsul): ?>
            <!-- Sudah login — form normal -->
            <form class="usul-form" method="POST" id="formUsul">
                <input type="hidden" name="form" value="usul_artikel">
                <div class="usul-input-wrap">
                    <input type="text" name="usul_pesan" class="usul-input"
                           placeholder="Ceritakan destinasi atau topik yang ingin kamu usulkan..." required maxlength="500">
                </div>
                <button type="submit" class="usul-submit" id="usulBtn">
                    <i class="fas fa-paper-plane" style="margin-right:7px"></i>Kirim
                </button>
            </form>
            <?php else: ?>
            <!-- Belum login — input palsu, klik buka modal -->
            <div class="usul-form" style="cursor:pointer;" onclick="openUsulLoginModal()">
                <div class="usul-input-wrap">
                    <input type="text" class="usul-input" readonly
                           placeholder="Ceritakan destinasi atau topik yang ingin kamu usulkan..."
                           style="cursor:pointer;" onclick="openUsulLoginModal()">
                </div>
                <button type="button" class="usul-submit" onclick="openUsulLoginModal()">
                    <i class="fas fa-paper-plane" style="margin-right:7px"></i>Kirim
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (!$sessUsul): ?>
<!-- Modal Login untuk Usul Artikel -->
<div class="usul-login-overlay" id="usulLoginModal" onclick="handleUsulOverlayClick(event)">
    <div class="usul-login-box">
        <div class="usul-login-box-inner">
            <button class="usul-login-close" onclick="closeUsulLoginModal()" title="Tutup">&times;</button>
            <div class="usul-login-icon">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            <h4>Masuk untuk Berbagi Usulan</h4>
            <p>Kamu punya destinasi tersembunyi yang ingin dibagikan?<br>Masuk atau daftar dulu yuk &#8212; gratis!</p>
            <div class="usul-login-actions">
                <a href="login.php" class="usul-btn-login">
                    <i class="fas fa-sign-in-alt"></i> Masuk
                </a>
                <a href="register.php" class="usul-btn-daftar">
                    <i class="fas fa-user-plus"></i> Daftar
                </a>
            </div>
        </div>
    </div>
</div>
<script>
function openUsulLoginModal() {
    document.getElementById('usulLoginModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeUsulLoginModal() {
    document.getElementById('usulLoginModal').classList.remove('open');
    document.body.style.overflow = '';
}
function handleUsulOverlayClick(e) {
    if (e.target === document.getElementById('usulLoginModal')) closeUsulLoginModal();
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeUsulLoginModal();
});
</script>
<?php endif; ?>
<footer>
    <div class="container">
        <div class="footer-main">
            <div class="footer-col">
                <h4>Alamat</h4>
                <p>JL. Tanimbar No.22, Kasin, Kec. Klojen,<br>Kota Malang, Jawa Timur 65117</p>
            </div>
            <div class="footer-col">
                <h4>Menu</h4>
                <ul>
                    <li><a href="index.php">Beranda</a></li>
                    <li><a href="artikel.php">Artikel</a></li>
                    <li><a href="galeri.php">Galeri</a></li>
                    <li><a href="tentangKami.php">Tentang Kami</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Hubungi Kami</h4>
                <ul>
                    <li><span><i class="fas fa-envelope"></i> jelajahinusa@gmail.com</span></li>
                    <li><span><i class="fab fa-whatsapp"></i> 086288275353 (Revan)</span></li>
                    <li><span><i class="fab fa-whatsapp"></i> 089209892410 (Baraka)</span></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Social Media</h4>
                <div class="social-icons">
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> JelajahinNusa. All rights reserved.</p>
        </div>
    </div>
</footer>

<div class="toast <?= $toast ? 'show' : '' ?>" id="toastMsg">
    <?= htmlspecialchars($toast) ?>
</div>
<script>
(function () {
    const t = document.getElementById('toastMsg');
    if (t && t.classList.contains('show')) {
        setTimeout(function () { t.classList.remove('show'); }, 3500);
    }
    const f = document.getElementById('formUsul');
    if (f) {
        f.addEventListener('submit', function () {
            const btn = document.getElementById('usulBtn');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:7px"></i>Mengirim...'; }
        });
    }
})();
</script>
<?php
}