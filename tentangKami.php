<?php
// ============================================================
//  tentangKami.php — Halaman Tentang Kami (REFAKTOR)
// ============================================================
require_once 'config.php';
require_once 'navbar.php';
require_once 'footer.php';

session_start();
require_once 'visitor_tracker.php';
$session = getSession();
$pdo     = getDB();


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - JelajahinNusa</title>
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
        .btn{ display:inline-block; padding:12px 28px; border-radius:30px; font-weight:500; font-size:16px; transition:.3s; border:none; cursor:pointer; }
        .btn-primary{ background:var(--text-primary); color:#000; }
        .btn-primary:hover{ opacity:.9; }
        /* Background blur */
        .background{ position:fixed; top:0; left:0; width:100%; height:100%; z-index:-1; overflow:hidden; }
        .background img{ width:100%; height:100%; object-fit:cover; opacity:.3; }
        /* Konten */
        .about-content{ padding:60px 0; text-align:justify; max-width:820px; margin:0 auto; }
        .about-content h1{ font-size:40px; font-weight:700; margin-bottom:30px; text-align:center; }
        .about-content p{ margin-bottom:20px; line-height:1.85; color:rgba(255,255,255,.85); font-size:16px; }
        /* Values */
        .values-section{ padding:60px 0; }
        .values-section h2{ font-size:30px; font-weight:700; text-align:center; margin-bottom:40px; }
        .values-grid{ display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
        @media(max-width:768px){ .values-grid{ grid-template-columns:1fr; } }
        .value-card{ background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); border-radius:14px; padding:28px; text-align:center; }
        .value-card i{ font-size:36px; color:#6fcf97; margin-bottom:16px; display:block; }
        .value-card h3{ font-size:18px; font-weight:600; margin-bottom:10px; }
        .value-card p{ font-size:14px; color:var(--text-secondary); line-height:1.7; }
    </style>
</head>
<body>

<div class="background">
    <img src="Gambar/hero.jpg" alt="Latar Belakang">
</div>

<?php renderNavbar($session, 'tentangKami.php'); ?>

<main>
    <div class="container">
        <div class="about-content">
            <h1>Jelajahi Keindahan Nusantara</h1>
            <p>Selamat datang di <strong>JelajahinNusa</strong>, katalog wisata Nusantara yang menyediakan informasi lengkap tentang destinasi wisata terbaik di Indonesia. Kami berdedikasi untuk membantu Anda menemukan pengalaman wisata yang tak terlupakan di negeri yang kaya akan budaya dan keindahan alam ini.</p>
            <p><strong>Misi kami</strong> adalah untuk menjadi sumber informasi terpercaya dan komprehensif tentang wisata Nusantara, sehingga Anda dapat dengan mudah menemukan dan merencanakan perjalanan wisata yang sesuai dengan minat dan kebutuhan Anda.</p>
            <p><strong>Visi kami</strong> adalah untuk menjadi katalog wisata online terkemuka di Indonesia, yang tidak hanya menyediakan informasi tentang destinasi wisata, tetapi juga membantu mempromosikan pariwisata Indonesia dan meningkatkan kesadaran akan pentingnya melestarikan budaya dan lingkungan.</p>
        </div>
    </div>

    <section class="values-section">
        <div class="container">
            <h2>Nilai-Nilai Kami</h2>
            <div class="values-grid">
                <div class="value-card">
                    <i class="fas fa-map-marked-alt"></i>
                    <h3>Eksplorasi</h3>
                    <p>Mendorong setiap orang untuk mengeksplorasi kekayaan alam dan budaya Nusantara yang luar biasa.</p>
                </div>
                <div class="value-card">
                    <i class="fas fa-leaf"></i>
                    <h3>Pelestarian</h3>
                    <p>Berkomitmen untuk mempromosikan wisata yang bertanggung jawab demi menjaga kelestarian lingkungan.</p>
                </div>
                <div class="value-card">
                    <i class="fas fa-users"></i>
                    <h3>Komunitas</h3>
                    <p>Membangun komunitas penjelajah yang saling berbagi pengalaman dan inspirasi perjalanan.</p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
renderFooter();
?>

</body>
</html>