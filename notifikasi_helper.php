<?php
// ============================================================
//  notifikasi_helper.php — Helper Notifikasi JelajahinNusa
//
//  Fungsi tersedia:
//    kirimBroadcastArtikel($pdo, $artikelId, $title, $location)
//      → Broadcast ke semua user yg aktifkan notif_artikel_baru
//      → Kirim email jika notif_email=1
//
//    kirimBroadcastMaintenance($pdo, $pesan)
//      → Broadcast ke semua user yg aktifkan notif_sistem
//      → Kirim email jika notif_email=1
//
//    kirimNotifFotoKontribusi($pdo, $galeriId, $status)
//      → Notif personal ke user pemilik foto (approved/rejected)
//      → Kirim email jika notif_email=1
//
//    kirimNotifikasiUser($pdo, $userId, $judul, $pesan, $tipe)
//      → Simpan notifikasi in-app ke 1 user
//      → Kirim email jika notif_email=1
//
//    kirimEmailNotifikasi($toEmail, $toName, $judul, $pesan, $tipe)
//      → Kirim email HTML via mail()
// ============================================================


// ================================================================
//  HELPER INTERNAL — ambil config situs
// ================================================================
function _notifGetSiteCfg(PDO $pdo): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    try {
        $rows = $pdo->query("SELECT kunci, nilai FROM pengaturan_situs WHERE kunci IN ('email_kontak','nama_pengirim','nama_situs')")->fetchAll(PDO::FETCH_ASSOC);
        $cfg  = array_column($rows, 'nilai', 'kunci');
    } catch (\Exception $e) {
        $cfg = [];
    }
    return $cfg;
}

// ================================================================
//  HELPER INTERNAL — pastikan tabel notifikasi ada
// ================================================================
function _notifEnsureTables(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS broadcast_notif (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        judul       VARCHAR(255) NOT NULL,
        pesan       TEXT NOT NULL,
        tipe        VARCHAR(30)  NOT NULL DEFAULT 'info',
        target      VARCHAR(30)  NOT NULL DEFAULT 'semua',
        total_kirim INT          DEFAULT 0,
        admin_id    VARCHAR(64),
        created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifikasi_user (
        id           BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id      VARCHAR(64) NOT NULL,
        broadcast_id INT         DEFAULT NULL,
        judul        VARCHAR(255) NOT NULL,
        pesan        TEXT        NOT NULL,
        tipe         VARCHAR(30) DEFAULT 'info',
        is_read      TINYINT(1)  DEFAULT 0,
        created_at   DATETIME    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_read (user_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

// ================================================================
//  FUNGSI 1 — Broadcast Artikel Baru
//  Dipanggil otomatis saat artikel status=published pertama kali
// ================================================================
function kirimBroadcastArtikel(PDO $pdo, string $artikelId, string $title, string $location = ''): void
{
    _notifEnsureTables($pdo);

    $lokInfo  = $location ? " di {$location}" : '';
    $judul    = "Artikel Baru: {$title}";
    $pesan    = "Hei! Ada artikel wisata baru yang baru saja diterbitkan: \"{$title}\"{$lokInfo}. Yuk, baca selengkapnya!";
    $tipe     = 'artikel_baru';

    // Ambil semua user yang aktifkan notif_artikel_baru
    $users = $pdo->query("
        SELECT u.id AS user_id, u.username, u.email,
               COALESCE(np.notif_artikel_baru, 1) AS notif_artikel_baru,
               COALESCE(np.notif_email,        1) AS notif_email
        FROM users u
        LEFT JOIN notifikasi_preferences np ON np.user_id = u.id
        WHERE u.type = 'user'
          AND u.is_active = 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Simpan record broadcast
    $pdo->prepare("INSERT INTO broadcast_notif (judul, pesan, tipe, target, total_kirim) VALUES (?,?,?,?,?)")
        ->execute([$judul, $pesan, $tipe, 'notif_artikel_baru', count($users)]);
    $broadcastId = (int)$pdo->lastInsertId();

    $baseUrl = _notifBaseUrl();
    $artUrl  = "{$baseUrl}/artikel_viewer.php?id={$artikelId}";

    foreach ($users as $u) {
        if (!$u['notif_artikel_baru']) continue;

        // Simpan in-app
        $pdo->prepare("INSERT INTO notifikasi_user (user_id, broadcast_id, judul, pesan, tipe) VALUES (?,?,?,?,?)")
            ->execute([$u['user_id'], $broadcastId, $judul, $pesan, $tipe]);

        // Kirim email jika notif_email aktif
        if ($u['notif_email'] && filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
            kirimEmailNotifikasi(
                pdo     : $pdo,
                toEmail : $u['email'],
                toName  : $u['username'],
                judul   : $judul,
                pesan   : $pesan,
                tipe    : $tipe,
                ctaUrl  : $artUrl,
                ctaLabel: 'Baca Artikel Sekarang'
            );
        }
    }
}


// ================================================================
//  FUNGSI 2 — Broadcast Maintenance
//  Dipanggil otomatis saat admin mengaktifkan mode_maintenance=1
// ================================================================
function kirimBroadcastMaintenance(PDO $pdo, string $pesanMaintenance = ''): void
{
    _notifEnsureTables($pdo);

    $judul = 'Website Sedang Dalam Pemeliharaan';
    $pesan = $pesanMaintenance ?: 'Website JelajahinNusa sedang dalam pemeliharaan sementara. Kami akan segera kembali. Terima kasih atas kesabarannya!';
    $tipe  = 'sistem';

    // Ambil semua user yang aktifkan notif_sistem
    $users = $pdo->query("
        SELECT u.id AS user_id, u.username, u.email,
               COALESCE(np.notif_sistem, 1) AS notif_sistem,
               COALESCE(np.notif_email,  1) AS notif_email
        FROM users u
        LEFT JOIN notifikasi_preferences np ON np.user_id = u.id
        WHERE u.type = 'user'
          AND u.is_active = 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Simpan record broadcast
    $pdo->prepare("INSERT INTO broadcast_notif (judul, pesan, tipe, target, total_kirim) VALUES (?,?,?,?,?)")
        ->execute([$judul, $pesan, $tipe, 'notif_sistem', count($users)]);
    $broadcastId = (int)$pdo->lastInsertId();

    foreach ($users as $u) {
        if (!$u['notif_sistem']) continue;

        // Simpan in-app
        $pdo->prepare("INSERT INTO notifikasi_user (user_id, broadcast_id, judul, pesan, tipe) VALUES (?,?,?,?,?)")
            ->execute([$u['user_id'], $broadcastId, $judul, $pesan, $tipe]);

        // Kirim email jika notif_email aktif
        if ($u['notif_email'] && filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
            kirimEmailNotifikasi(
                pdo     : $pdo,
                toEmail : $u['email'],
                toName  : $u['username'],
                judul   : $judul,
                pesan   : $pesan,
                tipe    : $tipe,
                ctaUrl  : null,
                ctaLabel: null
            );
        }
    }
}


// ================================================================
//  FUNGSI 3 — Notifikasi Personal: Foto Kontribusi Disetujui/Ditolak
//  Dipanggil saat admin approve/reject galeri_kontribusi
// ================================================================
function kirimNotifFotoKontribusi(PDO $pdo, string $galeriId, string $status): void
{
    _notifEnsureTables($pdo);

    // Ambil data galeri + user pemilik
    $stmt = $pdo->prepare("
        SELECT g.user_id, g.judul AS judul_foto, g.artikel_id,
               a.title AS judul_artikel,
               u.username, u.email,
               COALESCE(np.notif_balasan_ulasan, 1) AS notif_foto,
               COALESCE(np.notif_email,          1) AS notif_email
        FROM galeri g
        LEFT JOIN users   u  ON u.id  = g.user_id
        LEFT JOIN artikel a  ON a.id  = g.artikel_id
        LEFT JOIN notifikasi_preferences np ON np.user_id = g.user_id
        WHERE g.id = ?
        LIMIT 1
    ");
    $stmt->execute([$galeriId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !$row['user_id']) return; // Tidak ada user pemilik

    // Cek apakah user mau terima notif tipe ini
    if (!$row['notif_foto']) return;

    $judulFoto   = $row['judul_foto']    ?: 'foto kontribusimu';
    $judulArtikel= $row['judul_artikel'] ?: 'artikel terkait';
    $baseUrl     = _notifBaseUrl();
    $artUrl      = $row['artikel_id'] ? "{$baseUrl}/artikel_viewer.php?id={$row['artikel_id']}" : "{$baseUrl}/infoAkun.php?tab=foto";

    if ($status === 'approved') {
        $judul = '✅ Foto Kontribusimu Disetujui!';
        $pesan = "Selamat! Foto \"{$judulFoto}\" yang kamu kirimkan untuk artikel \"{$judulArtikel}\" telah disetujui oleh admin dan kini tampil di halaman artikel. Terima kasih atas kontribusimu!";
        $ctaLabel = 'Lihat Fotomu di Artikel';
    } else {
        $judul = 'ℹ️ Update Status Foto Kontribusimu';
        $pesan = "Foto \"{$judulFoto}\" yang kamu kirimkan untuk artikel \"{$judulArtikel}\" belum dapat kami tampilkan. Pastikan foto sesuai pedoman konten kami. Kamu bisa mengirim foto lain kapan saja!";
        $ctaLabel = 'Kirim Foto Lain';
        $artUrl   = "{$baseUrl}/infoAkun.php?tab=foto";
    }

    $tipe = 'balasan_ulasan'; // Kolom notif_balasan_ulasan dipakai untuk feedback personal

    // Simpan in-app
    $pdo->prepare("INSERT INTO notifikasi_user (user_id, broadcast_id, judul, pesan, tipe) VALUES (?,NULL,?,?,?)")
        ->execute([$row['user_id'], $judul, $pesan, $tipe]);

    // Kirim email jika aktif
    if ($row['notif_email'] && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
        kirimEmailNotifikasi(
            pdo     : $pdo,
            toEmail : $row['email'],
            toName  : $row['username'],
            judul   : $judul,
            pesan   : $pesan,
            tipe    : $tipe,
            ctaUrl  : $artUrl,
            ctaLabel: $ctaLabel
        );
    }
}


// ================================================================
//  FUNGSI 4 — Notifikasi Individual (untuk kebutuhan lainnya)
//  Simpan notif in-app ke 1 user, kirim email jika aktif
// ================================================================
function kirimNotifikasiUser(
    PDO    $pdo,
    string $userId,
    string $judul,
    string $pesan,
    string $tipe = 'info'
): void {
    _notifEnsureTables($pdo);

    // Cek preferensi user
    $stmt = $pdo->prepare("
        SELECT COALESCE(np.notif_email,          1) AS notif_email,
               COALESCE(np.notif_sistem,         1) AS notif_sistem,
               COALESCE(np.notif_artikel_baru,   1) AS notif_artikel_baru,
               COALESCE(np.notif_populer,        0) AS notif_populer,
               COALESCE(np.notif_balasan_ulasan, 1) AS notif_balasan_ulasan,
               u.username, u.email
        FROM users u
        LEFT JOIN notifikasi_preferences np ON np.user_id = u.id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) return;

    // Kolom preferensi sesuai tipe
    $kolom = match ($tipe) {
        'artikel_baru'   => 'notif_artikel_baru',
        'populer'        => 'notif_populer',
        'balasan_ulasan' => 'notif_balasan_ulasan',
        default          => 'notif_sistem',
    };
    if (!$u[$kolom]) return;

    // Simpan in-app
    $pdo->prepare("INSERT INTO notifikasi_user (user_id, broadcast_id, judul, pesan, tipe) VALUES (?,NULL,?,?,?)")
        ->execute([$userId, $judul, $pesan, $tipe]);

    // Email
    if ($u['notif_email'] && filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
        kirimEmailNotifikasi(
            pdo     : $pdo,
            toEmail : $u['email'],
            toName  : $u['username'],
            judul   : $judul,
            pesan   : $pesan,
            tipe    : $tipe
        );
    }
}


// ================================================================
//  HELPER — Base URL otomatis
// ================================================================
function _notifBaseUrl(): string
{
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'jelajahinnusa.id';
    return rtrim("{$scheme}://{$host}", '/');
}


// ================================================================
//  FUNGSI 5 — Kirim Email HTML
//  Gunakan mail() bawaan PHP. Ganti dengan PHPMailer/SMTP jika perlu.
// ================================================================
function kirimEmailNotifikasi(
    PDO    $pdo,
    string $toEmail,
    string $toName,
    string $judul,
    string $pesan,
    string $tipe      = 'info',
    ?string $ctaUrl   = null,
    ?string $ctaLabel = null
): bool {
    $cfg       = _notifGetSiteCfg($pdo);
    $fromEmail = $cfg['email_kontak']  ?? 'no-reply@jelajahinnusa.id';
    $fromName  = $cfg['nama_pengirim'] ?? 'JelajahinNusa';
    $namaSitus = $cfg['nama_situs']    ?? 'JelajahinNusa';
    $baseUrl   = _notifBaseUrl();

    // Label, warna, & ikon per tipe
    [$label, $warna, $ikon] = match ($tipe) {
        'artikel_baru'   => ['Artikel Baru',    '#27ae60', '📰'],
        'populer'        => ['Konten Populer',  '#e67e22', '🔥'],
        'sistem'         => ['Info Sistem',     '#2980b9', '⚙️'],
        'balasan_ulasan' => ['Update Foto',     '#8e44ad', '🖼️'],
        default          => ['Informasi',       '#1a3a2a', '📢'],
    };

    $pesanHtml = nl2br(htmlspecialchars($pesan,   ENT_QUOTES, 'UTF-8'));
    $judulHtml = htmlspecialchars($judul,          ENT_QUOTES, 'UTF-8');
    $nameHtml  = htmlspecialchars($toName,         ENT_QUOTES, 'UTF-8');
    $tahun     = date('Y');

    // CTA button (opsional)
    $ctaBlock = '';
    if ($ctaUrl && $ctaLabel) {
        $ctaUrlHtml   = htmlspecialchars($ctaUrl,   ENT_QUOTES, 'UTF-8');
        $ctaLabelHtml = htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8');
        $ctaBlock = <<<HTML
        <div style="text-align:center;margin:28px 0 8px">
          <a href="{$ctaUrlHtml}"
             style="background:linear-gradient(135deg,#2d6a4f,#40916c);color:#fff;text-decoration:none;
                    padding:13px 32px;border-radius:10px;font-size:14px;font-weight:700;letter-spacing:.3px;
                    display:inline-block">
            {$ctaLabelHtml} &rarr;
          </a>
        </div>
HTML;
    }

    $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$judulHtml}</title>
</head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:'Segoe UI',Arial,sans-serif;color:#2c3e50">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 0">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.10);max-width:600px">

      <!-- HEADER -->
      <tr><td style="background:linear-gradient(135deg,#1a3a2a 0%,#2d6a4f 100%);padding:36px 40px 28px;text-align:center">
        <div style="font-size:26px;font-weight:800;color:#fff;letter-spacing:-.5px;margin-bottom:6px">
          Jelajahin<span style="color:#a8edca">Nusa</span>
        </div>
        <div style="color:#a8edca;font-size:13px">Temukan pesona wisata alam Indonesia</div>
        <div style="display:inline-block;background:{$warna};color:#fff;font-size:12px;font-weight:600;
                    padding:4px 16px;border-radius:20px;margin-top:16px;letter-spacing:.5px">
          {$ikon} {$label}
        </div>
      </td></tr>

      <!-- BODY -->
      <tr><td style="padding:36px 40px">
        <p style="font-size:16px;font-weight:600;color:#1a3a2a;margin:0 0 8px">Halo, {$nameHtml}! 👋</p>
        <p style="font-size:14px;color:#64748b;margin:0 0 20px">Kamu mendapat notifikasi baru dari admin JelajahinNusa:</p>

        <div style="background:#f8fafb;border-left:4px solid {$warna};border-radius:8px;padding:20px 24px;margin:0 0 20px">
          <div style="font-size:17px;font-weight:700;color:#1a3a2a;margin-bottom:10px">{$judulHtml}</div>
          <div style="font-size:14px;line-height:1.8;color:#4a5568">{$pesanHtml}</div>
        </div>

        {$ctaBlock}

        <p style="font-size:12px;color:#94a3b8;text-align:center;margin:16px 0 0">
          Notifikasi ini dikirim karena kamu mengaktifkan <strong>notifikasi email</strong> di pengaturan akun.
        </p>
      </td></tr>

      <!-- FOOTER -->
      <tr><td style="background:#f8fafb;border-top:1px solid #e8ecef;padding:22px 40px;text-align:center">
        <p style="font-size:12px;color:#94a3b8;margin:4px 0">
          &copy; {$tahun} <strong>{$namaSitus}</strong> &mdash; Jelajahi Keindahan Nusantara
        </p>
        <p style="font-size:12px;color:#94a3b8;margin:4px 0">
          <a href="{$baseUrl}" style="color:#2d6a4f;text-decoration:none">Kunjungi Website</a>
          &nbsp;|&nbsp;
          <a href="{$baseUrl}/infoAkun.php?tab=notifikasi" style="color:#2d6a4f;text-decoration:none">Kelola Notifikasi</a>
        </p>
        <p style="font-size:11px;color:#b0b8c1;margin:10px 0 0">
          Untuk mematikan notifikasi email, pergi ke
          <a href="{$baseUrl}/infoAkun.php?tab=notifikasi" style="color:#b0b8c1">Pengaturan Notifikasi</a>
          dan nonaktifkan toggle <em>Notifikasi Email</em>.
        </p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;

    $subject = "=?UTF-8?B?" . base64_encode("[{$namaSitus}] {$judul}") . "?=";
    $headers = implode("\r\n", [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
        "Reply-To: {$fromEmail}",
        "X-Mailer: PHP/" . PHP_VERSION,
        "X-Priority: 3",
    ]);

    $sent = @mail($toEmail, $subject, $html, $headers);
    if (!$sent) {
        error_log("[NotifEmail] Gagal kirim ke {$toEmail} | judul: {$judul}");
    }
    return $sent;
}
