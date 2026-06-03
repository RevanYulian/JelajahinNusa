<?php
// ============================================================
//  index.php — Halaman Utama JelajahinNusa (REFAKTOR)
// ============================================================
require_once 'config.php';
require_once 'navbar.php';
require_once 'footer.php';
session_start();
require_once 'visitor_tracker.php';
$session = getSession();
$pdo     = getDB();

$cfgRows = $pdo->query("SELECT kunci, nilai FROM pengaturan_situs")->fetchAll(PDO::FETCH_ASSOC);
$cfg = [];
foreach ($cfgRows as $r) $cfg[$r['kunci']] = $r['nilai'];

// Hero slider: selalu ambil 3 artikel dengan views terbanyak secara otomatis
$heroSlides = $pdo->query("SELECT id, title, image, excerpt FROM artikel WHERE status='published' ORDER BY views DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

$daerahData = $cfg['landing_daerah'] ?? null;
$daerahList = $daerahData ? json_decode($daerahData, true) : [
    ['nama'=>'Jawa',                'gambar'=>'Gambar/jawa.jpg',        'link'=>'artikel.php?search=jawa'],
    ['nama'=>'Kalimantan',          'gambar'=>'Gambar/kalimantan.jpg',   'link'=>'artikel.php?search=kalimantan'],
    ['nama'=>'Sulawesi',            'gambar'=>'Gambar/sulawesi.jpg',     'link'=>'artikel.php?search=sulawesi'],
    ['nama'=>'Sumatra',             'gambar'=>'Gambar/sumatra.jpg',      'link'=>'artikel.php?search=sumatra'],
    ['nama'=>'Maluku & Papua',      'gambar'=>'Gambar/papua.jpg',        'link'=>'artikel.php?search=papua'],
    ['nama'=>'Bali & Nusa Tenggara','gambar'=>'Gambar/nusa.jpg',        'link'=>'artikel.php?search=bali'],
];

$kategoriUtama = [
    'pantai-laut' => 'Wisata Pantai & Laut',
    'gunung'      => 'Wisata Gunung',
    'air-terjun'  => 'Wisata Air Terjun',
    'danau'       => 'Wisata Danau',
];
$slugList = array_keys($kategoriUtama);
$phAll    = implode(',', array_fill(0, count($slugList), '?'));
$stmtNew  = $pdo->prepare(
    "SELECT a.id, a.title, a.image,
            COALESCE(a.excerpt,'') AS excerpt,
            a.created_at,
            k.slug AS kategori_slug, k.nama AS kategori_nama
     FROM artikel a
     JOIN kategori_artikel k ON a.kategori_id=k.id
     WHERE k.slug IN ($phAll) AND a.status='published'
     ORDER BY a.created_at DESC LIMIT 10"
);
$stmtNew->execute($slugList);
$wisataTerbaru = $stmtNew->fetchAll(PDO::FETCH_ASSOC);

if (empty($wisataTerbaru)) {
    $stmtFallback = $pdo->query(
        "SELECT a.id, a.title, a.image,
                COALESCE(a.excerpt,'') AS excerpt,
                a.created_at,
                k.slug AS kategori_slug, k.nama AS kategori_nama
         FROM artikel a
         JOIN kategori_artikel k ON a.kategori_id=k.id
         WHERE a.status='published'
         ORDER BY a.created_at DESC LIMIT 10"
    );
    $wisataTerbaru = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
}

$kategoriIcon = [
    'gunung'      => 'fa-mountain',
    'danau'       => 'fa-water',
    'pantai-laut' => 'fa-umbrella-beach',
    'air-terjun'  => 'fa-tint',
];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JelajahinNusa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root{ --dark-bg:#000; --dark-bg-secondary:#1A1A1A; --text-primary:#FFF; --text-secondary:#AAA; --accent-green:#1F4529; --card-overlay:linear-gradient(180deg,rgba(0,0,0,0) 0%,rgba(0,0,0,.7) 100%); }
        *{ margin:0; padding:0; box-sizing:border-box; }
        html{ scroll-behavior:smooth; }
        body{ overflow-x:hidden; font-family:'Poppins',Arial,sans-serif; background:var(--dark-bg); color:var(--text-primary); line-height:1.6; }
        @media(min-width:1025px){ body{ zoom:90%; } }
        .container{ max-width:1200px; margin:0 auto; padding:0 30px; }
        a{ text-decoration:none; color:inherit; }
        ul{ list-style:none; }
        img{ max-width:100%; display:block; }
        section{ padding:80px 0; }
        .btn{ display:inline-block; padding:12px 28px; border-radius:30px; font-weight:500; font-size:16px; transition:.3s; border:none; cursor:pointer; }
        .btn-primary{ background:var(--text-primary); color:#000; }
        .btn-primary:hover{ opacity:.9; }
        .btn-outline{ background:rgba(255,255,255,.1); backdrop-filter:blur(5px); color:var(--text-primary); border:1px solid rgba(255,255,255,.2); }
        .btn-outline:hover{ background:rgba(255,255,255,.2); }
        /* Hero */
        .hero{ height:95vh; background:var(--card-overlay) center/cover no-repeat; display:flex; align-items:center; position:relative; transition:background-image .8s ease-in-out; }
        .hero .container{ position:relative; z-index:2; }
        .hero-content{ max-width:560px; opacity:1; transition:opacity .5s ease; }
        .hero-content h1{ font-size:38px; font-weight:600; margin:5px 0 15px; line-height:1.25; }
        .hero-content p{ font-size:16px; color:var(--text-secondary); margin-bottom:30px; max-width:400px; }
        .hero-dots{ position:absolute; bottom:50px; right:50px; display:flex; flex-direction:column; gap:12px; z-index:2; }
        .dot{ width:10px; height:10px; border-radius:50%; background:rgba(255,255,255,.3); cursor:pointer; transition:.3s; }
        .dot.active{ background:var(--text-primary); transform:scale(1.2); }
        /* Scroll arrow */
        .hero-scroll-arrow{ position:absolute; bottom:36px; left:50%; transform:translateX(-50%); z-index:2; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:4px; opacity:.7; transition:opacity .3s; border:none; background:none; padding:8px; }
        .hero-scroll-arrow:hover{ opacity:1; }
        .hero-scroll-arrow span{ display:block; width:20px; height:20px; border-right:2px solid #fff; border-bottom:2px solid #fff; transform:rotate(45deg); animation:scrollBounce 1.4s ease-in-out infinite; }
        @keyframes scrollBounce{ 0%,100%{ transform:rotate(45deg) translate(0,0); } 50%{ transform:rotate(45deg) translate(5px,5px); } }
        /* Popular */
        .popular-destinations{ padding:90px 0; }
        .popular-destinations .section-title{ font-size:36px; font-weight:700; text-align:left; margin-bottom:10px; padding:0; }
        .popular-destinations .section-subtitle{ font-size:14px; color:rgba(255,255,255,.4); margin-bottom:44px; }
        .popular-grid{ display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
        .popular-card{ position:relative; border-radius:18px; height:200px; overflow:hidden; cursor:pointer; }
        .popular-card a{ display:block; width:100%; height:100%; }
        .card-img{ width:100%; height:100%; object-fit:cover; display:block; transition:transform .6s cubic-bezier(.4,0,.2,1); }
        .popular-card:hover .card-img{ transform:scale(1.07); }
        .card-content{ position:absolute; inset:0; background:linear-gradient(to top,rgba(0,0,0,.72) 0%,rgba(0,0,0,.18) 50%,transparent 100%); display:flex; flex-direction:column; justify-content:flex-end; padding:24px 26px; transition:background .4s ease; z-index:2; }
        .popular-card:hover .card-content{ background:linear-gradient(to top,rgba(0,0,0,.85) 0%,rgba(0,0,0,.3) 55%,transparent 100%); }
        .card-region-tag{ font-size:10px; font-weight:500; letter-spacing:2px; text-transform:uppercase; color:rgba(255,255,255,.55); margin-bottom:6px; opacity:0; transform:translateY(6px); transition:opacity .35s,transform .35s; }
        .popular-card:hover .card-region-tag{ opacity:1; transform:translateY(0); }
        .card-content h4{ font-size:24px; font-weight:700; line-height:1.15; color:#fff; }
        .card-explore{ display:inline-flex; align-items:center; gap:6px; margin-top:10px; font-size:11px; font-weight:500; color:rgba(255,255,255,.7); opacity:0; transform:translateY(8px); transition:opacity .35s .05s,transform .35s .05s; }
        .popular-card:hover .card-explore{ opacity:1; transform:translateY(0); }
        /* CTA */
        .cta-section{ padding:80px 0; text-align:center; }
        .cta-section h2{ font-size:38px; font-weight:700; max-width:800px; margin:0 auto 15px; line-height:1.2; }
        .cta-section p{ font-size:17px; color:var(--text-secondary); max-width:600px; margin:0 auto 30px; }
        /* Rekomendasi */
        .reco-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:20px; }
        .reco-card{ border-radius:15px; overflow:hidden; height:220px; position:relative; transition:transform .3s; }
        .reco-card:hover{ transform:translateY(-5px); }
        .reco-card .card-content h4{ font-size:18px; }
        .reco-card-link{ display:block; height:100%; }
        .reco-footer{ text-align:center; margin-top:30px; }
        /* App CTA */
        .app-cta{ background:#1F4529; border-radius:20px; margin:0 30px 60px; }
        .app-cta-content{ display:flex; justify-content:space-between; align-items:center; gap:40px; padding:60px; }
        .app-cta-text h3{ font-size:28px; font-weight:700; margin-bottom:30px; line-height:1.4; }
        .app-buttons{ display:flex; align-items:center; gap:15px; flex-wrap:wrap; }
        .app-buttons img{ height:45px; width:auto; }
        /* Wisata Terbaru */
        .wisata-terbaru{ background:#000; padding:70px 0 80px; }
        .wt-header{ display:flex; align-items:flex-end; justify-content:space-between; gap:20px; max-width:1200px; margin:0 auto 28px; padding:0 30px; }
        .wt-header-left{ display:flex; flex-direction:column; gap:6px; }
        .wt-label{ display:inline-flex; align-items:center; gap:6px; font-size:11px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:rgba(255,255,255,.5); }
        .wt-header h2{ font-size:30px; font-weight:700; line-height:1.25; color:#fff; margin:0; }
        .wt-header-right{ display:flex; align-items:center; gap:12px; flex-shrink:0; padding-bottom:4px; }
        .wt-counter{ font-size:13px; color:rgba(255,255,255,.4); white-space:nowrap; }
        .wt-counter strong{ font-size:20px; font-weight:700; color:#fff; }
        .wt-btn{ width:34px; height:34px; border-radius:50%; border:1px solid rgba(255,255,255,.2); background:rgba(255,255,255,.04); color:#fff; font-size:11px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:.25s; }
        .wt-btn:hover:not(:disabled){ background:rgba(255,255,255,.12); border-color:rgba(255,255,255,.6); transform:scale(1.08); }
        .wt-btn:disabled{ opacity:.22; cursor:default; }
        .wt-progress-bar{ max-width:1200px; margin:0 auto 22px; padding:0 30px; }
        .wt-progress-bar-inner{ width:100%; height:1px; background:rgba(255,255,255,.2); }
        .wt-progress-fill{ display:none; }
        .wt-cards-wrap{ max-width:1260px; margin:0 auto; padding:0 30px; overflow:hidden; }
        .wt-track{ display:flex; height:360px; gap:14px; will-change:transform; }
        .wt-card{ flex:0 0 calc((100% - 28px)/3); position:relative; overflow:hidden; text-decoration:none; color:inherit; display:block; border-radius:16px; flex-shrink:0; border:1px solid rgba(255,255,255,.06); transition:transform .35s,box-shadow .35s,border-color .35s; }
        .wt-card:hover{ transform:translateY(-6px); box-shadow:0 20px 50px rgba(0,0,0,.6); border-color:rgba(255,255,255,.2); }
        .wt-card-img{ width:100%; height:100%; object-fit:cover; display:block; transition:transform .6s; }
        .wt-card:hover .wt-card-img{ transform:scale(1.07); }
        .wt-card-overlay{ position:absolute; inset:0; background:linear-gradient(180deg,transparent 0%,transparent 35%,rgba(0,0,0,.4) 60%,rgba(0,0,0,.88) 100%); }
        @keyframes pulse-dot{ 0%,100%{ opacity:1; transform:scale(1); } 50%{ opacity:.4; transform:scale(.7); } }
        .wt-badge-new{ position:absolute; top:14px; left:14px; background:rgba(255,255,255,.15); backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,.25); color:#fff; font-size:9px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; padding:4px 10px; border-radius:20px; z-index:3; display:flex; align-items:center; gap:4px; }
        .wt-badge-new::before{ content:''; width:5px; height:5px; border-radius:50%; background:#fff; animation:pulse-dot 1.5s ease-in-out infinite; }
        .wt-badge-cat{ position:absolute; top:14px; right:14px; background:rgba(0,0,0,.55); backdrop-filter:blur(8px); color:rgba(255,255,255,.85); font-size:10px; padding:4px 10px; border-radius:20px; z-index:3; border:1px solid rgba(255,255,255,.15); }
        .wt-card-body{ position:absolute; bottom:0; left:0; right:0; padding:26px 22px 24px; z-index:2; }
        .wt-card-body h3{ font-size:15px; font-weight:700; line-height:1.35; color:#fff; margin:0 0 8px; }
        .wt-card-excerpt{ font-size:12px; color:rgba(255,255,255,.6); line-height:1.55; margin:0 0 12px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .wt-card-cta{ display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:600; color:rgba(255,255,255,.8); opacity:0; transform:translateY(6px); transition:opacity .3s,transform .3s; }
        .wt-card:hover .wt-card-cta{ opacity:1; transform:translateY(0); }
        @keyframes shimmer{ 0%{ background-position:-400px 0; } 100%{ background-position:400px 0; } }
        .wt-card-skeleton{ flex:0 0 calc((100% - 28px)/3); border-radius:20px; background:linear-gradient(90deg,#111 25%,#1e1e1e 50%,#111 75%); background-size:800px 100%; animation:shimmer 1.5s infinite; }
        @media(max-width:800px){ .wt-track{ height:320px; } .wt-card{ flex:0 0 calc(78% - 7px); } }
        @media(max-width:768px){
            section{ padding:50px 0; }
            .container{ padding:0 16px; }

            /* Hero */
            .hero{ height:calc(100dvh - 60px); }
            .hero-content h1{ font-size:22px; }
            .hero-content p{ font-size:13px; }
            .hero-dots{ bottom:20px; right:20px; }

            /* Destinasi */
            .popular-destinations{ padding:50px 0; }
            .popular-destinations .section-title{ font-size:24px; }
            .popular-grid{ grid-template-columns:repeat(2,1fr); gap:10px; }
            .popular-card{ height:150px; }
            .card-content h4{ font-size:16px; }

            /* CTA */
            .cta-section h2{ font-size:22px; }
            .cta-section p{ font-size:13px; }

            /* Wisata Terbaru */
            .wt-header{ padding:0 16px; flex-wrap:wrap; gap:10px; }
            .wt-header h2{ font-size:20px; }
            .wt-cards-wrap{ padding:0 16px; }
            .wt-track{ height:260px; }
            .wt-card{ flex:0 0 calc(85% - 7px); }

            /* App CTA */
            .app-cta{ margin:0 16px 40px; border-radius:16px; }
            .app-cta-content{ flex-direction:column; padding:28px 20px; gap:20px; text-align:center; }
            .app-cta-text h3{ font-size:18px; margin-bottom:16px; }
            .app-buttons{ justify-content:center; flex-wrap:wrap; gap:10px; }
            .app-buttons img{ height:36px; }
            .app-cta-img{ display:none; }

            /* Reko */
            .reco-grid{ grid-template-columns:repeat(2,1fr); gap:12px; }
            .reco-card{ height:160px; }
        }
        @media(max-width:400px){
            .popular-grid{ grid-template-columns:1fr 1fr; gap:8px; }
            .popular-card{ height:120px; }
            .app-cta-text h3{ font-size:15px; }
        }
    </style>
</head>
<body>

<?php renderNavbar($session, 'index.php'); ?>

<main>
    <!-- Hero Slider -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content" id="hero-content"></div>
        </div>
        <div class="hero-dots" id="hero-dots"></div>
        <button class="hero-scroll-arrow" onclick="document.getElementById('artikel').scrollIntoView({behavior:'smooth'})" aria-label="Scroll ke bawah">
            <span></span>
        </button>
    </section>

    <!-- Destinasi Berdasarkan Daerah -->
    <section class="popular-destinations" id="artikel">
        <div class="container">
            <h2 class="section-title">Jelajahi Nusantara</h2>
            <p class="section-subtitle">Temukan keindahan alam dari setiap sudut kepulauan Indonesia</p>
            <div class="popular-grid">
                <?php foreach ($daerahList as $d): ?>
                <div class="popular-card">
                    <a href="<?= htmlspecialchars($d['link']) ?>">
                        <img src="<?= htmlspecialchars($d['gambar']) ?>" alt="<?= htmlspecialchars($d['nama']) ?>" class="card-img">
                        <div class="card-content">
                            <span class="card-region-tag">Destinasi &middot; Indonesia</span>
                            <h4><?= htmlspecialchars($d['nama']) ?></h4>
                            <span class="card-explore">Jelajahi <i class="fas fa-arrow-right" style="font-size:9px"></i></span>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA Galeri -->
    <section class="cta-section">
        <div class="container">
            <h2>Cari, Lihat, Ceritakan!</h2>
            <p>Lihat foto-foto memukau dan kisah nyata dari sesama penjelajah Nusantara.</p>
            <a href="galeri.php" class="btn btn-primary">Mulai Jelajahi Sekarang <i class="fas fa-arrow-right"></i></a>
        </div>
    </section>

    <!-- Wisata Terbaru -->
    <?php if (!empty($wisataTerbaru)):
        $now24h = strtotime('-24 hours');
        $wtJson = array_map(function($w) use ($kategoriIcon, $kategoriUtama, $now24h) {
            $isNew = (strtotime($w['created_at'] ?? '') >= $now24h);
            return [
                'title'    => $w['title'],
                'image'    => $w['image'] ?? 'Gambar/hero.jpg',
                'excerpt'  => mb_substr(strip_tags($w['excerpt'] ?? ''), 0, 120),
                'href'     => 'artikel_viewer.php?id=' . urlencode($w['id']),
                'kategori' => $w['kategori_nama'] ?? '',
                'isNew'    => $isNew,
            ];
        }, $wisataTerbaru);
        $total = count($wisataTerbaru);
    ?>
    <section class="wisata-terbaru" id="wisata-terbaru">
        <div class="wt-header">
            <div class="wt-header-left">
                <h2>Artikel Wisata Alam</h2>
                <div class="wt-label">Terbaru</div>
            </div>
            <div class="wt-header-right">
                <button class="wt-btn" id="wtPrev"><i class="fas fa-chevron-left"></i></button>
                <button class="wt-btn" id="wtNext"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        <div class="wt-progress-bar"><div class="wt-progress-bar-inner"><div class="wt-progress-fill" id="wtProgress"></div></div></div>
        <div class="wt-cards-wrap"><div class="wt-track" id="wtTrack"></div></div>
    </section>
    <script>
    (function(){
        const data=<?= json_encode($wtJson, JSON_UNESCAPED_UNICODE) ?>;
        const total=data.length;
        const track=document.getElementById('wtTrack');
        const curNumEl=document.getElementById('wtCurNum');
        if(!track||total===0)return;

        function makeCard(d){
            const card=document.createElement('a');
            card.className='wt-card'; card.href=d.href;
            card.innerHTML=`<img src="${d.image}" class="wt-card-img" alt="${d.title}" loading="lazy">
            <div class="wt-card-overlay"></div>
            ${d.isNew?'<span class="wt-badge-new">Baru</span>':''}
            ${d.kategori?`<span class="wt-badge-cat">${d.kategori}</span>`:''}
            <div class="wt-card-body"><h3>${d.title}</h3>
            ${d.excerpt?`<p class="wt-card-excerpt">${d.excerpt}…</p>`:''}
            <span class="wt-card-cta">Lihat Selengkapnya <i class="fas fa-arrow-right" style="font-size:9px"></i></span>
            </div>`;
            return card;
        }

        // Clone: tambah 3 kartu terakhir di depan, 3 kartu pertama di belakang
        const CLONE=3;
        const clonesBefore=data.slice(-CLONE);
        const clonesAfter=data.slice(0,CLONE);
        [...clonesBefore,...data,...clonesAfter].forEach(d=>track.appendChild(makeCard(d)));

        const GAP=14,DURATION=5000;
        let cur=CLONE; // mulai dari kartu real pertama
        let busy=false,autoTimer=null;
        const progressEl=document.getElementById('wtProgress');

        function getCardW(){ const c=track.querySelector('.wt-card'); return c?c.offsetWidth+GAP:0; }
        function setTranslate(idx,animated){
            track.style.transition=animated?'transform .55s cubic-bezier(.4,0,.2,1)':'none';
            track.style.transform=`translateX(${-idx*getCardW()}px)`;
        }
        function updateCounter(){ }
        function goTo(idx,animated){
            cur=idx; setTranslate(cur,animated); updateCounter();
        }
        function afterSlide(){
            // Kalau sudah masuk zona clone, reset diam-diam ke real
            if(cur>=total+CLONE){ goTo(CLONE+(cur-total-CLONE),false); }
            else if(cur<CLONE){ goTo(total+cur,false); }
            busy=false;
        }
        track.addEventListener('transitionend',afterSlide);

        function slide(dir){
            if(busy)return; busy=true;
            goTo(cur+dir,true);
            setTimeout(()=>{ if(busy){ afterSlide(); } },600);
        }
        function startProgress(){ if(!progressEl)return; progressEl.style.transition='none'; progressEl.style.width='0%'; requestAnimationFrame(()=>requestAnimationFrame(()=>{ progressEl.style.transition=`width ${DURATION}ms linear`; progressEl.style.width='100%'; })); }
        function stopProgress(){ if(!progressEl)return; progressEl.style.transition='none'; progressEl.style.width='0%'; }
        function startAuto(){ clearInterval(autoTimer); startProgress(); autoTimer=setInterval(()=>slide(1),DURATION); }
        function stopAuto(){ clearInterval(autoTimer); stopProgress(); }

        document.getElementById('wtPrev').addEventListener('click',()=>{ slide(-1); startAuto(); });
        document.getElementById('wtNext').addEventListener('click',()=>{ slide(1); startAuto(); });
        document.getElementById('wtPrev').disabled=false;
        document.getElementById('wtNext').disabled=false;

        const wrap=document.querySelector('.wt-cards-wrap');
        if(wrap){ wrap.addEventListener('mouseenter',stopAuto); wrap.addEventListener('mouseleave',startAuto); }
        let touchStartX=0;
        if(wrap){
            wrap.addEventListener('touchstart',e=>{ touchStartX=e.touches[0].clientX; },{passive:true});
            wrap.addEventListener('touchend',e=>{ const dx=touchStartX-e.changedTouches[0].clientX; if(Math.abs(dx)>40){ slide(dx>0?1:-1); startAuto(); } },{passive:true});
        }
        goTo(CLONE,false); startAuto();
        window.addEventListener('resize',()=>goTo(cur,false));
    })();
    </script>
    <?php endif; ?>

    <!-- App CTA -->
    <section class="app-cta" id="testimoni">
        <div class="container">
            <div class="app-cta-content">
                <div class="app-cta-text">
                    <h3>Bingung Mau cari Aplikasi Pesan Tiket Liburan<br>Yang Serba Ada Semua Dimana?<br>TiketinNusa Solusinya</h3>
                    <div class="app-buttons">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/7/78/Google_Play_Store_badge_EN.svg" alt="Google Play" height="60">
                        <a href="#" class="logo"><img src="Gambar/Tiketin.png" alt="TiketinNusa" height="60"></a>
                        <img src="https://upload.wikimedia.org/wikipedia/commons/3/3c/Download_on_the_App_Store_Badge.svg" alt="App Store" height="60">
                    </div>
                </div>
                <div class="app-cta-img"><img src="Gambar/phone.png" alt="App Mockup" height="300"></div>
            </div>
        </div>
    </section>
</main>

<?php renderFooter(); ?>

<script>
// Hero Slider
const slidesData = <?= json_encode(array_map(fn($a) => [
    'title'       => $a['title'],
    'description' => $a['excerpt'] ?? '',
    'image'       => $a['image'] ?? 'Gambar/hero.jpg',
    'linkUrl'     => 'artikel_viewer.php?id=' . $a['id'],
], $heroSlides), JSON_UNESCAPED_UNICODE) ?>;

let currentSlideIndex = 0;
const heroElement = document.querySelector('.hero');
const heroContent = document.getElementById('hero-content');
const heroDotsContainer = document.getElementById('hero-dots');
let sliderTimer;

function updateSlide(index) {
    const slide = slidesData[index];
    heroElement.style.backgroundImage = `var(--card-overlay), url(${slide.image})`;
    heroContent.style.opacity = 0;
    setTimeout(() => {
        heroContent.innerHTML = `<h1>${slide.title}</h1><p>${slide.description}</p>
        <a href="${slide.linkUrl}" class="btn btn-outline">Jelajahi <i class="fas fa-arrow-right"></i></a>`;
        heroContent.style.opacity = 1;
    }, 300);
    document.querySelectorAll('.dot').forEach((dot, i) => dot.classList.toggle('active', i === index));
    currentSlideIndex = index;
}

function nextSlide() { updateSlide((currentSlideIndex + 1) % slidesData.length); }

function initSlider() {
    heroDotsContainer.innerHTML = '';
    slidesData.forEach((_, index) => {
        const dot = document.createElement('span');
        dot.classList.add('dot');
        if (index === 0) dot.classList.add('active');
        dot.addEventListener('click', () => { clearInterval(sliderTimer); updateSlide(index); sliderTimer = setInterval(nextSlide, 5000); });
        heroDotsContainer.appendChild(dot);
    });
    updateSlide(0);
    sliderTimer = setInterval(nextSlide, 5000);
}

document.addEventListener('DOMContentLoaded', initSlider);
</script>
</body>
</html>