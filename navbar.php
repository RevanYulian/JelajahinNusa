<?php
// ============================================================
//  navbar.php — Komponen Navbar + CSS Navbar
//
//  CARA PAKAI:
//    require_once 'navbar.php';
//    renderNavbar($session, 'index.php');
// ============================================================

function renderNavbarCSS(): void { ?>
<style>
/* ── NAVBAR CSS ── */
header{position:sticky;top:0;z-index:9000;background:rgba(18,18,18,.85);backdrop-filter:blur(10px);overflow:visible;}
.navbar{display:flex;justify-content:space-between;align-items:center;padding:0;}
.logo img{height:100px;width:auto;}
.nav-menu ul{display:flex;gap:45px;}
.nav-menu li a{color:var(--text-secondary);font-weight:500;transition:.3s;}
.nav-menu li a:hover,.nav-menu li a.active{color:#fff;}
.header-right{display:flex;align-items:center;gap:20px;}
.search-wrapper{ position:relative; }
.search-bar{ display:flex; align-items:center; border:1px solid var(--text-secondary); border-radius:30px; padding:8px 15px; transition:.25s; }
.search-bar:focus-within{ border-color:#fff; background:rgba(255,255,255,.06); }
.search-bar i{ color:var(--text-secondary); }
.search-bar input{ background:transparent; border:none; outline:none; color:#fff; margin-left:10px; font-family:'Poppins',sans-serif; width:160px; }
/* Autocomplete dropdown */
.search-suggestions{
    display:none; position:absolute; top:calc(100% + 10px); left:50%; transform:translateX(-50%);
    width:480px;
    background:rgba(18,18,18,.96); border:1px solid rgba(255,255,255,.1); border-radius:18px;
    z-index:99999; overflow:hidden;
    box-shadow:0 20px 60px rgba(0,0,0,.8), 0 0 0 1px rgba(255,255,255,.04);
    backdrop-filter:blur(20px);
    opacity:0; transform:translateX(-50%) translateY(-6px);
    transition:opacity .18s ease, transform .18s ease;
    pointer-events:none;
    display:flex; flex-direction:column; max-height:520px;
}
.search-suggestions.open{
    display:flex; opacity:1; transform:translateX(-50%) translateY(0);
    pointer-events:auto;
}
.suggestion-scroll{
    overflow-y:auto; flex:1;
    scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.1) transparent;
}
.suggestion-scroll::-webkit-scrollbar{ width:4px; }
.suggestion-scroll::-webkit-scrollbar-thumb{ background:rgba(255,255,255,.12); border-radius:4px; }
.suggestion-footer-split{
    flex-shrink:0;
}
.suggestion-item{
    display:flex; align-items:center; gap:12px;
    padding:10px 16px; cursor:pointer; transition:.15s;
    text-decoration:none; color:inherit; position:relative;
}
.suggestion-item::before{
    content:''; position:absolute; left:16px; right:16px; bottom:0;
    height:1px; background:rgba(255,255,255,.04);
}
.suggestion-item:last-of-type::before{ display:none; }
.suggestion-item:hover,.suggestion-item.active{
    background:rgba(255,255,255,.06);
}
.suggestion-item:hover .suggestion-arrow{ opacity:1; transform:translateX(0); }
/* Thumbnail artikel — kotak kecil */
.suggestion-thumb{
    width:46px; height:46px; border-radius:10px; object-fit:cover;
    flex-shrink:0; background:#2a2a2a;
}
/* Thumbnail galeri — landscape */
.suggestion-thumb.galeri-thumb{
    width:70px; height:46px; border-radius:10px;
}
.suggestion-thumb-icon{
    width:46px; height:46px; border-radius:10px; background:#1e1e1e;
    display:flex; align-items:center; justify-content:center;
    color:#444; font-size:18px; flex-shrink:0;
    border:1px solid rgba(255,255,255,.06);
}
.suggestion-text{ flex:1; overflow:hidden; }
.suggestion-title{
    font-size:13px; font-weight:500; color:#e8e8e8;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    line-height:1.4;
}
.suggestion-title mark{ background:none; color:#6fcf97; font-weight:700; }
.suggestion-meta{ font-size:11px; color:#555; margin-top:3px; display:flex; align-items:center; gap:5px; }
.suggestion-kat{
    display:inline-flex; align-items:center; font-size:10px; font-weight:600;
    background:rgba(31,69,41,.4); color:#6fcf97;
    padding:2px 8px; border-radius:20px;
    border:1px solid rgba(111,207,151,.15);
}
.suggestion-kat.galeri-kat{ background:rgba(41,55,69,.4); color:#74b9ff; border-color:rgba(116,185,255,.15); }
.suggestion-arrow{
    color:#444; font-size:12px; flex-shrink:0;
    opacity:0; transform:translateX(-4px); transition:.15s;
}
/* Group label */
.suggestion-group-label{
    padding:10px 16px 5px; font-size:10px; font-weight:700; color:#444;
    text-transform:uppercase; letter-spacing:1px;
    display:flex; align-items:center; gap:7px;
}
.suggestion-group-label i{ font-size:11px; }
/* Footer */
.suggestion-footer-split{
    display:flex; align-items:stretch;
    border-top:1px solid rgba(255,255,255,.06);
}
.suggestion-footer-btn{
    flex:1; display:flex; align-items:center; justify-content:center; gap:7px;
    padding:11px 14px; font-size:12px; color:#555; transition:.15s;
    text-decoration:none;
}
.suggestion-footer-btn:hover{ color:#fff; background:rgba(255,255,255,.04); }
.suggestion-footer-btn:first-child{ border-radius:0 0 0 18px; }
.suggestion-footer-btn:last-child{ border-radius:0 0 18px 0; }
.suggestion-footer-divider{ width:1px; background:rgba(255,255,255,.06); flex-shrink:0; }
/* Empty */
.suggestion-empty{
    padding:28px 16px; text-align:center; color:#444; font-size:13px;
    display:flex; flex-direction:column; align-items:center; gap:8px;
}
.suggestion-empty i{ font-size:28px; color:#2a2a2a; }
.user-profile{display:flex;align-items:center;cursor:pointer;position:relative;}
.user-profile i{font-size:30px;color:#fff;}
.user-dropdown{display:none;position:absolute;top:calc(100% + 10px);right:0;background:#1A1A1A;border:1px solid #333;border-radius:10px;min-width:210px;z-index:9999;padding:8px 0;box-shadow:0 8px 30px rgba(0,0,0,.6);}
.user-dropdown a{display:block;padding:10px 18px;color:var(--text-secondary);font-size:14px;transition:.2s;}
.user-dropdown a:hover{color:#fff;background:rgba(255,255,255,.05);}
.user-dropdown.open{display:block;}
.auth-buttons{display:flex;align-items:center;gap:10px;}
.btn-masuk{padding:8px 20px;border-radius:30px;font-size:14px;font-weight:500;color:#fff;border:1px solid rgba(255,255,255,.35);transition:.3s;}
.btn-masuk:hover{background:rgba(255,255,255,.1);border-color:#fff;}
.btn-daftar{padding:8px 20px;border-radius:30px;font-size:14px;font-weight:500;background:#fff;color:#000;transition:.3s;}
.btn-daftar:hover{opacity:.85;}

.menu-toggle-nav{display:none;font-size:24px;cursor:pointer;color:#fff;}
@media(max-width:992px){
    .nav-menu{display:none;position:absolute;top:100%;left:0;width:100%;background:rgba(18,18,18,.98);padding:20px;}
    .nav-menu.active{display:block;}
    .nav-menu ul{flex-direction:column;gap:16px;}
    .menu-toggle-nav{display:block;}
    .btn-masuk,.btn-daftar{padding:7px 14px;font-size:13px;}
}
@media(max-width:768px){
    .logo img{height:60px;}
    .search-bar{ padding:6px 10px; }
    .search-bar input{ width:90px; font-size:12px; }
    .header-right{gap:8px;}
    .search-suggestions{width:calc(100vw - 32px);left:50%;transform:translateX(-50%);}
}
</style>
<?php }

function renderNavbar(array|null $session, string $activeNav = ''): void
{
    renderNavbarCSS();

    $name    = htmlspecialchars($session['username']      ?? '');
    $photo   = htmlspecialchars($session['profile_photo'] ?? $session['photo'] ?? '');
    $isAdmin = ($session['type'] ?? '') === 'admin';

    // Baca logo dari pengaturan DB, fallback ke logo default
    $siteLogo = 'Gambar/logo2.png';
    if (function_exists('getSiteSetting')) {
        $dbLogo = getSiteSetting('site_logo', '');
        if ($dbLogo) $siteLogo = $dbLogo;
    } else {
        // Fallback langsung query jika getSiteSetting belum tersedia
        try {
            if (function_exists('getDB')) {
                $pdo2 = getDB();
                $s2 = $pdo2->prepare("SELECT nilai FROM pengaturan_situs WHERE kunci='site_logo' LIMIT 1");
                $s2->execute();
                $row2 = $s2->fetch();
                if ($row2 && $row2['nilai']) $siteLogo = $row2['nilai'];
            }
        } catch (\Exception $e2) { /* silent */ }
    }

    $links = [
        'index.php'       => 'Beranda',
        'artikel.php'     => 'Artikel',
        'galeri.php'      => 'Galeri',
        'tentangKami.php' => 'Tentang Kami',
    ];
?>
<header>
    <div class="container">
        <div class="navbar">

            <a href="index.php" class="logo">
                <img src="<?= htmlspecialchars($siteLogo) ?>" alt="Logo JelajahinNusa" onerror="this.src='Gambar/logo2.png'">
            </a>

            <nav class="nav-menu" id="navMenu">
                <ul>
                    <?php foreach ($links as $href => $label): ?>
                    <li>
                        <a href="<?= $href ?>"
                           <?= $activeNav === $href ? 'class="active"' : '' ?>>
                            <?= $label ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="header-right">
                <div class="search-wrapper">
                    <form class="search-bar" action="artikel.php" method="GET" id="searchForm" autocomplete="off">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" id="searchInput" placeholder="Cari disini..." autocomplete="off">
                    </form>
                    <div class="search-suggestions" id="searchSuggestions"></div>
                </div>

                <?php if ($session): ?>
                <div class="user-profile" id="upBtn">
                    <?php if ($photo): ?>
                        <img src="<?= $photo ?>"
                             style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.75);"
                             alt="Foto profil">
                    <?php else: ?>
                        <i class="far fa-user-circle" style="font-size:30px;color:#fff;"></i>
                    <?php endif; ?>
                    <div class="user-dropdown" id="userDD">
                        <a href="infoAkun.php">
                            <i class="fas fa-user" style="margin-right:8px"></i> Profil Saya
                        </a>
                        <?php if ($isAdmin): ?>
                        <a href="dashboardAdmin.php">
                            <i class="fas fa-tachometer-alt" style="margin-right:8px"></i> Dashboard Admin
                        </a>
                        <?php endif; ?>
                        <a href="logout.php" style="color:#e74c3c;">
                            <i class="fas fa-sign-out-alt" style="margin-right:8px"></i> Keluar
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="auth-buttons">
                    <a href="login.php" class="btn-masuk">Masuk</a>
                    <a href="daftar.php" class="btn-daftar">Daftar</a>
                </div>
                <?php endif; ?>

                <div class="menu-toggle-nav" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>

        </div>
    </div>
</header>

<script>
(function () {
    document.getElementById('menuToggle').addEventListener('click', function () {
        const nav = document.getElementById('navMenu');
        nav.classList.toggle('active');
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-bars');
        icon.classList.toggle('fa-times');
    });
    const upBtn = document.getElementById('upBtn');
    if (upBtn) {
        upBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            document.getElementById('userDD').classList.toggle('open');
        });
        document.addEventListener('click', function () {
            const dd = document.getElementById('userDD');
            if (dd) dd.classList.remove('open');
        });
    }

    // ── AUTOCOMPLETE ──
    const input   = document.getElementById('searchInput');
    const box     = document.getElementById('searchSuggestions');
    const form    = document.getElementById('searchForm');
    if (!input || !box) return;

    let debounceTimer = null;
    let activeIndex   = -1;
    let lastResults   = [];

    function highlight(text, q) {
        if (!q) return text;
        const escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return text.replace(new RegExp('(' + escaped + ')', 'gi'), '<mark>$1</mark>');
    }

    function renderSuggestions(results, q) {
        activeIndex = -1;
        lastResults = results;

        if (!results.length) {
            box.innerHTML = `<div class="suggestion-empty">
                <i class="fas fa-search"></i>
                Tidak ada hasil untuk "<strong style="color:#aaa">${q}</strong>"
            </div>`;
            box.classList.add('open');
            return;
        }

        const artikels = results.filter(r => r.type === 'artikel');
        const galeris  = results.filter(r => r.type === 'galeri');
        let html = '<div class="suggestion-scroll">';

        if (artikels.length) {
            html += `<div class="suggestion-group-label"><i class="fas fa-newspaper"></i> Artikel</div>`;
            html += artikels.map(r => renderItem(r, results.indexOf(r), q)).join('');
        }
        if (galeris.length) {
            html += `<div class="suggestion-group-label"><i class="fas fa-images"></i> Galeri</div>`;
            html += galeris.map(r => renderItem(r, results.indexOf(r), q)).join('');
        }

        html += '</div>';
        html += `<div class="suggestion-footer-split">
            <a href="artikel.php?search=${encodeURIComponent(q)}" class="suggestion-footer-btn">
                <i class="fas fa-newspaper"></i> Semua Artikel
            </a>
            <span class="suggestion-footer-divider"></span>
            <a href="galeri.php?search=${encodeURIComponent(q)}" class="suggestion-footer-btn">
                <i class="fas fa-images"></i> Semua Galeri
            </a>
        </div>`;
        box.innerHTML = html;
        box.classList.add('open');
    }

    function renderItem(r, i, q) {
        const isGaleri = r.type === 'galeri';
        const thumb = r.image
            ? `<img class="suggestion-thumb${isGaleri ? ' galeri-thumb' : ''}" src="${r.image}" alt="" onerror="this.style.display='none'">`
            : `<div class="suggestion-thumb-icon"><i class="fas ${isGaleri ? 'fa-image' : 'fa-mountain'}"></i></div>`;
        const loc = r.location ? r.location.split(',').pop().trim() : '';
        const meta = isGaleri
            ? (loc ? `<i class="fas fa-map-marker-alt" style="font-size:9px"></i>${loc}` : '')
            : (loc ? `<i class="fas fa-map-marker-alt" style="font-size:9px"></i>${loc}` : '');
        return `<a class="suggestion-item" href="${r.url}" data-index="${i}">
            ${thumb}
            <div class="suggestion-text">
                <div class="suggestion-title">${highlight(r.title, q)}</div>
                <div class="suggestion-meta">
                    <span class="suggestion-kat${isGaleri ? ' galeri-kat' : ''}">${r.kategori}</span>
                    ${meta}
                </div>
            </div>
            <i class="fas fa-arrow-right suggestion-arrow"></i>
        </a>`;
    }

    function closeSuggestions() {
        box.classList.remove('open');
        activeIndex = -1;
    }

    async function fetchSuggestions(q) {
        try {
            const res  = await fetch('search_api.php?q=' + encodeURIComponent(q));
            const data = await res.json();
            renderSuggestions(data, q);
        } catch(e) { closeSuggestions(); }
    }

    input.addEventListener('input', function () {
        const q = this.value.trim();
        clearTimeout(debounceTimer);
        if (q.length < 2) { closeSuggestions(); return; }
        debounceTimer = setTimeout(() => fetchSuggestions(q), 220);
    });

    // Navigasi keyboard (atas/bawah/enter/escape)
    input.addEventListener('keydown', function (e) {
        const items = box.querySelectorAll('.suggestion-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = Math.min(activeIndex + 1, items.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = Math.max(activeIndex - 1, -1);
        } else if (e.key === 'Escape') {
            closeSuggestions(); return;
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            items[activeIndex].click(); return;
        } else { return; }
        items.forEach((el, i) => el.classList.toggle('active', i === activeIndex));
        if (activeIndex >= 0 && items[activeIndex]) {
            input.value = lastResults[activeIndex]?.title ?? input.value;
        }
    });

    // Tutup saat klik di luar
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.search-wrapper')) closeSuggestions();
    });

    // Buka lagi saat fokus ke input (jika ada teks)
    input.addEventListener('focus', function () {
        if (this.value.trim().length >= 2 && lastResults.length) {
            box.classList.add('open');
        }
    });
})();
</script>
<?php
}