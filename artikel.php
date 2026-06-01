<?php
// ============================================================
//  artikel.php — Halaman Daftar Artikel
// ============================================================
require_once 'config.php';
require_once 'navbar.php';
require_once 'footer.php';
session_start();
$session = getSession();
$pdo     = getDB();

// ---- Parameter filter ----
$search   = trim($_GET['search']   ?? '');
$kategori = trim($_GET['kategori'] ?? '');
$wilayah  = trim($_GET['wilayah']  ?? '');
$sortir   = trim($_GET['sortir']   ?? 'terbaru');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPageOptions = [25, 50, 100, 200];
$perPage  = in_array((int)($_GET['perpage'] ?? 0), $perPageOptions) ? (int)$_GET['perpage'] : 25;

// ---- Ambil pengaturan situs untuk filter kategori ----
$cfgRows = $pdo->query("SELECT kunci, nilai FROM pengaturan_situs")->fetchAll(PDO::FETCH_KEY_PAIR);
$filterKatSlugs = array_filter(array_map('trim', explode(',', $cfgRows['artikel_filter_kategori'] ?? '')));

// ---- Kategori "utama" untuk filter Lainnya: gunakan yang dikonfigurasi admin, atau fallback hardcode ----
$kategoriUtama = !empty($filterKatSlugs) ? array_values($filterKatSlugs) : ['gunung', 'pantai-laut', 'danau', 'air-terjun'];

// ---- Ambil semua kategori untuk filter ----
$categories = $pdo->query("SELECT * FROM kategori_artikel ORDER BY nama")->fetchAll();

// ---- Bangun labelUtama dari DB jika ada pengaturan, fallback ke hardcode ----
if (!empty($filterKatSlugs)) {
    // Ambil nama kategori dari DB sesuai slug yang dikonfigurasi
    $phKat = implode(',', array_fill(0, count($filterKatSlugs), '?'));
    $stmtKat = $pdo->prepare("SELECT slug, nama FROM kategori_artikel WHERE slug IN ($phKat) ORDER BY nama");
    $stmtKat->execute($filterKatSlugs);
    $katRows = $stmtKat->fetchAll(PDO::FETCH_KEY_PAIR); // slug => nama
    // Urutkan sesuai urutan yang disimpan admin
    $labelUtama = [];
    foreach ($filterKatSlugs as $s) {
        if (isset($katRows[$s])) $labelUtama[$s] = $katRows[$s];
    }
    $labelUtama['lainnya'] = 'Lainnya';
} else {
    $labelUtama = [
        'gunung'      => 'Gunung',
        'pantai-laut' => 'Pantai & Laut',
        'danau'       => 'Danau',
        'air-terjun'  => 'Air Terjun',
        'lainnya'     => 'Lainnya',
    ];
}

// ---- Daftar wilayah dari pengaturan, fallback ke hardcode ----
$wilayahJsonCfg = $cfgRows['artikel_filter_wilayah'] ?? '';
$wilayahArr     = $wilayahJsonCfg ? json_decode($wilayahJsonCfg, true) : [];
if (!empty($wilayahArr) && is_array($wilayahArr)) {
    $wilayahList = [];
    foreach ($wilayahArr as $w) {
        if (!empty($w['slug']) && !empty($w['nama'])) {
            $wilayahList[$w['slug']] = ['nama' => $w['nama'], 'keywords' => array_map('trim', explode(',', $w['keywords'] ?? $w['nama']))];
        }
    }
} else {
    $wilayahList = [
        'jawa'              => ['nama' => 'Jawa',                 'keywords' => ['Jawa']],
        'kalimantan'        => ['nama' => 'Kalimantan',           'keywords' => ['Kalimantan']],
        'sulawesi'          => ['nama' => 'Sulawesi',             'keywords' => ['Sulawesi']],
        'sumatera'          => ['nama' => 'Sumatra',              'keywords' => ['Sumatra']],
        'maluku-papua'      => ['nama' => 'Maluku & Papua',       'keywords' => ['Maluku', 'Papua']],
        'bali-nusatenggara' => ['nama' => 'Bali & Nusa Tenggara', 'keywords' => ['Bali', 'NTT', 'NTB', 'Nusa Tenggara']],
    ];
}

// ---- Query artikel dengan filter ----
$where  = ["a.status = 'published'"];
$params = [];

if ($search) {
    $where[]  = "(a.title LIKE ? OR a.excerpt LIKE ? OR a.location LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$isLainnya = ($kategori === 'lainnya');
if ($isLainnya) {
    $placeholders = implode(',', array_fill(0, count($kategoriUtama), '?'));
    $where[]  = "k.slug NOT IN ($placeholders)";
    $params   = array_merge($params, $kategoriUtama);
} elseif ($kategori) {
    $where[]  = "k.slug = ?";
    $params[] = $kategori;
}

// Filter wilayah — gunakan keywords dari konfigurasi
if ($wilayah && isset($wilayahList[$wilayah])) {
    $keywords = $wilayahList[$wilayah]['keywords'];
    if (count($keywords) > 1) {
        $orParts = [];
        foreach ($keywords as $kw) {
            $orParts[] = "a.location LIKE ?";
            $params[]  = '%' . $kw . '%';
        }
        $where[] = '(' . implode(' OR ', $orParts) . ')';
    } else {
        $where[]  = "a.location LIKE ?";
        $params[] = '%' . ($keywords[0] ?? $wilayah) . '%';
    }
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ORDER BY berdasarkan sortir
$orderBy = match($sortir) {
    'populer' => 'a.views DESC',
    'az'      => 'a.title ASC',
    default   => 'a.created_at DESC',
};

// Total
$countSql = "SELECT COUNT(*) FROM artikel a JOIN kategori_artikel k ON a.kategori_id=k.id $whereSql";
$stmtC    = $pdo->prepare($countSql);
$stmtC->execute($params);
$total      = (int)$stmtC->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset     = ($page - 1) * $perPage;

// Data
$sql  = "SELECT a.id, a.title, a.slug, k.nama AS kategori, k.slug AS kat_slug,
                a.location, a.excerpt, a.image, a.file_html, a.views,
                DATE_FORMAT(a.created_at,'%d %b %Y') AS tanggal
         FROM artikel a
         JOIN kategori_artikel k ON a.kategori_id=k.id
         $whereSql
         ORDER BY $orderBy
         LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$artikels = $stmt->fetchAll();

// Helper: bangun query string untuk link filter (jaga parameter lain)
function buildQs(array $override = []): string {
    global $search, $kategori, $wilayah, $sortir, $perPage;
    global $page;
    $base = [
        'search'   => $search,
        'kategori' => $kategori,
        'wilayah'  => $wilayah,
        'sortir'   => $sortir !== 'terbaru' ? $sortir : '',
        'perpage'  => $perPage !== 25 ? $perPage : '',
        'page'     => $page > 1 ? $page : '',
    ];
    $merged = array_merge($base, $override);
    $filtered = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return $filtered ? '?' . http_build_query($filtered) : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artikel - JelajahinNusa</title>
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
        .btn{ display:inline-block; padding:10px 22px; border-radius:30px; font-weight:500; font-size:15px; transition:.3s; border:none; cursor:pointer; }
        .btn-primary{ background:var(--text-primary); color:#000; }
        .btn-primary:hover{ opacity:.9; }
        /* Page Header */
        .page-header{ padding:60px 0 30px; }
        .page-header h1{ font-size:40px; font-weight:700; margin-bottom:10px; }
        .page-header p{ color:var(--text-secondary); font-size:16px; }
        /* Filter section */
        .filter-section{ padding:20px 0 32px; display:flex; flex-direction:column; gap:14px; }
        .filter-row{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .filter-label{ font-size:12px; color:var(--text-secondary); font-weight:500; white-space:nowrap; min-width:64px; }
        /* Dropdown filter */
        .filter-select{ padding:7px 36px 7px 14px; border-radius:30px; border:1px solid rgba(255,255,255,.18); color:#fff; cursor:pointer; font-size:13px; font-weight:500; transition:.22s; background:#1A1A1A; font-family:'Poppins',sans-serif; outline:none; appearance:none; -webkit-appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23aaa' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; }
        .filter-select:hover{ border-color:rgba(255,255,255,.4); }
        .filter-select option{ background:#1A1A1A; color:#fff; }
        /* Sortir single toggle button */
        .sortir-toggle{ display:inline-flex; align-items:center; gap:8px; padding:7px 18px; border-radius:30px; border:1px solid rgba(255,255,255,.18); color:#fff; cursor:pointer; font-size:13px; font-weight:500; transition:.22s; background:rgba(255,255,255,.07); font-family:'Poppins',sans-serif; text-decoration:none; white-space:nowrap; }
        .sortir-toggle:hover{ background:rgba(255,255,255,.14); border-color:rgba(255,255,255,.4); }
        .sortir-toggle .sort-icon{ font-size:14px; transition:transform .2s; }
        .filter-divider{ width:1px; height:20px; background:rgba(255,255,255,.1); margin:0 4px; }

        /* Grid toolbar */
        .grid-toolbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
        .toolbar-info{ font-size:13px; color:var(--text-secondary); display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .toolbar-info strong{ color:#fff; }
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
            transition:.2s; flex-shrink:0;
        }
        .search-chip:hover i{ background:rgba(255,100,100,.3); color:#ff7070; }
        .toolbar-right{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .perpage-wrap{ display:flex; align-items:center; gap:8px; }
        .toolbar-label{ font-size:12px; color:var(--text-secondary); white-space:nowrap; }
        .toolbar-select{ padding:5px 30px 5px 12px !important; font-size:12px !important; }
        .sortir-toggle-sm{ padding:5px 14px; font-size:12px; }

        /* Article cards — compact horizontal list */
        .articles-grid{ display:flex; flex-direction:column; gap:12px; padding-bottom:60px; }
        .article-card{ background:var(--dark-bg-secondary); border-radius:12px; overflow:hidden; display:flex; flex-direction:row; align-items:stretch; transition:transform .25s,box-shadow .25s; height:110px; color:inherit; text-decoration:none; }
        .article-card:hover{ transform:translateX(4px); box-shadow:0 6px 20px rgba(0,0,0,.5); }
        .article-img-wrap{ flex:0 0 160px; overflow:hidden; height:100%; }
        .article-img{ width:160px; height:100%; object-fit:cover; transition:transform .4s; display:block; }
        .article-card:hover .article-img{ transform:scale(1.07); }
        .article-body{ padding:12px 16px; flex-grow:1; display:flex; flex-direction:column; justify-content:space-between; gap:4px; overflow:hidden; }
        .article-meta{ font-size:11px; color:var(--text-secondary); display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .article-meta .kat-badge{ background:rgba(31,69,41,.5); color:#6fcf97; padding:2px 9px; border-radius:20px; font-size:10px; font-weight:600; }
        .article-title{ font-size:14px; font-weight:600; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .article-excerpt{ font-size:12px; color:var(--text-secondary); line-height:1.5; display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden; }
        .article-footer{ display:flex; align-items:center; justify-content:space-between; }
        .article-stats{ display:flex; gap:10px; font-size:11px; color:var(--text-secondary); }
        .article-stats span{ display:flex; align-items:center; gap:4px; }
        .btn-read-arrow{ font-size:42px; font-weight:200; color:var(--text-secondary); line-height:1; transition:color .25s, transform .25s; padding:0 18px; display:flex; align-items:center; flex-shrink:0; }
        .article-card:hover .btn-read-arrow{ color:#fff; transform:translateX(4px); }
        @media(max-width:600px){ .article-card{ height:auto; flex-direction:column; } .article-img-wrap{ flex:none; width:100%; height:150px; } .article-img{ width:100%; } }
        /* Empty */
        .empty-state{ text-align:center; padding:80px 20px; color:var(--text-secondary); grid-column:1/-1; }
        .empty-state i{ font-size:48px; margin-bottom:16px; display:block; }
        /* Pagination */
        .pagination{ display:flex; justify-content:center; align-items:center; gap:8px; padding:40px 0 60px; }
        .pagination a,.pagination span{ display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; border-radius:8px; font-size:14px; font-weight:500; border:1px solid rgba(255,255,255,.15); color:var(--text-secondary); transition:.2s; }
        .pagination a:hover{ background:var(--accent-green); color:#fff; border-color:var(--accent-green); }
        .pagination span.current{ background:var(--accent-green); color:#fff; border-color:var(--accent-green); }
        .pagination .dots{ border:none; color:var(--text-secondary); cursor:default; }
    </style>
</head>
<body>

<?php renderNavbar($session, 'artikel.php'); ?>

<main>
    <div class="container">
        

        <!-- Filter & Sortir -->
        <div class="filter-section">

            <!-- Baris 1: Kategori + Wilayah + Sortir dalam satu baris -->
            <div class="filter-row">
                <span class="filter-label"><i class="fas fa-tag" style="margin-right:5px"></i>Kategori</span>
                <select class="filter-select" onchange="applyFilter('kategori', this.value)">
                    <option value="" <?= !$kategori ? 'selected' : '' ?>>Semua Kategori</option>
                    <?php foreach ($labelUtama as $slug => $label): ?>
                    <option value="<?= $slug ?>" <?= $kategori===$slug ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>

                <span class="filter-label" style="margin-left:10px"><i class="fas fa-map-marker-alt" style="margin-right:5px"></i>Wilayah</span>
                <select class="filter-select" onchange="applyFilter('wilayah', this.value)">
                    <option value="" <?= !$wilayah ? 'selected' : '' ?>>Semua Wilayah</option>
                    <?php foreach ($wilayahList as $slug => $w): ?>
                    <option value="<?= htmlspecialchars($slug) ?>" <?= $wilayah===$slug ? 'selected' : '' ?>><?= htmlspecialchars(is_array($w) ? $w['nama'] : $w) ?></option>
                    <?php endforeach; ?>
                </select>


            </div>

        </div>

        <!-- Toolbar: info kiri + sort & perpage kanan -->
        <?php
        $sortirCycleBar = ['terbaru' => 'populer', 'populer' => 'az', 'az' => 'terbaru'];
        $sortirNextBar  = $sortirCycleBar[$sortir] ?? 'populer';
        $sortirIconsBar = ['terbaru' => 'fa-clock', 'populer' => 'fa-fire', 'az' => 'fa-font'];
        $sortirLabelBar = ['terbaru' => 'Terbaru', 'populer' => 'Terpopuler', 'az' => 'A–Z'];
        ?>
        <div class="grid-toolbar">
            <p class="toolbar-info">
                Menampilkan <strong><?= number_format($total) ?></strong> artikel
                <?php if ($search): ?>
                <a href="artikel.php<?= buildQs(['search'=>'','page'=>'']) ?>" class="search-chip">
                    <?= htmlspecialchars($search) ?> <i class="fas fa-times"></i>
                </a>
                <?php endif; ?>
                <?php
                if ($isLainnya) echo ' · <strong>Lainnya</strong>';
                elseif ($kategori) { $lm=['gunung'=>'Gunung','pantai-laut'=>'Pantai & Laut','danau'=>'Danau','air-terjun'=>'Air Terjun']; echo ' · <strong>'.($lm[$kategori]??htmlspecialchars($kategori)).'</strong>'; }
                if ($wilayah && isset($wilayahList[$wilayah])) {
                    $wNama = is_array($wilayahList[$wilayah]) ? $wilayahList[$wilayah]['nama'] : $wilayahList[$wilayah];
                    echo ' · <strong>' . htmlspecialchars($wNama) . '</strong>';
                }
                ?>
            </p>
            <div class="toolbar-right">
                <div class="perpage-wrap">
                    <span class="toolbar-label">Tampilkan</span>
                    <select class="filter-select toolbar-select" onchange="applyFilter('perpage', this.value)">
                        <?php foreach ($perPageOptions as $opt): ?>
                        <option value="<?= $opt ?>" <?= $perPage===$opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="toolbar-label">per halaman</span>
                </div>
                <a href="artikel.php<?= buildQs(['sortir'=>$sortirNextBar,'page'=>'']) ?>" class="sortir-toggle sortir-toggle-sm" title="Klik untuk ganti urutan">
                    <i class="fas <?= $sortirIconsBar[$sortir] ?? 'fa-clock' ?> sort-icon"></i>
                    <span><?= $sortirLabelBar[$sortir] ?? 'Terbaru' ?></span>
                    <i class="fas fa-sync-alt" style="font-size:10px;opacity:.6;margin-left:2px"></i>
                </a>
            </div>
        </div>

        <!-- Article Grid -->
        <div class="articles-grid">
            <?php if (empty($artikels)): ?>
            <div class="empty-state">
                <i class="fas fa-newspaper"></i>
                <p>Tidak ada artikel yang ditemukan.</p>
                <a href="artikel.php" class="btn btn-primary" style="margin-top:16px;display:inline-block">Lihat Semua Artikel</a>
            </div>
            <?php else: ?>
            <?php foreach ($artikels as $art): ?>
            <a href="artikel_viewer.php?id=<?= urlencode($art['id']) ?>" class="article-card">
                <div class="article-img-wrap">
                    <img src="<?= htmlspecialchars($art['image']) ?>"
                         alt="<?= htmlspecialchars($art['title']) ?>" class="article-img">
                </div>
                <div class="article-body">
                    <div class="article-meta">
                        <span class="kat-badge"><?= htmlspecialchars($art['kategori']) ?></span>
                        <?php if (!empty($art['location'])): ?>
                        <i class="fas fa-map-marker-alt"></i>
                        <?php
                        $lokasiParts = array_unique(array_map('trim', explode(',', $art['location'])));
                        echo htmlspecialchars(end($lokasiParts));
                        ?>
                        <?php endif; ?>
                    </div>
                    <h3 class="article-title"><?= htmlspecialchars($art['title']) ?></h3>
                    <p class="article-excerpt"><?= htmlspecialchars($art['excerpt'] ?? '') ?></p>
                    <div class="article-footer">
                        <div class="article-stats">
                            <span><i class="fas fa-eye"></i> <?= number_format($art['views']) ?></span>
                            <span><i class="fas fa-calendar-alt"></i> <?= $art['tanggal'] ?></span>
                        </div>
                    </div>
                </div>
                <span class="btn-read-arrow">&#8250;</span>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="artikel.php<?= buildQs(['page'=>$page-1]) ?>"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i==1 || $i==$totalPages || abs($i-$page)<=2): ?>
                <?php if ($i==$page): ?>
                <span class="current"><?= $i ?></span>
                <?php else: ?>
                <a href="artikel.php<?= buildQs(['page'=>$i]) ?>"><?= $i ?></a>
                <?php endif; ?>
                <?php elseif (abs($i-$page)==3): ?>
                <span class="dots">...</span>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="artikel.php<?= buildQs(['page'=>$page+1]) ?>"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php renderFooter(); ?>

<script>
    // Filter dropdown handler — ubah URL dengan parameter baru
    function applyFilter(param, value) {
        const url = new URL(window.location.href);
        if (value) url.searchParams.set(param, value);
        else url.searchParams.delete(param);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }
</script>
</body>
</html>