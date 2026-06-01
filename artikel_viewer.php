<?php
// ============================================================
//  artikel_viewer.php — Halaman Detail Artikel (REFAKTOR)
// ============================================================
require_once 'config.php';
require_once 'navbar.php';
require_once 'footer.php';

session_start();
$session   = getSession();
$pdo       = getDB();
$artikelId = trim($_GET['id'] ?? '');

if (!preg_match('/^[a-zA-Z0-9\-]+$/', $artikelId)) {
    http_response_code(404); die('Artikel tidak ditemukan.');
}

$toast = '';

// ── Handle upload foto kontribusi ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'upload_galeri') {
    if (!$session) { header('Location: login.php'); exit; }
    $uploadDir = 'Gambar/galeri/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $file      = $_FILES['galeri_foto'] ?? null;
    $judul     = trim($_POST['galeri_judul']   ?? '');
    $lokUpload = trim($_POST['galeri_lokasi']  ?? '');
    $caption   = trim($_POST['galeri_caption'] ?? '');
    $artRef    = trim($_POST['artikel_ref']    ?? $artikelId);
    $allowed   = ['image/jpeg','image/png','image/webp','image/gif'];
    if ($file && $file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowed)
        && $file['size'] <= 5*1024*1024 && $judul) {
        $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
        $dest = $uploadDir.'galeri_'.bin2hex(random_bytes(8)).'.'.$ext;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $gid = bin2hex(random_bytes(16));
            $pdo->prepare(
                "INSERT INTO galeri (id, image_path, judul, lokasi, caption, artikel_id, user_id, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
            )->execute([$gid, $dest, $judul, $lokUpload, $caption, $artRef, $session['id']]);
            $toastMsg = 'Foto berhasil diunggah! Menunggu persetujuan admin.';
        } else { $toastMsg = 'Gagal menyimpan foto. Coba lagi.'; }
    } else { $toastMsg = 'Upload gagal. Pastikan foto valid dan judul diisi.'; }
    header('Location: artikel_viewer.php?id='.urlencode($artikelId).'&toast='.urlencode($toastMsg));
    exit;
}
if (empty($toast) && !empty($_GET['toast'])) $toast = htmlspecialchars($_GET['toast']);

// ── Ambil artikel ────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM v_artikel_lengkap WHERE id = ? LIMIT 1");
$stmt->execute([$artikelId]);
$art = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$art) { http_response_code(404); die('Artikel tidak ditemukan.'); }

$pdo->prepare("UPDATE artikel SET views = views + 1 WHERE id = ?")->execute([$artikelId]);

// ── Artikel terkait ──────────────────────────────────────────
$relStmt = $pdo->prepare(
    "SELECT id, title, image, location FROM artikel
     WHERE kategori_id = ? AND id != ? AND status = 'published'
     ORDER BY views DESC LIMIT 4"
);
$relStmt->execute([$art['kategori_id'], $artikelId]);
$related = $relStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Galeri — ambil dari gallery_json yang sudah di-sync otomatis saat approve/reject ──
$galleryJson = [];
if ($art['gallery_json']) {
    $decoded = json_decode($art['gallery_json'], true);
    if (is_array($decoded)) {
        $galleryJson = array_map(fn($g) => ['src' => $g['src'] ?? '', 'caption' => $g['caption'] ?? ''], $decoded);
    }
}

// ── Parsing konten ───────────────────────────────────────────
function renderTeks(string $text): string {
    $lines = preg_split('/\r?\n/', trim($text));
    $intro = []; $items = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^\*\*/', $line)) { $items[] = $line; }
        elseif (!empty($items)) { $items[count($items)-1] .= ' '.$line; }
        else { $intro[] = $line; }
    }
    $html = '';
    if ($intro) {
        $t = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', htmlspecialchars(implode(' ',$intro)));
        $html .= '<p>'.$t.'</p>';
    }
    if ($items) {
        $html .= '<ul>';
        foreach ($items as $item) {
            $html .= '<li>'.preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', htmlspecialchars($item)).'</li>';
        }
        $html .= '</ul>';
    }
    if (!$intro && !$items) {
        foreach (preg_split('/\n{2,}/', trim($text)) as $p) {
            $html .= '<p>'.preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', htmlspecialchars(trim($p))).'</p>';
        }
    }
    return $html;
}

function parseKonten(string $raw): string {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $html = '';
        foreach (['deskripsi'=>'Deskripsi','jalur'=>'Jalur dan Akses Menuju','daya_tarik'=>'Daya Tarik'] as $key => $label) {
            if (!empty($decoded[$key])) { $html .= '<h3>'.$label.'</h3>'.renderTeks($decoded[$key]); }
        }
        return $html;
    }
    if (str_contains($raw,'<p>') || str_contains($raw,'<h3>')) return $raw;
    return renderTeks($raw);
}

$heroStyle  = $art['image'] ? "background:url('".htmlspecialchars($art['image'])."') center/cover no-repeat;" : '';
$kontenHtml = parseKonten($art['content'] ?? $art['excerpt'] ?? '');
$pageTitle  = htmlspecialchars($art['title'] ?? 'Artikel');
$kategori   = htmlspecialchars($art['kategori'] ?? '');
$lokasiRaw  = $art['location'] ?? '';
$lokasiParts = array_unique(array_map('trim', explode(',', $lokasiRaw)));
$lokasi     = htmlspecialchars(implode(', ', $lokasiParts));
$mapsEmbed  = htmlspecialchars($art['maps_embed'] ?? '');

$infoBox = null;
if (!empty($art['info_json'])) {
    $decodedInfo = json_decode($art['info_json'], true);
    if (is_array($decodedInfo) && count(array_filter(array_keys($decodedInfo), 'is_string')) > 0) {
        $infoBox = $decodedInfo;
    } else {
        $rawInfo = is_string($decodedInfo) ? $decodedInfo : $art['info_json'];
        $infoBox = [];
        foreach (explode("\n", $rawInfo) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (str_contains($line, ':')) { [$k,$v] = explode(':',$line,2); $infoBox[trim($k)] = trim($v); }
            else { $infoBox[$line] = ''; }
        }
        if (empty($infoBox)) $infoBox = null;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> – JelajahinNusa</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    :root{ --dark-bg:#000; --dark-bg-secondary:#1A1A1A; --text-primary:#FFF; --text-secondary:#AAA; --accent-green:#1F4529; }
    *{ margin:0; padding:0; box-sizing:border-box; }
    html{ scroll-behavior:smooth; }
    body{ font-family:'Poppins',Arial,sans-serif; background:var(--dark-bg); color:var(--text-primary); line-height:1.6; overflow-x:hidden; zoom:90%; }
    .container{ max-width:1200px; margin:0 auto; padding:0 30px; }
    a{ text-decoration:none; color:inherit; }
    ul{ list-style:none; }
    img{ max-width:100%; display:block; }
    .btn{ display:inline-block; padding:12px 28px; border-radius:30px; font-weight:500; font-size:16px; transition:.3s; border:none; cursor:pointer; }
    .btn-primary{ background:var(--text-primary); color:#000; }
    .btn-primary:hover{ opacity:.9; }
    /* Hero */
    .post-hero{ height:50vh; position:relative; display:flex; align-items:flex-end; overflow:hidden; z-index:1; isolation:isolate; }
    .hero-bg{ position:absolute; inset:0; <?= $heroStyle ?> filter:brightness(.55); }
    .hero-title{ position:relative; z-index:2; padding:40px 0; }
    .hero-title h2{ font-size:40px; font-weight:700; line-height:1.2; text-shadow:0 2px 10px rgba(0,0,0,.5); }
    .badge-kat{ background:rgba(31,69,41,.5); color:#6fcf97; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; display:inline-block; margin-bottom:10px; }
    /* Layout */
    .post-body{ padding:60px 0; }
    .content-layout{ display:grid; grid-template-columns:1fr 340px; gap:50px; align-items:start; }
    @media(max-width:900px){ .content-layout{ grid-template-columns:1fr; } }
    .main-content p{ color:rgba(255,255,255,.85); font-size:16px; line-height:1.85; margin-bottom:22px; }
    .main-content h3{ font-size:22px; font-weight:600; margin:28px 0 14px; }
    .main-content ul{ margin-left:20px; list-style:disc; margin-bottom:20px; }
    .main-content li{ color:rgba(255,255,255,.82); font-size:15px; line-height:1.8; margin-bottom:10px; }
    /* Sidebar */
    .sidebar{ display:flex; flex-direction:column; gap:24px; position:sticky; top:90px; }
    .sidebar-box{ background:var(--dark-bg-secondary); border-radius:14px; padding:22px; }
    .sidebar-box h4{ font-size:16px; font-weight:600; margin-bottom:14px; }
    .map-placeholder iframe{ width:100%; height:220px; border-radius:8px; display:block; }
    .promo-box{ text-align:center; }
    .promo-box img{ max-width:80px; margin:0 auto 10px; border-radius:10px; }
    .app-links{ display:flex; flex-direction:row; justify-content:center; gap:10px; margin-top:15px; }
    .app-links img{ height:36px; width:auto; }
    /* Galeri terkait */
    .related-gallery{ padding:40px 0 60px; }
    .related-gallery h3{ font-size:22px; font-weight:600; margin-bottom:24px; }
    .gallery-grid{ display:flex; gap:14px; overflow-x:auto; scroll-snap-type:x mandatory; padding-bottom:12px; -webkit-overflow-scrolling:touch; scrollbar-width:thin; scrollbar-color:#333 transparent; }
    .gallery-grid::-webkit-scrollbar{ height:5px; }
    .gallery-grid::-webkit-scrollbar-track{ background:transparent; }
    .gallery-grid::-webkit-scrollbar-thumb{ background:#333; border-radius:10px; }
    .gallery-card{ border-radius:10px; overflow:hidden; position:relative; flex:0 0 260px; scroll-snap-align:start; }
    .gallery-card img{ width:100%; height:180px; object-fit:cover; transition:transform .4s; }
    .gallery-card:hover img{ transform:scale(1.06); }
    .gallery-caption{ position:absolute; bottom:0; left:0; right:0; padding:8px 12px; background:rgba(0,0,0,.65); font-size:12px; }
    /* Card tambah foto */
    .gallery-card-add{ background:rgba(255,255,255,.04); border:2px dashed rgba(255,255,255,.18); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:.25s; text-decoration:none; color:inherit; min-height:180px; flex:0 0 180px; scroll-snap-align:start; border-radius:10px; }
    .gallery-card-add:hover{ background:rgba(255,255,255,.09); border-color:rgba(255,255,255,.4); }
    .gallery-add-inner{ display:flex; flex-direction:column; align-items:center; gap:10px; }
    .gallery-add-inner i{ font-size:28px; color:#aaa; transition:.25s; }
    .gallery-card-add:hover .gallery-add-inner i{ color:#fff; }
    .gallery-add-inner span{ font-size:12px; color:#888; text-align:center; line-height:1.5; transition:.25s; }
    .gallery-card-add:hover .gallery-add-inner span{ color:#ccc; }
    /* Info box */
    .info-box{ background:rgba(31,69,41,.2); border:1px solid rgba(31,69,41,.5); border-radius:12px; padding:20px 24px; margin:24px 0; }
    .info-box h4{ font-size:15px; font-weight:600; color:#6fcf97; margin-bottom:10px; }
    .info-box ul{ margin-left:18px; list-style:disc; }
    .info-box li{ font-size:14px; color:rgba(255,255,255,.8); margin-bottom:6px; }
    /* Artikel terkait */
    .rel-grid{ display:grid; grid-template-columns:repeat(2,1fr); gap:14px; margin-top:10px; }
    .rel-card{ background:var(--dark-bg-secondary); border-radius:10px; overflow:hidden; display:flex; gap:12px; align-items:center; padding:10px; }
    .rel-card img{ width:70px; height:70px; object-fit:cover; border-radius:8px; flex-shrink:0; }
    .rel-card h5{ font-size:13px; font-weight:600; margin-bottom:4px; }
    .rel-card p{ font-size:11px; color:#aaa; }
    /* Ulasan */
    .review-card{ background:var(--dark-bg-secondary); border-radius:12px; padding:20px; margin-bottom:14px; }
    .review-card .stars{ color:#F1C40F; font-size:13px; margin-bottom:8px; }
    .review-card .reviewer{ font-size:13px; color:#aaa; margin-top:8px; }
    .review-card p{ font-size:15px; line-height:1.7; }
    /* Modal kontribusi */
    .modal-kontribusi-overlay{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:9999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
    .modal-kontribusi-overlay.open{ display:flex; }
    .modal-kontribusi-box{ background:#1a1a1a; border-radius:16px; padding:28px; width:100%; max-width:500px; max-height:90vh; overflow-y:auto; border:1px solid #2a2a2a; box-shadow:0 20px 60px rgba(0,0,0,.7); }
    .modal-kontribusi-box h3{ display:flex; justify-content:space-between; align-items:center; font-size:17px; margin-bottom:8px; }
    .modal-kontribusi-box h3 button{ background:none; border:none; color:#aaa; font-size:22px; cursor:pointer; line-height:1; padding:0; }
    .modal-kontribusi-box h3 button:hover{ color:#fff; }
    .k-drop-zone{ border:2px dashed #333; border-radius:12px; padding:30px 20px; text-align:center; cursor:pointer; transition:.25s; position:relative; margin-bottom:16px; }
    .k-drop-zone:hover,.k-drop-zone.drag-over{ border-color:#888; background:rgba(255,255,255,.04); }
    .k-drop-zone input[type="file"]{ position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
    .k-preview-wrap{ display:none; position:relative; border-radius:10px; overflow:hidden; margin-bottom:16px; }
    .k-preview-wrap img{ width:100%; height:200px; object-fit:cover; display:block; }
    .k-remove-preview{ position:absolute; top:8px; right:8px; background:rgba(0,0,0,.7); border:none; color:#fff; width:28px; height:28px; border-radius:50%; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; }
    .k-upload-progress{ display:none; margin-bottom:14px; }
    .k-upload-progress .bar-track{ background:#333; border-radius:6px; height:6px; overflow:hidden; }
    .k-upload-progress .bar-fill{ height:100%; background:#888; width:0%; transition:width .3s; border-radius:6px; }
    .form-row-k{ margin-bottom:14px; }
    .form-row-k label{ display:block; font-size:13px; color:#aaa; margin-bottom:6px; }
    .k-input{ width:100%; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.12); border-radius:9px; padding:11px 15px; color:#fff; font-family:'Poppins',sans-serif; font-size:14px; outline:none; transition:border-color .25s; }
    .k-input:focus{ border-color:#888; }
    .k-modal-actions{ display:flex; gap:10px; margin-top:8px; }
    .k-btn-cancel{ background:rgba(255,255,255,.08); color:#aaa; border:1px solid #333; padding:12px 20px; border-radius:9px; font-family:'Poppins',sans-serif; font-size:14px; cursor:pointer; transition:.25s; }
    .k-btn-cancel:hover{ color:#fff; }
    .k-btn-submit{ flex:1; background:#fff; color:#000; border:none; padding:12px; border-radius:9px; font-weight:600; font-family:'Poppins',sans-serif; font-size:14px; cursor:pointer; transition:.25s; }
    .k-btn-submit:hover{ background:#e0e0e0; }
    .k-btn-submit:disabled{ opacity:.35; cursor:not-allowed; }
  </style>
</head>
<body>

<?php renderNavbar($session, 'artikel.php'); ?>

<main>
  <!-- Hero -->
  <div class="post-hero">
    <div class="hero-bg"></div>
    <div class="container hero-title">
      <span class="badge-kat"><?= $kategori ?></span>
      <h2><?= $pageTitle ?></h2>
      <?php if ($lokasi): ?>
      <p style="color:rgba(255,255,255,.75);margin-top:8px;font-size:14px;">
        <i class="fas fa-map-marker-alt"></i> <?= $lokasi ?>
      </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Body -->
  <section class="post-body">
    <div class="container content-layout">
      <!-- Konten Utama -->
      <div class="main-content">
        <?= $kontenHtml ?>

        <?php if ($infoBox): ?>
        <div class="info-box">
          <h4><i class="fas fa-info-circle"></i> Info Kunjungan</h4>
          <ul>
            <?php foreach ($infoBox as $k => $v): ?>
            <li><strong><?= htmlspecialchars($k) ?>:</strong> <?= htmlspecialchars($v) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

      </div>

      <!-- Sidebar -->
      <div class="sidebar">
        <?php if ($mapsEmbed): ?>
        <div class="sidebar-box">
          <h4><i class="fas fa-map-marker-alt"></i> Peta Lokasi</h4>
          <div class="map-placeholder">
            <iframe src="<?= $mapsEmbed ?>" allowfullscreen loading="lazy"></iframe>
          </div>
        </div>
        <?php endif; ?>
        <div class="sidebar-box promo-box">
          <h4>Solusi Pesan Tiket Wisata</h4>
          <img src="Gambar/logoapp.jpg" alt="TiketinNusa">
          <h4 style="margin-top:8px">TiketinNusa</h4>
          <div class="app-links">
            <a href="#"><img src="https://upload.wikimedia.org/wikipedia/commons/3/3c/Download_on_the_App_Store_Badge.svg" alt="App Store"></a>
            <a href="#"><img src="https://upload.wikimedia.org/wikipedia/commons/7/78/Google_Play_Store_badge_EN.svg" alt="Google Play"></a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Galeri -->
  <section class="related-gallery container">
    <h3>Galeri Terkait Artikel</h3>
    <div class="gallery-grid">
      <?php if ($session): ?>
      <div class="gallery-card gallery-card-add" onclick="openKontribusiModal()">
        <div class="gallery-add-inner">
          <i class="fas fa-plus"></i>
          <span>Bagikan<br>Pengalaman Anda</span>
        </div>
      </div>
      <?php else: ?>
      <a href="login.php" class="gallery-card gallery-card-add">
        <div class="gallery-add-inner">
          <i class="fas fa-sign-in-alt"></i>
          <span>Masuk untuk<br>Bagikan</span>
        </div>
      </a>
      <?php endif; ?>

      <?php foreach ($galleryJson as $g): ?>
      <div class="gallery-card">
        <img src="<?= htmlspecialchars($g['src']) ?>" alt="<?= htmlspecialchars($g['caption'] ?? '') ?>">
        <div class="gallery-caption"><?= htmlspecialchars($g['caption'] ?? '') ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Destinasi Terkait -->
  <?php if (!empty($related)): ?>
  <section class="related-gallery container" style="padding-top:0;">
    <h3>Destinasi Terkait</h3>
    <div class="rel-grid">
      <?php foreach ($related as $r): ?>
      <a href="artikel_viewer.php?id=<?= urlencode($r['id']) ?>" class="rel-card" style="color:inherit;">
        <img src="<?= htmlspecialchars($r['image']) ?>" alt="<?= htmlspecialchars($r['title']) ?>">
        <div>
          <h5><?= htmlspecialchars($r['title']) ?></h5>
          <p><?= htmlspecialchars($r['location'] ?? '') ?></p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Modal Kontribusi Foto -->
  <?php if ($session): ?>
  <div class="modal-kontribusi-overlay" id="kontribusiModal">
    <div class="modal-kontribusi-box">
      <h3>
        <span><i class="fas fa-camera" style="color:#aaa;margin-right:10px"></i>Bagikan Pengalaman Anda</span>
        <button onclick="closeKontribusiModal()" title="Tutup">&times;</button>
      </h3>
      <p style="font-size:13px;color:#aaa;margin-bottom:20px;line-height:1.6;">
        Punya foto momen terbaik di sini? Unggah dan ceritakan pengalamanmu terkait
        <em><?= $pageTitle ?></em>. Foto akan menunggu persetujuan admin.
      </p>
      <form method="POST" action="artikel_viewer.php?id=<?= urlencode($artikelId) ?>" enctype="multipart/form-data" id="kontribusiForm">
        <input type="hidden" name="form"       value="upload_galeri">
        <input type="hidden" name="artikel_ref" value="<?= htmlspecialchars($artikelId) ?>">
        <div class="k-drop-zone" id="kDropZone">
          <i class="fas fa-cloud-upload-alt" style="font-size:36px;margin-bottom:10px;color:#555"></i>
          <p style="font-size:14px;color:#aaa">Seret &amp; lepas foto, atau klik untuk memilih</p>
          <small style="color:#666">JPG, PNG, WEBP, GIF · Maks. 5 MB</small>
          <input type="file" name="galeri_foto" id="kFotoInput" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
        <div class="k-preview-wrap" id="kPreviewWrap">
          <img id="kPreviewImg" src="" alt="Preview">
          <button type="button" class="k-remove-preview" onclick="kResetPreview()"><i class="fas fa-times"></i></button>
        </div>
        <div class="k-upload-progress" id="kProgress">
          <div class="bar-track"><div class="bar-fill" id="kBarFill"></div></div>
          <span id="kProgressLabel" style="font-size:12px;color:#aaa;margin-top:5px;display:block">Mengunggah...</span>
        </div>
        <div class="form-row-k">
          <label>Judul Foto <span style="color:#e74c3c">*</span></label>
          <input type="text" name="galeri_judul" class="k-input" placeholder="cth. Pemandangan <?= htmlspecialchars($art['title']) ?>" maxlength="120" required>
        </div>
        <div class="form-row-k">
          <label>Lokasi</label>
          <input type="text" name="galeri_lokasi" class="k-input" placeholder="<?= $lokasi ?: 'cth. Jawa Timur' ?>" maxlength="120" value="<?= htmlspecialchars($lokasiRaw) ?>">
        </div>
        <div class="form-row-k">
          <label>Caption / Deskripsi</label>
          <textarea name="galeri_caption" class="k-input" rows="3" placeholder="Ceritakan momen saat foto ini diambil..." maxlength="500" style="resize:vertical;line-height:1.6"></textarea>
        </div>
        <div class="k-modal-actions">
          <button type="button" class="k-btn-cancel" onclick="closeKontribusiModal()">Batal</button>
          <button type="submit" class="k-btn-submit" id="kSubmitBtn" disabled>
            <i class="fas fa-upload"></i> Unggah Foto
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</main>

<?php renderFooter($toast); ?>

<?php if ($session): ?>
<script>
function openKontribusiModal() {
    document.getElementById('kontribusiModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeKontribusiModal() {
    document.getElementById('kontribusiModal').classList.remove('open');
    document.body.style.overflow = '';
    kResetPreview();
    document.getElementById('kontribusiForm').reset();
    document.getElementById('kSubmitBtn').disabled = true;
}
document.getElementById('kontribusiModal').addEventListener('click', function(e) {
    if (e.target === this) closeKontribusiModal();
});

const kInput      = document.getElementById('kFotoInput');
const kPreviewW   = document.getElementById('kPreviewWrap');
const kPreviewImg = document.getElementById('kPreviewImg');
const kDropZone   = document.getElementById('kDropZone');
const kSubmitBtn  = document.getElementById('kSubmitBtn');
const kBarFill    = document.getElementById('kBarFill');
const kProgress   = document.getElementById('kProgress');
const kLabel      = document.getElementById('kProgressLabel');

kInput.addEventListener('change', () => kHandleFile(kInput.files[0]));

function kHandleFile(file) {
    if (!file) return;
    const allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    if (!allowed.includes(file.type)) { alert('Format tidak didukung. Gunakan JPG, PNG, WEBP, atau GIF.'); return; }
    if (file.size > 5*1024*1024) { alert('Ukuran file terlalu besar. Maksimal 5 MB.'); return; }
    const reader = new FileReader();
    reader.onload = e => {
        kPreviewImg.src = e.target.result;
        kPreviewW.style.display = 'block';
        kDropZone.style.display = 'none';
        kSubmitBtn.disabled = false;
    };
    reader.readAsDataURL(file);
}

function kResetPreview() {
    kPreviewW.style.display = 'none';
    kPreviewImg.src = '';
    kDropZone.style.display = 'block';
    kInput.value = '';
    kSubmitBtn.disabled = true;
}

kDropZone.addEventListener('dragover', e => { e.preventDefault(); kDropZone.classList.add('drag-over'); });
kDropZone.addEventListener('dragleave', () => kDropZone.classList.remove('drag-over'));
kDropZone.addEventListener('drop', e => {
    e.preventDefault(); kDropZone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) { const dt = new DataTransfer(); dt.items.add(file); kInput.files = dt.files; kHandleFile(file); }
});

document.getElementById('kontribusiForm').addEventListener('submit', function(e) {
    if (kInput.files.length === 0) { e.preventDefault(); alert('Pilih foto terlebih dahulu.'); return; }
    kProgress.style.display = 'block';
    kSubmitBtn.disabled = true;
    kSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengunggah...';
    let pct = 0;
    setInterval(() => { pct = Math.min(pct + Math.random()*15, 90); kBarFill.style.width = pct+'%'; kLabel.textContent = 'Mengunggah... '+Math.round(pct)+'%'; }, 200);
});
</script>
<?php endif; ?>
</body>
</html>