<?php
// ============================================================
//  galeri.php — Halaman Galeri (Grid + Masonry / Pinterest)
// ============================================================
require_once 'config.php';
require_once 'navbar.php';
require_once 'footer.php';
session_start();
$session = getSession();
$pdo     = getDB();

// Ambil semua foto dari DB
$galeri = $pdo->query(
    "SELECT g.*, a.title AS judul_artikel
     FROM galeri g
     LEFT JOIN artikel a ON g.artikel_id = a.id
     ORDER BY g.urutan ASC, g.created_at DESC"
)->fetchAll();

// Jika DB kosong, pakai data statis bawaan
if (empty($galeri)) {
    $galeri = [
        ['id'=>1,'image_path'=>'Gambar/hero2.jpg',          'judul'=>'Gunung Bromo',            'lokasi'=>'Probolinggo, Jawa Timur', 'likes'=>2100,'caption'=>'Hamparan lautan pasir dan kawah Bromo'],
        ['id'=>2,'image_path'=>'Gambar/papua.jpg',          'judul'=>'Raja Ampat',               'lokasi'=>'Papua Barat',             'likes'=>1800,'caption'=>'Gugusan pulau karst biru jernih'],
        ['id'=>3,'image_path'=>'Gambar/Tegunangan.jpeg',    'judul'=>'Air Terjun Tegenungan',    'lokasi'=>'Gianyar, Bali',           'likes'=>2300,'caption'=>'Air terjun rindang di hutan Bali'],
        ['id'=>4,'image_path'=>'Gambar/danau toba.jpeg',    'judul'=>'Danau Toba',               'lokasi'=>'Samosir, Sumatera Utara', 'likes'=>3500,'caption'=>'Danau vulkanik terbesar dunia'],
        ['id'=>5,'image_path'=>'Gambar/madakaripura.jpeg',  'judul'=>'Air Terjun Madakaripura',  'lokasi'=>'Probolinggo, Jawa Timur', 'likes'=>4200,'caption'=>'Air terjun megah tersembunyi'],
        ['id'=>6,'image_path'=>'Gambar/ijen.jpg',           'judul'=>'Kawah Ijen',               'lokasi'=>'Banyuwangi, Jawa Timur',  'likes'=>5400,'caption'=>'Api biru langka di dini hari'],
        ['id'=>7,'image_path'=>'Gambar/pantai.jpg',         'judul'=>'Pantai Parangtritis',      'lokasi'=>'Bantul, Yogyakarta',      'likes'=>7300,'caption'=>'Ombak besar pantai selatan Jawa'],
        ['id'=>8,'image_path'=>'Gambar/komodo.jpg',         'judul'=>'Labuan Bajo',              'lokasi'=>'Labuan Bajo, NTT',        'likes'=>3900,'caption'=>'Panorama pelabuhan dan gugusan pulau'],
        ['id'=>9,'image_path'=>'Gambar/rinjani.jpeg',       'judul'=>'Gunung Rinjani',           'lokasi'=>'Lombok, NTB',             'likes'=>2800,'caption'=>'Kaldera dan danau Segara Anak'],
        ['id'=>10,'image_path'=>'Gambar/merbabu.jpeg',      'judul'=>'Gunung Merbabu',           'lokasi'=>'Jawa Tengah',             'likes'=>1900,'caption'=>'Sabana hijau di puncak Merbabu'],
        ['id'=>11,'image_path'=>'Gambar/labuan cermin.jpeg','judul'=>'Labuan Cermin',            'lokasi'=>'Kalimantan Timur',        'likes'=>3100,'caption'=>'Danau dua rasa yang jernih'],
        ['id'=>12,'image_path'=>'Gambar/danau sentani.jpg', 'judul'=>'Danau Sentani',            'lokasi'=>'Papua',                   'likes'=>1600,'caption'=>'Danau di kaki Pegunungan Cycloop'],
        ['id'=>13,'image_path'=>'Gambar/kerinci.jpeg',      'judul'=>'Gunung Kerinci',           'lokasi'=>'Jambi, Sumatera',         'likes'=>2200,'caption'=>'Puncak tertinggi Sumatera'],
        ['id'=>14,'image_path'=>'Gambar/jaya wijaya.jpeg',  'judul'=>'Jaya Wijaya',              'lokasi'=>'Papua',                   'likes'=>1400,'caption'=>'Puncak bersalju di khatulistiwa'],
        ['id'=>15,'image_path'=>'Gambar/sekumpul.jpeg',     'judul'=>'Air Terjun Sekumpul',      'lokasi'=>'Singaraja, Bali',         'likes'=>4600,'caption'=>'Air terjun terindah di Bali'],
        ['id'=>16,'image_path'=>'Gambar/danau kelimutu.jpeg','judul'=>'Danau Kelimutu',          'lokasi'=>'Ende, NTT',               'likes'=>3300,'caption'=>'Tiga danau tiga warna di puncak gunung'],
        ['id'=>17,'image_path'=>'Gambar/tanjung tinggi.jpeg','judul'=>'Tanjung Tinggi',          'lokasi'=>'Bangka Belitung',         'likes'=>2600,'caption'=>'Batu granit raksasa di tepi pantai'],
        ['id'=>18,'image_path'=>'Gambar/tanjung bira.jpeg', 'judul'=>'Tanjung Bira',             'lokasi'=>'Sulawesi Selatan',        'likes'=>1700,'caption'=>'Pantai pasir putih bak tepung'],
        ['id'=>19,'image_path'=>'Gambar/pantai ora.jpeg',   'judul'=>'Pantai Ora',               'lokasi'=>'Maluku',                  'likes'=>2900,'caption'=>'Surga tersembunyi di timur Indonesia'],
        ['id'=>20,'image_path'=>'Gambar/pantai pink.jpeg',  'judul'=>'Pantai Pink',              'lokasi'=>'Pulau Komodo, NTT',       'likes'=>5100,'caption'=>'Pantai berpasir merah muda unik'],
    ];
}

$perPage    = 8;

// Filter search
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $galeri = array_values(array_filter($galeri, function($g) use ($search) {
        $q = mb_strtolower($search);
        return str_contains(mb_strtolower($g['judul']   ?? ''), $q)
            || str_contains(mb_strtolower($g['lokasi']  ?? ''), $q)
            || str_contains(mb_strtolower($g['caption'] ?? ''), $q);
    }));
}

$totalItems = count($galeri);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeri - JelajahinNusa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root{ --dark-bg:#000; --dark-bg-secondary:#1A1A1A; --text-primary:#FFF; --text-secondary:#AAA; --accent-green:#1F4529; }
        *{ margin:0; padding:0; box-sizing:border-box; }
        html{ scroll-behavior:smooth; }
        body{ font-family:'Poppins',Arial,sans-serif; background:var(--dark-bg); color:var(--text-primary); overflow-x:hidden; zoom:90%; }
        .container{ max-width:1200px; margin:0 auto; padding:0 30px; }
        a{ text-decoration:none; color:inherit; }
        ul{ list-style:none; }
        img{ max-width:100%; display:block; }

        /* ===== LAYOUT TOGGLE BAR ===== */
        .gallery-section{ max-width:1200px; margin:0 auto; padding:30px 30px 20px; }
        /* ===== MASONRY LAYOUT ===== */
        .gallery-grid{
            display:block;
            column-count:4;
            column-gap:14px;
        }
        .gallery-grid .gallery-card{
            break-inside:avoid;
            margin-bottom:14px;
            aspect-ratio:unset;
        }
        .gallery-grid .gallery-card img{
            height:auto;
        }
        @media(max-width:1100px){ .gallery-grid{ column-count:3; } }
        @media(max-width:750px) { .gallery-grid{ column-count:2; } }
        @media(max-width:480px) { .gallery-grid{ column-count:1; } }

        /* ===== CARD ===== */
        .gallery-card{
            position:relative; border-radius:14px; overflow:hidden;
            background:#222; cursor:pointer;
        }
        .gallery-card img{
            width:100%; height:100%; object-fit:cover; display:block;
            transition:transform .45s ease;
        }
        .gallery-card:hover img{ transform:scale(1.07); }

        .card-bar{
            position:absolute; bottom:0; left:0; right:0;
            display:flex; justify-content:flex-end; align-items:center;
            padding:10px 14px;
            background:linear-gradient(transparent,rgba(0,0,0,.72));
        }
        .more-btn{
            display:flex; align-items:center; justify-content:center;
            width:30px; height:30px; border-radius:50%;
            color:#ccc; font-size:16px; cursor:pointer;
            transition:.2s; background:transparent; border:none;
        }
        .more-btn:hover{ background:rgba(255,255,255,.15); color:#fff; }
        .card-dropdown{
            display:none; position:absolute; bottom:44px; right:10px;
            background:#1e1e1e; border:1px solid rgba(255,255,255,.12);
            border-radius:10px; min-width:160px; z-index:20;
            box-shadow:0 6px 20px rgba(0,0,0,.6); overflow:hidden;
        }
        .card-dropdown.open{ display:block; }
        .card-dropdown a{
            display:flex; align-items:center; gap:10px;
            padding:11px 16px; font-size:13px; color:#ccc; transition:.2s;
        }
        .card-dropdown a:hover{ background:rgba(255,255,255,.07); color:#fff; }
        .card-dropdown a i{ width:14px; text-align:center; }

        /* ===== PAGINATION ===== */
        .pagination-wrap{
            display:flex; justify-content:center; align-items:center;
            gap:0; padding:32px 0 28px; position:relative;
        }
        /* masonry: sembunyikan pagination */
        .pagination-wrap.hidden{ display:none; }

        .dots-row{ display:flex; gap:10px; align-items:center; }
        .dot{
            width:10px; height:10px; border-radius:50%;
            background:rgba(255,255,255,.3);
            cursor:pointer; transition:.25s; border:none; padding:0;
        }
        .dot.active{ background:#fff; transform:scale(1.25); }

        .btn-prev,.btn-next{
            position:absolute;
            width:50px; height:50px; border-radius:50%;
            background:#fff; color:#000;
            display:flex; align-items:center; justify-content:center;
            font-size:20px; cursor:pointer; border:none; transition:.25s;
            box-shadow:0 4px 14px rgba(0,0,0,.4);
        }
        .btn-prev{ left:0; }
        .btn-next{ right:0; }
        .btn-prev:hover,.btn-next:hover{ background:#e0e0e0; transform:scale(1.06); }
        .btn-prev:disabled,.btn-next:disabled{ opacity:0; pointer-events:none; }

        /* ===== LIGHTBOX ===== */
        .lightbox-overlay{
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,.94); z-index:9999;
            align-items:center; justify-content:center;
        }
        .lightbox-overlay.open{ display:flex; }
        .lightbox-inner{ position:relative; text-align:center; max-width:92vw; }
        .lightbox-inner img{
            max-width:90vw; max-height:82vh; border-radius:12px;
            display:block; margin:0 auto; box-shadow:0 20px 60px rgba(0,0,0,.8);
        }
        .lb-info{ margin-top:14px; }
        .lb-info h4{ font-size:16px; font-weight:600; }
        .lb-info p{ font-size:13px; color:#aaa; margin-top:4px; }
        .lb-close{
            position:fixed; top:22px; right:30px;
            font-size:38px; color:#fff; cursor:pointer;
            line-height:1; z-index:10001; opacity:.8; transition:.2s;
        }
        .lb-close:hover{ opacity:1; }
        .lb-nav{
            position:fixed; top:50%; transform:translateY(-50%);
            font-size:32px; color:rgba(255,255,255,.7);
            background:rgba(0,0,0,.45); width:52px; height:52px;
            border-radius:50%; display:flex; align-items:center; justify-content:center;
            cursor:pointer; user-select:none; transition:.2s; z-index:10001;
        }
        .lb-nav:hover{ background:rgba(0,0,0,.75); color:#fff; }
        .lb-prev{ left:20px; }
        .lb-next{ right:20px; }

        /* Toast */
        .toast{
            position:fixed; bottom:28px; right:28px;
            background:#1F4529; color:#fff;
            padding:12px 22px; border-radius:10px;
            font-size:14px; z-index:99999;
            opacity:0; transform:translateY(14px); transition:.3s; pointer-events:none;
        }
        .toast.show{ opacity:1; transform:translateY(0); }

        /* Info hasil pencarian */
        .search-result-info{
            padding:28px 24px 0; display:flex; align-items:center; gap:10px; flex-wrap:wrap;
        }
        .search-result-info .result-text{ font-size:14px; color:var(--text-secondary); }
        .search-result-info .result-text strong{ color:#fff; }
        .search-chip{
            display:inline-flex; align-items:center; gap:7px;
            font-size:13px; color:#fff;
            background:rgba(255,255,255,.08);
            border:1px solid rgba(255,255,255,.15);
            padding:4px 10px 4px 14px; border-radius:20px;
            text-decoration:none; transition:.2s;
        }
        .search-chip:hover{ background:rgba(255,255,255,.13); }
        .search-chip i{
            font-size:10px; color:#aaa;
            background:rgba(255,255,255,.12); border-radius:50%;
            width:16px; height:16px; display:flex; align-items:center; justify-content:center;
            transition:.2s;
        }
        .search-chip:hover i{ background:rgba(255,100,100,.3); color:#ff7070; }

        /* Empty state pencarian */
        .gallery-empty{
            padding:80px 24px; text-align:center; color:#444;
        }
        .gallery-empty i{ font-size:48px; margin-bottom:16px; display:block; color:#2a2a2a; }
        .gallery-empty p{ font-size:15px; margin-bottom:16px; }
        .gallery-empty a{ color:#6fcf97; font-size:13px; }

        /* Responsive */
        @media(max-width:768px){
            .footer-main{ grid-template-columns:1fr 1fr; }
        }
    </style>
</head>
<body>

<?php renderNavbar($session, 'galeri.php'); ?>

<!-- ===== GALLERY ===== -->
<div class="gallery-section">

    <?php if ($search !== ''): ?>
    <div class="search-result-info">
        <span class="result-text">Menampilkan <strong><?= $totalItems ?> foto</strong></span>
        <a href="galeri.php" class="search-chip">
            <?= htmlspecialchars($search) ?>
            <i class="fas fa-times"></i>
        </a>
    </div>
    <?php endif; ?>

    <?php if ($totalItems === 0): ?>
    <div class="gallery-empty">
        <i class="fas fa-image"></i>
        <p>Tidak ada foto untuk <strong style="color:#aaa">"<?= htmlspecialchars($search) ?>"</strong></p>
        <a href="galeri.php"><i class="fas fa-arrow-left" style="margin-right:5px"></i>Lihat semua foto</a>
    </div>
    <?php else: ?>
    <div class="gallery-grid" id="galleryGrid">
        <?php foreach ($galeri as $i => $g): ?>
        <div class="gallery-card"
             data-index="<?= $i ?>"
             data-page="<?= floor($i / $perPage) ?>">

            <img src="<?= htmlspecialchars($g['image_path']) ?>"
                 alt="<?= htmlspecialchars($g['judul'] ?? '') ?>"
                 onclick="openLightbox(<?= $i ?>)">

            <div class="card-bar">
                <button class="more-btn" onclick="toggleDropdown(event, <?= $g['id'] ?>)">
                    <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="card-dropdown" id="drop-<?= $g['id'] ?>">
                    <a href="<?= htmlspecialchars($g['image_path']) ?>" download>
                        <i class="fas fa-download"></i> Unduh Gambar
                    </a>
                    <a href="#" onclick="sharePhoto('<?= htmlspecialchars(addslashes($g['judul'] ?? '')) ?>');return false;">
                        <i class="fas fa-share-alt"></i> Bagikan
                    </a>
                    <a href="#" onclick="reportPhoto();return false;">
                        <i class="fas fa-flag"></i> Laporkan
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination (hanya grid mode) -->
    <div class="pagination-wrap" id="paginationWrap">
        <button class="btn-prev" id="btnPrev" onclick="prevPage()" disabled>
            <i class="fas fa-chevron-left"></i>
        </button>
        <div class="dots-row" id="dotsRow">
            <?php for ($p = 0; $p < $totalPages; $p++): ?>
            <button class="dot <?= $p === 0 ? 'active' : '' ?>"
                    data-page="<?= $p ?>"
                    onclick="goToPage(<?= $p ?>)"></button>
            <?php endfor; ?>
        </div>
        <button class="btn-next" id="btnNext" onclick="nextPage()" <?= $totalPages <= 1 ? 'disabled' : '' ?>>
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>
    <?php endif; ?>

</div>

<!-- ===== LIGHTBOX ===== -->
<div class="lightbox-overlay" id="lightbox">
    <span class="lb-close" onclick="closeLightbox()">&times;</span>
    <span class="lb-nav lb-prev" onclick="lbNav(-1)"><i class="fas fa-chevron-left"></i></span>
    <div class="lightbox-inner">
        <img id="lbImg" src="" alt="">
        <div class="lb-info">
            <h4 id="lbTitle"></h4>
            <p id="lbLocation"></p>
        </div>
    </div>
    <span class="lb-nav lb-next" onclick="lbNav(1)"><i class="fas fa-chevron-right"></i></span>
</div>

<?php renderFooter(); ?>

<div class="toast" id="toast"></div>

<!-- ===== JAVASCRIPT ===== -->
<script>
const photos = <?= json_encode(array_values(array_map(fn($g) => [
    'src'      => $g['image_path'],
    'title'    => $g['judul']   ?? '',
    'location' => $g['lokasi']  ?? '',
    'id'       => $g['id'],
], $galeri))) ?>;

const perPage    = <?= $perPage ?>;
const totalPages = <?= $totalPages ?>;
let currentPage  = 0;
let lbIndex      = 0;


// ============================
// PAGINATION
// ============================
function goToPage(page) {
    currentPage = page;

    document.querySelectorAll('.gallery-card').forEach(card => {
        card.style.display = parseInt(card.dataset.page) === page ? '' : 'none';
    });

    document.querySelectorAll('.dot').forEach((d, i) => {
        d.classList.toggle('active', i === page);
    });

    document.getElementById('btnPrev').disabled = page <= 0;
    document.getElementById('btnNext').disabled = page >= totalPages - 1;

    document.querySelector('.gallery-section').scrollIntoView({ behavior:'smooth', block:'start' });
}

function prevPage() { if (currentPage > 0) goToPage(currentPage - 1); }
function nextPage() { if (currentPage < totalPages - 1) goToPage(currentPage + 1); }

// ============================
// LIGHTBOX
// ============================
function openLightbox(index) {
    lbIndex = index;
    updateLightbox();
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
}
function lbNav(dir) {
    lbIndex = (lbIndex + dir + photos.length) % photos.length;
    updateLightbox();
}
function updateLightbox() {
    const p = photos[lbIndex];
    const img = document.getElementById('lbImg');
    img.style.opacity = 0;
    img.src = p.src;
    img.onload = () => { img.style.transition = 'opacity .3s'; img.style.opacity = 1; };
    document.getElementById('lbTitle').textContent    = p.title;
    document.getElementById('lbLocation').textContent = p.location;
}
document.addEventListener('keydown', e => {
    if (!document.getElementById('lightbox').classList.contains('open')) return;
    if (e.key === 'ArrowRight') lbNav(1);
    if (e.key === 'ArrowLeft')  lbNav(-1);
    if (e.key === 'Escape')     closeLightbox();
});
document.getElementById('lightbox').addEventListener('click', function(e) {
    if (e.target === this) closeLightbox();
});

// ============================
// DROPDOWN KARTU
// ============================
function toggleDropdown(e, id) {
    e.stopPropagation();
    const target = document.getElementById('drop-' + id);
    const isOpen = target.classList.contains('open');
    document.querySelectorAll('.card-dropdown').forEach(d => d.classList.remove('open'));
    if (!isOpen) target.classList.add('open');
}
document.addEventListener('click', () => {
    document.querySelectorAll('.card-dropdown').forEach(d => d.classList.remove('open'));
});

// ============================
// SHARE & REPORT
// ============================
function sharePhoto(title) {
    if (navigator.share) { navigator.share({ title, url: window.location.href }).catch(()=>{}); }
    else { navigator.clipboard.writeText(window.location.href).then(() => showToast('Link berhasil disalin!')); }
}
function reportPhoto() { showToast('Foto berhasil dilaporkan. Terima kasih.'); }

// ============================
// TOAST
// ============================
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
}

// ============================
// INIT — tampilkan halaman 0
// ============================
document.addEventListener('DOMContentLoaded', () => {
    // Masonry: tampilkan semua kartu, sembunyikan pagination
    document.querySelectorAll('.gallery-card').forEach(c => c.style.display = '');
    const wrap = document.getElementById('paginationWrap');
    if (wrap) wrap.classList.add('hidden');
});
</script>

</body>
</html>