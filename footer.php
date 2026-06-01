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
@media(max-width:768px){
    .footer-main{grid-template-columns:1fr 1fr;}
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
<section class="usul-section">
    <div class="container">
        <div class="usul-box">
            <h3>Bagikan Permata Tersembunyi Favoritmu!</h3>
            <p>Punya tempat <em>healing</em> favorit yang belum ada di sini? Beritahu kami agar bisa kami bagikan ke petualang lain!</p>
            <form class="usul-form" method="POST" id="formUsul">
                <input type="hidden" name="form" value="usul_artikel">
                <input type="text" name="usul_pesan" class="usul-input"
                       placeholder="Ceritakan destinasi atau topik yang ingin kamu usulkan..." required maxlength="500">
                <button type="submit" class="usul-submit" id="usulBtn">
                    <i class="fas fa-paper-plane" style="margin-right:7px"></i>Kirim
                </button>
            </form>
        </div>
    </div>
</section>
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