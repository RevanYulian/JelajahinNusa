<?php
// Shared functions for artikel pages
require_once 'config.php';
function navbar(object|array|null $session, string $activeNav = ''): string {
    $user   = is_array($session) ? $session : null;
    $name   = htmlspecialchars($user['username'] ?? '');
    $photo  = htmlspecialchars($user['photo'] ?? '');
    $isAdmin= ($user['type'] ?? '') === 'admin';
    $photoHtml = $photo
        ? "<img src=\"$photo\" style=\"width:32px;height:32px;border-radius:50%;object-fit:cover;\">"
        : '<i class="fas fa-user-circle" style="font-size:28px"></i>';

    $userHtml = $user
        ? '<div class="user-profile" id="upBtn">'.$photoHtml.'<span>'.$name.'</span>
             <div class="user-dropdown" id="userDD">
               <a href="infoAkun.php"><i class="fas fa-user" style="margin-right:8px"></i> Profil Saya</a>
               '.($isAdmin ? '<a href="dashboardAdmin.php"><i class="fas fa-tachometer-alt" style="margin-right:8px"></i> Dashboard Admin</a>' : '').'
               <a href="logout.php" style="color:#e74c3c;"><i class="fas fa-sign-out-alt" style="margin-right:8px"></i> Keluar</a>
             </div></div>'
        : '<a href="login.php" class="user-profile"><i class="fas fa-user-circle" style="font-size:28px"></i></a>';

    $links = ['index.php'=>'Beranda','artikel.php'=>'Artikel','galeri.php'=>'Galeri','tentangKami.php'=>'Tentang Kami'];
    $liHtml = '';
    foreach($links as $href=>$label){
        $cls = $activeNav===$href ? ' class="active"' : '';
        $liHtml .= "<li><a href=\"$href\"$cls>$label</a></li>";
    }
    return '<header>
  <div class="container">
    <div class="navbar">
      <a href="index.php" class="logo"><img src="Gambar/logo2.png" alt="Logo"></a>
      <nav class="nav-menu" id="navMenu"><ul>'.$liHtml.'</ul></nav>
      <div class="header-right">
        <form class="search-bar" action="artikel.php" method="GET">
          <i class="fas fa-search"></i>
          <input type="text" name="search" placeholder="Cari disini...">
        </form>
        '.$userHtml.'
        <div class="menu-toggle-nav" id="menuToggle"><i class="fas fa-bars"></i></div>
      </div>
    </div>
  </div>
</header>';
}
function reviewForm(object|array|null $session, string $artikelId): string {
    if (!$session) return '<p style="color:#aaa;text-align:center"><a href="login.php" style="color:#27AE60">Masuk</a> untuk memberikan ulasan.</p>';
    return '<form class="subscribe-form review-form" method="POST">
      <input type="hidden" name="artikel_id" value="'.htmlspecialchars($artikelId).'">
      <input type="hidden" name="form" value="ulasan">
      <input type="text" name="isi_ulasan" placeholder="Tuliskan Ulasan Anda...">
      <button type="submit" class="btn btn-primary">Kirim</button>
    </form>';
}
function handleReview(PDO $pdo, array|null $session): string {
    if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='ulasan' && $session) {
        $isi = trim($_POST['isi_ulasan']??'');
        $aid = trim($_POST['artikel_id']??'');
        if ($isi && $aid) {
            $id = bin2hex(random_bytes(16));
            $pdo->prepare("INSERT INTO ulasan(id,user_id,artikel_id,rating,isi_ulasan,status,created_at) VALUES(?,?,?,5,?,'pending',NOW())")
                ->execute([$id, $session['id'], $aid, $isi]);
            return 'Ulasan berhasil dikirim! Terima kasih.';
        }
    }
    return '';
}