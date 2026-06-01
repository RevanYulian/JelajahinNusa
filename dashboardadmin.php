<?php
// ============================================================
//  dashboardAdmin.php — Admin Dashboard Terpadu JelajahinNusa
//  Mode dideteksi via ?mode= parameter:
//    (default / users)  → Manajemen Pengguna
//    articles           → Manajemen Artikel
//    settings           → Pengaturan Sistem
//    reviews            → Ulasan Pengguna
//    api                → JSON API endpoint
// ============================================================
require_once 'config.php';

// ============================================================
//  PENTING: Deteksi mode API lebih awal — SEBELUM requireAdmin()
//  Supaya fetch/Ajax dari JS tidak kena redirect HTML login,
//  melainkan mendapat JSON error 401 yang bisa dibaca JS.
// ============================================================
$mode = $_GET['mode'] ?? 'users';

if ($mode === 'api') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Content-Type: application/json');

    // Cek session ada — gunakan getSession() dari config.php
    // Tidak cek field 'role' secara hardcode karena nama field bisa berbeda-beda
    $sessionData = getSession();
    if (!$sessionData) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Silakan login ulang.']);
        exit;
    }

    $pdo    = getDB();
    $action = $_GET['action'] ?? '';

    // ---- helpers ----
    if (!function_exists('jsonOk')) {
        function jsonOk(mixed $data, string $msg = 'OK'): never {
            echo json_encode(['success' => true, 'message' => $msg, 'data' => $data]);
            exit;
        }
    }
    if (!function_exists('jsonErr')) {
        function jsonErr(string $msg): never {
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
    }

    switch ($action) {

        // ---------- stats ----------
        case 'get_stats':
            try {
                $stats = $pdo->query("SELECT * FROM v_stats_dashboard")->fetch(PDO::FETCH_ASSOC);
                jsonOk([
                    'total_user'            => $stats['total_user']            ?? 0,
                    'user_daftar_hari_ini'  => $stats['user_daftar_hari_ini']  ?? 0,
                    'total_artikel_terbit'  => $stats['total_artikel_terbit']  ?? 0,
                ]);
            } catch (\Exception $e) {
                // fallback manual jika view tidak ada
                try {
                    $total   = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                    $today   = $pdo->query("SELECT COUNT(*) FROM users WHERE type='user' AND DATE(created_at)=CURDATE()")->fetchColumn();
                    $artikel = $pdo->query("SELECT COUNT(*) FROM artikel WHERE status='published'")->fetchColumn();
                    jsonOk(['total_user' => $total, 'user_daftar_hari_ini' => $today, 'total_artikel_terbit' => $artikel]);
                } catch (\Exception $e2) {
                    jsonErr('DB Error get_stats: ' . $e2->getMessage());
                }
            }
            break;

        case 'get_active_users':
            // Jumlah user yang sedang online (aktif dalam 15 menit terakhir)
            try {
                $count = 0;
                try {
                    $count = (int)$pdo->query(
                        "SELECT COUNT(DISTINCT user_id) FROM user_sessions
                         WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
                    )->fetchColumn();
                } catch (\Exception $e2) { /* tabel belum ada */ }
                jsonOk(['active_users' => $count]);
            } catch (\Exception $e) {
                jsonErr('DB Error get_active_users: ' . $e->getMessage());
            }
            break;


        case 'get_users':
            try {
                $rows = $pdo->query("
                    SELECT u.id, u.username, u.email, u.country, u.profile_photo,
                           u.created_at, u.is_active, u.type,
                           MAX(s.last_seen) AS last_active
                    FROM users u
                    LEFT JOIN user_sessions s ON s.user_id = u.id
                    GROUP BY u.id
                    ORDER BY u.type ASC, u.created_at DESC
                ")->fetchAll(PDO::FETCH_ASSOC);
                jsonOk($rows);
            } catch (\Exception $e) {
                // Fallback jika tabel user_sessions belum ada
                try {
                    $rows = $pdo->query("SELECT id, username, email, country, profile_photo, created_at, is_active, type, NULL AS last_active FROM users ORDER BY type ASC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                    jsonOk($rows);
                } catch (\Exception $e2) {
                    jsonErr('DB Error get_users: ' . $e2->getMessage());
                }
            }
            break;

        case 'ban_user':
            try {
                $id = $_POST['id'] ?? '';
                if (!$id) jsonErr('ID pengguna tidak valid.');
                $pdo->prepare("UPDATE users SET is_active=0, updated_at=NOW() WHERE id=? AND type='user'")->execute([$id]);
                jsonOk(null, 'Pengguna berhasil dibanned.');
            } catch (\Exception $e) {
                jsonErr('DB Error ban_user: ' . $e->getMessage());
            }
            break;

        case 'unban_user':
            try {
                $id = $_POST['id'] ?? '';
                if (!$id) jsonErr('ID pengguna tidak valid.');
                $pdo->prepare("UPDATE users SET is_active=1, updated_at=NOW() WHERE id=? AND type='user'")->execute([$id]);
                jsonOk(null, 'Ban pengguna berhasil dicabut.');
            } catch (\Exception $e) {
                jsonErr('DB Error unban_user: ' . $e->getMessage());
            }
            break;

        case 'get_user':
            try {
                $id   = $_GET['id'] ?? '';
                $stmt = $pdo->prepare("SELECT id, username, email, country, profile_photo FROM users WHERE id=? LIMIT 1");
                $stmt->execute([$id]);
                $u = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$u) jsonErr('Pengguna tidak ditemukan.');
                jsonOk($u);
            } catch (\Exception $e) {
                jsonErr('DB Error get_user: ' . $e->getMessage());
            }
            break;

        case 'update_user':
            try {
                $id       = $_POST['id']       ?? '';
                $username = trim($_POST['username'] ?? '');
                $email    = trim($_POST['email']    ?? '');
                $country  = trim($_POST['country']  ?? 'Indonesia');
                if (!$id || !$username || !$email) jsonErr('ID, username, dan email wajib diisi.');
                $pdo->prepare("UPDATE users SET username=?, email=?, country=?, updated_at=NOW() WHERE id=?")->execute([$username, $email, $country, $id]);
                jsonOk(null, 'Pengguna berhasil diperbarui.');
            } catch (\Exception $e) {
                jsonErr('DB Error update_user: ' . $e->getMessage());
            }
            break;

        case 'delete_user':
            try {
                $id = $_POST['id'] ?? '';
                if (!$id) jsonErr('ID pengguna tidak valid.');
                $pdo->prepare("DELETE FROM users WHERE id=? AND type='user'")->execute([$id]);
                jsonOk(null, 'Pengguna berhasil dihapus.');
            } catch (\Exception $e) {
                jsonErr('DB Error delete_user: ' . $e->getMessage());
            }
            break;

        // ---------- articles ----------
        case 'get_articles':
            try {
                $articles = $pdo->query("
                    SELECT a.id, a.title, k.nama AS category, a.location,
                           a.excerpt, a.image, a.file_html, a.status, a.views,
                           DATE_FORMAT(a.created_at,'%d %b %Y') AS date,
                           a.kategori_id
                    FROM artikel a
                    JOIN kategori_artikel k ON a.kategori_id = k.id
                    ORDER BY a.created_at DESC
                ")->fetchAll(PDO::FETCH_ASSOC);
                $categories = $pdo->query("SELECT id, nama FROM kategori_artikel ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
                // Normalisasi field agar JS konsisten
                $articles = array_map(function($a) {
                    return [
                        'id'          => $a['id'],
                        'title'       => $a['title']     ?? '',
                        'category'    => $a['category']  ?? '',
                        'location'    => $a['location']  ?? '',
                        'excerpt'     => $a['excerpt']   ?? '',
                        'image'       => $a['image']     ?? '',
                        'file_html'   => $a['file_html'] ?? '#',
                        'status'      => $a['status']    ?? 'draft',
                        'views'       => (int)($a['views'] ?? 0),
                        'date'        => $a['date']      ?? '',
                        'kategori_id' => $a['kategori_id'] ?? '',
                    ];
                }, $articles);
                echo json_encode(['success' => true, 'message' => 'OK', 'data' => $articles, 'categories' => $categories]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'message' => 'DB Error get_articles: ' . $e->getMessage()]);
            }
            exit;

        case 'get_article':
            try {
                $id   = $_GET['id'] ?? '';
                $stmt = $pdo->prepare(
                    "SELECT a.*, k.nama AS category FROM artikel a
                     JOIN kategori_artikel k ON a.kategori_id = k.id
                     WHERE a.id=? LIMIT 1"
                );
                $stmt->execute([$id]);
                $a = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$a) jsonErr('Artikel tidak ditemukan.');

                // Decode content JSON → 3 bagian
                $contentArr = $a['content'] ? json_decode($a['content'], true) : null;
                if (is_array($contentArr)) {
                    $a['content_deskripsi'] = $contentArr['deskripsi']  ?? '';
                    $a['content_jalur']     = $contentArr['jalur']      ?? '';
                    $a['content_daya_tarik']= $contentArr['daya_tarik'] ?? '';
                } else {
                    // fallback: konten lama (plain text) masuk ke deskripsi
                    $a['content_deskripsi'] = $a['content'] ?? '';
                    $a['content_jalur']     = '';
                    $a['content_daya_tarik']= '';
                }

                // Decode info_json
                $infoArr = $a['info_json'] ? json_decode($a['info_json'], true) : [];
                $a['info_json_raw'] = $infoArr
                    ? implode("\n", array_map(fn($k,$v) => "$k: $v", array_keys($infoArr), $infoArr))
                    : '';

                // Galeri: kirim ID yang sudah dipilih (dari gallery_json)
                $galArr = $a['gallery_json'] ? json_decode($a['gallery_json'], true) : [];
                $a['gallery_ids'] = array_map(fn($g) => $g['galeri_id'] ?? '', $galArr);

                jsonOk($a);
            } catch (\Exception $e) {
                jsonErr('DB Error get_article: ' . $e->getMessage());
            }
            break;

        case 'get_galeri_list':
            try {
                $rows = $pdo->query("SELECT id, image_path, judul, lokasi FROM galeri ORDER BY urutan ASC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                jsonOk($rows);
            } catch (\Exception $e) {
                jsonErr('DB Error get_galeri_list: ' . $e->getMessage());
            }
            break;

        case 'add_article':
            try {
                $session     = getSession();
                $title       = trim($_POST['title']       ?? '');
                $kategori_id = trim($_POST['kategori_id'] ?? '');
                $location    = trim($_POST['location']    ?? '');
                $image       = trim($_POST['image']       ?? 'Gambar/hero.jpg');
                $maps_embed  = trim($_POST['maps_embed']  ?? '');
                $status      = in_array($_POST['status'] ?? '', ['published','draft']) ? $_POST['status'] : 'draft';

                // Konten 3 bagian → simpan sebagai JSON
                $contentArr = [
                    'deskripsi'  => trim($_POST['content_deskripsi']  ?? ''),
                    'jalur'      => trim($_POST['content_jalur']      ?? ''),
                    'daya_tarik' => trim($_POST['content_daya_tarik'] ?? ''),
                ];
                $content = json_encode($contentArr, JSON_UNESCAPED_UNICODE);

                // info_json
                $infoRaw = trim($_POST['info_json_raw'] ?? '');
                $infoArr = [];
                foreach (explode("\n", $infoRaw) as $line) {
                    if (str_contains($line, ':')) {
                        [$k, $v] = explode(':', $line, 2);
                        $infoArr[trim($k)] = trim($v);
                    }
                }
                $info_json = $infoArr ? json_encode($infoArr, JSON_UNESCAPED_UNICODE) : null;

                // gallery_json: dari ID galeri yang dipilih → ambil image_path dari tabel galeri
                $galIds  = array_filter(array_map('trim', explode(',', $_POST['gallery_ids'] ?? '')));
                $galArr  = [];
                foreach ($galIds as $gid) {
                    $gid = trim($gid); if (!$gid) continue;
                    $gr  = $pdo->prepare("SELECT id, image_path, judul FROM galeri WHERE id=? LIMIT 1");
                    $gr->execute([$gid]);
                    $row = $gr->fetch(PDO::FETCH_ASSOC);
                    if ($row) $galArr[] = ['galeri_id' => $row['id'], 'src' => $row['image_path'], 'caption' => $row['judul'] ?? ''];
                }
                $gallery_json = $galArr ? json_encode($galArr, JSON_UNESCAPED_UNICODE) : null;

                if (!$title || !$kategori_id) jsonErr('Judul dan kategori wajib diisi.');
                $slug      = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title)) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
                $id        = bin2hex(random_bytes(16));
                $file_html = 'artikel_viewer.php?id=' . $id;
                $stmt = $pdo->prepare(
                    "INSERT INTO artikel
                     (id, title, slug, kategori_id, location, excerpt, content, image,
                      maps_embed, info_json, gallery_json, file_html, status, views, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0,NOW(),NOW())"
                );
                $stmt->execute([$id, $title, $slug, $kategori_id, $location, '', $content,
                                $image, $maps_embed, $info_json, $gallery_json, $file_html, $status]);
                jsonOk(['id' => $id], 'Artikel berhasil ditambahkan.');
            } catch (\Exception $e) {
                jsonErr('DB Error add_article: ' . $e->getMessage());
            }
            break;

        case 'update_article':
            try {
                $id          = $_POST['id']          ?? '';
                $title       = trim($_POST['title']  ?? '');
                $kategori_id = trim($_POST['kategori_id'] ?? '');
                $location    = trim($_POST['location']    ?? '');
                $image       = trim($_POST['image']       ?? 'Gambar/hero.jpg');
                $maps_embed  = trim($_POST['maps_embed']  ?? '');
                $status      = in_array($_POST['status'] ?? '', ['published','draft']) ? $_POST['status'] : 'draft';

                // Konten 3 bagian → JSON
                $contentArr = [
                    'deskripsi'  => trim($_POST['content_deskripsi']  ?? ''),
                    'jalur'      => trim($_POST['content_jalur']      ?? ''),
                    'daya_tarik' => trim($_POST['content_daya_tarik'] ?? ''),
                ];
                $content = json_encode($contentArr, JSON_UNESCAPED_UNICODE);

                // info_json
                $infoRaw = trim($_POST['info_json_raw'] ?? '');
                $infoArr = [];
                foreach (explode("\n", $infoRaw) as $line) {
                    if (str_contains($line, ':')) {
                        [$k, $v] = explode(':', $line, 2);
                        $infoArr[trim($k)] = trim($v);
                    }
                }
                $info_json = $infoArr ? json_encode($infoArr, JSON_UNESCAPED_UNICODE) : null;

                // gallery_json dari ID galeri
                $galIds  = array_filter(array_map('trim', explode(',', $_POST['gallery_ids'] ?? '')));
                $galArr  = [];
                foreach ($galIds as $gid) {
                    $gid = trim($gid); if (!$gid) continue;
                    $gr  = $pdo->prepare("SELECT id, image_path, judul FROM galeri WHERE id=? LIMIT 1");
                    $gr->execute([$gid]);
                    $row = $gr->fetch(PDO::FETCH_ASSOC);
                    if ($row) $galArr[] = ['galeri_id' => $row['id'], 'src' => $row['image_path'], 'caption' => $row['judul'] ?? ''];
                }
                $gallery_json = $galArr ? json_encode($galArr, JSON_UNESCAPED_UNICODE) : null;

                if (!$id || !$title || !$kategori_id) jsonErr('ID, judul, dan kategori wajib diisi.');
                $pdo->prepare(
                    "UPDATE artikel
                     SET title=?, kategori_id=?, location=?, excerpt=?, content=?,
                         image=?, maps_embed=?, info_json=?, gallery_json=?, status=?, updated_at=NOW()
                     WHERE id=?"
                )->execute([$title, $kategori_id, $location, '', $content,
                            $image, $maps_embed, $info_json, $gallery_json, $status, $id]);
                jsonOk(null, 'Artikel berhasil diperbarui.');
            } catch (\Exception $e) {
                jsonErr('DB Error update_article: ' . $e->getMessage());
            }
            break;

        case 'delete_article':
            try {
                $id = $_POST['id'] ?? '';
                if (!$id) jsonErr('ID artikel tidak valid.');
                $pdo->prepare("DELETE FROM artikel WHERE id=?")->execute([$id]);
                jsonOk(null, 'Artikel berhasil dihapus.');
            } catch (\Exception $e) {
                jsonErr('DB Error delete_article: ' . $e->getMessage());
            }
            break;

        case 'delete_usul':
            try {
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) jsonErr('ID tidak valid.');
                $pdo->prepare("DELETE FROM usul_artikel WHERE id=?")->execute([$id]);
                jsonOk(null, 'Usulan berhasil dihapus.');
            } catch (\Exception $e) {
                jsonErr('DB Error delete_usul: ' . $e->getMessage());
            }
            break;

        // ---------- broadcast notifikasi ----------
        case 'send_broadcast':
            try {
                $judul  = trim($_POST['judul']  ?? '');
                $pesan  = trim($_POST['pesan']  ?? '');
                $tipe   = in_array($_POST['tipe']   ?? '', ['info','artikel_baru','sistem','populer']) ? $_POST['tipe']   : 'info';
                $target = in_array($_POST['target'] ?? '', ['semua','notif_sistem','notif_artikel_baru']) ? $_POST['target'] : 'semua';
                if (!$judul || !$pesan) jsonErr('Judul dan pesan wajib diisi.');

                // Pastikan tabel ada
                $pdo->exec("CREATE TABLE IF NOT EXISTS broadcast_notif (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    judul VARCHAR(255) NOT NULL, pesan TEXT NOT NULL,
                    tipe VARCHAR(30) NOT NULL DEFAULT 'info',
                    target VARCHAR(30) NOT NULL DEFAULT 'semua',
                    total_kirim INT DEFAULT 0, admin_id VARCHAR(64),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $pdo->exec("CREATE TABLE IF NOT EXISTS notifikasi_user (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(64) NOT NULL, broadcast_id INT DEFAULT NULL,
                    judul VARCHAR(255) NOT NULL, pesan TEXT NOT NULL,
                    tipe VARCHAR(30) DEFAULT 'info', is_read TINYINT(1) DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_read (user_id, is_read)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // Ambil user sesuai target
                if ($target === 'semua') {
                    $users = $pdo->query("SELECT u.id FROM users u WHERE u.type='user' AND u.is_active=1")->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($target === 'notif_sistem') {
                    $users = $pdo->query("SELECT u.id FROM users u JOIN notifikasi_preferences np ON np.user_id=u.id WHERE u.type='user' AND u.is_active=1 AND np.notif_sistem=1")->fetchAll(PDO::FETCH_COLUMN);
                } else { // notif_artikel_baru
                    $users = $pdo->query("SELECT u.id FROM users u JOIN notifikasi_preferences np ON np.user_id=u.id WHERE u.type='user' AND u.is_active=1 AND np.notif_artikel_baru=1")->fetchAll(PDO::FETCH_COLUMN);
                }

                // Simpan broadcast record
                $pdo->prepare("INSERT INTO broadcast_notif (judul,pesan,tipe,target,total_kirim,admin_id) VALUES (?,?,?,?,?,?)")
                    ->execute([$judul, $pesan, $tipe, $target, count($users), $sessionData['id'] ?? null]);
                $broadcastId = $pdo->lastInsertId();

                // Insert notif per user (batch)
                if ($users) {
                    $placeholders = implode(',', array_fill(0, count($users), '(?,?,?,?,?)'));
                    $vals = [];
                    foreach ($users as $uid) {
                        array_push($vals, $uid, $broadcastId, $judul, $pesan, $tipe);
                    }
                    $pdo->prepare("INSERT INTO notifikasi_user (user_id,broadcast_id,judul,pesan,tipe) VALUES $placeholders")->execute($vals);
                }

                jsonOk(['total' => count($users)], 'Notifikasi berhasil dikirim ke ' . count($users) . ' pengguna.');
            } catch (\Exception $e) {
                jsonErr('DB Error send_broadcast: ' . $e->getMessage());
            }
            break;

        case 'get_broadcasts':
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS broadcast_notif (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    judul VARCHAR(255) NOT NULL, pesan TEXT NOT NULL,
                    tipe VARCHAR(30) NOT NULL DEFAULT 'info',
                    target VARCHAR(30) NOT NULL DEFAULT 'semua',
                    total_kirim INT DEFAULT 0, admin_id VARCHAR(64),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $rows = $pdo->query("SELECT b.*, u.username AS admin_name,
                    DATE_FORMAT(b.created_at,'%d %b %Y %H:%i') AS tanggal
                    FROM broadcast_notif b LEFT JOIN users u ON u.id=b.admin_id
                    ORDER BY b.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
                jsonOk($rows);
            } catch (\Exception $e) {
                jsonErr('DB Error get_broadcasts: ' . $e->getMessage());
            }
            break;

        case 'delete_broadcast':
            try {
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) jsonErr('ID tidak valid.');
                $pdo->prepare("DELETE FROM notifikasi_user WHERE broadcast_id=?")->execute([$id]);
                $pdo->prepare("DELETE FROM broadcast_notif WHERE id=?")->execute([$id]);
                jsonOk(null, 'Broadcast berhasil dihapus.');
            } catch (\Exception $e) {
                jsonErr('DB Error delete_broadcast: ' . $e->getMessage());
            }
            break;

        // ---- DEBUG (hapus setelah masalah teratasi) ----
        case 'debug':
            echo json_encode([
                'success'      => true,
                'session_data' => $sessionData,
                'php_version'  => PHP_VERSION,
                'mode'         => $mode,
                'action'       => $action,
                'get_params'   => $_GET,
            ]);
            exit;

        case 'debug_db':
            try {
                $result = [];

                // Semua tabel
                $result['all_tables'] = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

                // Jumlah baris tiap tabel penting
                foreach (['users','artikel','ulasan','kategori_artikel','pengaturan_situs'] as $tbl) {
                    try {
                        $result['counts'][$tbl] = (int)$pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
                    } catch (\Exception $e) {
                        $result['counts'][$tbl] = 'ERR: '.$e->getMessage();
                    }
                }

                // Kolom tiap tabel penting
                foreach (['artikel','ulasan','kategori_artikel','users'] as $tbl) {
                    try {
                        $cols = $pdo->query("DESCRIBE `$tbl`")->fetchAll(PDO::FETCH_ASSOC);
                        $result['columns'][$tbl] = array_column($cols, 'Field');
                    } catch (\Exception $e) {
                        $result['columns'][$tbl] = 'ERR: '.$e->getMessage();
                    }
                }

                // Sample 1 baris tiap tabel
                foreach (['artikel','ulasan'] as $tbl) {
                    try {
                        $result['sample'][$tbl] = $pdo->query("SELECT * FROM `$tbl` LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    } catch (\Exception $e) {
                        $result['sample'][$tbl] = 'ERR: '.$e->getMessage();
                    }
                }

                $result['success'] = true;
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } catch (\Exception $e) {
                echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
            }
            exit;

        // ---------- galeri kontribusi ----------
        case 'get_galeri_kontribusi':
            try {
                $rows = $pdo->query("
                    SELECT g.id, g.image_path, g.judul, g.lokasi, g.caption,
                           g.status, g.artikel_id,
                           g.created_at,
                           DATE_FORMAT(g.created_at, '%d %b %Y') AS tanggal,
                           u.username,
                           a.title AS judul_artikel
                    FROM galeri g
                    LEFT JOIN users   u ON u.id = g.user_id
                    LEFT JOIN artikel a ON a.id = g.artikel_id
                    WHERE g.user_id IS NOT NULL
                      AND (a.image IS NULL OR g.image_path != a.image)
                    ORDER BY g.created_at DESC
                ")->fetchAll(PDO::FETCH_ASSOC);
                jsonOk($rows);
            } catch (\Exception $e) {
                jsonErr('DB Error get_galeri_kontribusi: ' . $e->getMessage());
            }
            break;

        case 'update_galeri_kontribusi_status':
            try {
                $id     = $_POST['id']     ?? '';
                $status = $_POST['status'] ?? '';
                if (!$id) jsonErr('ID tidak valid.');
                if (!in_array($status, ['approved','rejected','pending'])) jsonErr('Status tidak valid.');
                $pdo->prepare("UPDATE galeri SET status=? WHERE id=?")->execute([$status, $id]);
                // Rebuild gallery_json artikel terkait
                $artId = $pdo->prepare("SELECT artikel_id FROM galeri WHERE id=? LIMIT 1");
                $artId->execute([$id]); $artId = $artId->fetchColumn();
                if ($artId) {
                    $approved = $pdo->prepare("SELECT id, image_path, judul FROM galeri WHERE artikel_id=? AND status='approved' AND user_id IS NOT NULL ORDER BY created_at ASC");
                    $approved->execute([$artId]);
                    $galArr = array_map(fn($r) => ['galeri_id'=>$r['id'],'src'=>$r['image_path'],'caption'=>$r['judul']??''], $approved->fetchAll(PDO::FETCH_ASSOC));
                    $pdo->prepare("UPDATE artikel SET gallery_json=?, updated_at=NOW() WHERE id=?")->execute([$galArr ? json_encode($galArr, JSON_UNESCAPED_UNICODE) : null, $artId]);
                }
                jsonOk(null, 'Status berhasil diperbarui.');
            } catch (\Exception $e) { jsonErr('DB Error: ' . $e->getMessage()); }
            break;

        case 'delete_galeri_kontribusi':
            try {
                $id = $_POST['id'] ?? '';
                if (!$id) jsonErr('ID tidak valid.');
                // Ambil info sebelum hapus untuk update gallery_json
                $row = $pdo->prepare("SELECT artikel_id, image_path FROM galeri WHERE id=? LIMIT 1");
                $row->execute([$id]); $row = $row->fetch(PDO::FETCH_ASSOC);
                // Hapus file fisik
                if ($row && $row['image_path'] && file_exists($row['image_path'])) {
                    @unlink($row['image_path']);
                }
                $pdo->prepare("DELETE FROM galeri WHERE id=?")->execute([$id]);
                // Rebuild gallery_json artikel terkait
                if ($row && $row['artikel_id']) {
                    $approved = $pdo->prepare("SELECT id, image_path, judul FROM galeri WHERE artikel_id=? AND status='approved' AND user_id IS NOT NULL ORDER BY created_at ASC");
                    $approved->execute([$row['artikel_id']]);
                    $galArr = array_map(fn($r) => ['galeri_id'=>$r['id'],'src'=>$r['image_path'],'caption'=>$r['judul']??''], $approved->fetchAll(PDO::FETCH_ASSOC));
                    $pdo->prepare("UPDATE artikel SET gallery_json=?, updated_at=NOW() WHERE id=?")->execute([$galArr ? json_encode($galArr, JSON_UNESCAPED_UNICODE) : null, $row['artikel_id']]);
                }
                jsonOk(null, 'Foto berhasil dihapus.');
            } catch (\Exception $e) { jsonErr('DB Error: ' . $e->getMessage()); }
            break;

        // ---------- kelola galeri artikel ----------
        case 'get_approved_galeri_by_artikel':
            try {
                $artId = $_GET['artikel_id'] ?? '';
                if (!$artId) jsonErr('artikel_id wajib diisi.');
                $rows = $pdo->prepare("
                    SELECT g.id, g.image_path, g.judul, g.lokasi
                    FROM galeri g
                    LEFT JOIN artikel a ON a.id = g.artikel_id
                    WHERE g.artikel_id = ?
                      AND g.status = 'approved'
                      AND g.user_id IS NOT NULL
                      AND (a.image IS NULL OR g.image_path != a.image)
                    ORDER BY g.created_at ASC
                ");
                $rows->execute([$artId]);
                $photos = $rows->fetchAll(PDO::FETCH_ASSOC);
                $art = $pdo->prepare("SELECT gallery_json, title FROM artikel WHERE id=? LIMIT 1");
                $art->execute([$artId]);
                $artRow = $art->fetch(PDO::FETCH_ASSOC);
                $selected = [];
                if ($artRow && $artRow['gallery_json']) {
                    $gj = json_decode($artRow['gallery_json'], true);
                    if (is_array($gj)) $selected = array_column($gj, 'galeri_id');
                }
                jsonOk(['photos' => $photos, 'selected' => $selected, 'title' => $artRow['title'] ?? '']);
            } catch (\Exception $e) {
                jsonErr('DB Error get_approved_galeri_by_artikel: ' . $e->getMessage());
            }
            break;

        case 'update_artikel_gallery':
            try {
                $artId  = $_POST['artikel_id'] ?? '';
                $rawIds = $_POST['galeri_ids']  ?? '';
                if (!$artId) jsonErr('artikel_id wajib diisi.');
                $galIds = array_filter(array_map('trim', explode(',', $rawIds)));
                $galArr = [];
                foreach ($galIds as $gid) {
                    $gr = $pdo->prepare("SELECT id, image_path, judul FROM galeri WHERE id=? LIMIT 1");
                    $gr->execute([$gid]);
                    $row = $gr->fetch(PDO::FETCH_ASSOC);
                    if ($row) $galArr[] = ['galeri_id' => $row['id'], 'src' => $row['image_path'], 'caption' => $row['judul'] ?? ''];
                }
                $gallery_json = $galArr ? json_encode($galArr, JSON_UNESCAPED_UNICODE) : null;
                $pdo->prepare("UPDATE artikel SET gallery_json=?, updated_at=NOW() WHERE id=?")->execute([$gallery_json, $artId]);
                jsonOk(null, 'Galeri artikel berhasil diperbarui.');
            } catch (\Exception $e) {
                jsonErr('DB Error update_artikel_gallery: ' . $e->getMessage());
            }
            break;

        case 'upload_logo':
            try {
                if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                    jsonErr('File logo tidak ditemukan atau terjadi error upload.');
                }
                $file     = $_FILES['logo'];
                $allowed  = ['image/png','image/jpeg','image/gif','image/svg+xml','image/webp'];
                $mime     = mime_content_type($file['tmp_name']);
                if (!in_array($mime, $allowed)) jsonErr('Format file tidak didukung. Gunakan PNG, JPG, SVG, atau WebP.');
                if ($file['size'] > 2 * 1024 * 1024) jsonErr('Ukuran file maksimal 2 MB.');

                $ext     = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
                $newName = 'logo_site_' . time() . '.' . strtolower($ext);
                $dir     = 'Gambar/';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $dest    = $dir . $newName;

                // Hapus logo lama jika ada dan bukan default
                $oldLogo = '';
                try {
                    $s = $pdo->prepare("SELECT nilai FROM pengaturan_situs WHERE kunci='site_logo' LIMIT 1");
                    $s->execute();
                    $oldLogo = $s->fetchColumn() ?: '';
                } catch(\Exception $e2) {}
                if ($oldLogo && $oldLogo !== 'Gambar/logo2.png' && file_exists($oldLogo)) {
                    @unlink($oldLogo);
                }

                if (!move_uploaded_file($file['tmp_name'], $dest)) jsonErr('Gagal menyimpan file logo.');

                $pdo->prepare("INSERT INTO pengaturan_situs (kunci, nilai) VALUES ('site_logo',?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)")
                    ->execute([$dest]);

                jsonOk(['path' => $dest], 'Logo berhasil diperbarui.');
            } catch (\Exception $e) {
                jsonErr('DB Error upload_logo: ' . $e->getMessage());
            }
            break;

        case 'get_settings':
            try {
                $rows = $pdo->query("SELECT kunci, nilai FROM pengaturan_situs")->fetchAll(PDO::FETCH_ASSOC);
                $cfg2 = [];
                foreach ($rows as $r) $cfg2[$r['kunci']] = $r['nilai'];
                jsonOk($cfg2);
            } catch (\Exception $e) {
                jsonErr('DB Error get_settings: ' . $e->getMessage());
            }
            break;

        default:
            jsonErr('Action tidak dikenal: ' . htmlspecialchars($action));
    }
    exit; // fallback safety
}


// ============================================================
//  Mode non-API: session & autentikasi admin normal
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
requireAdmin();
$session = getSession();
$pdo     = getDB(); // inisialisasi global untuk semua mode

// ============================================================
//  MODE: SETTINGS — proses POST sebelum render
// ============================================================
$toast     = '';
$toastType = 'success';
$cfg       = [];


// ── Hapus usul user — ditangani via AJAX (delete_usul) ──

if ($mode === 'settings') {
    $pdo = getDB();
    // Ambil semua kategori dari DB untuk picker filter artikel
    $allKategoriForFilter = $pdo->query("SELECT id, slug, nama FROM kategori_artikel ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form = $_POST['form'] ?? '';

        $formMap = [
            'umum'     => ['nama_situs', 'deskripsi_situs'],
            'email'    => ['email_kontak', 'nama_pengirim'],
            'media'    => ['max_upload_size_mb', 'allowed_file_types', 'thumb_width', 'thumb_height'],
            'keamanan' => ['maintenance_message', 'cache_durasi_menit'],
        ];

        if ($form === 'umum') {
            foreach (['nama_situs', 'deskripsi_situs'] as $k) {
                $pdo->prepare("INSERT INTO pengaturan_situs (kunci, nilai) VALUES (?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)")
                    ->execute([$k, trim($_POST[$k] ?? '')]);
            }
            $toast = 'Pengaturan Tampilan & Umum berhasil disimpan.';
        } elseif ($form === 'email') {
            foreach (['email_kontak', 'nama_pengirim'] as $k) {
                $pdo->prepare("INSERT INTO pengaturan_situs (kunci, nilai) VALUES (?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)")
                    ->execute([$k, trim($_POST[$k] ?? '')]);
            }
            $toast = 'Pengaturan Email & Notifikasi berhasil disimpan.';
        } elseif ($form === 'media') {
            $data = [
                'max_upload_size_mb' => (int)($_POST['max_upload_size_mb'] ?? 5),
                'allowed_file_types' => trim($_POST['allowed_file_types'] ?? 'jpg, jpeg, png, webp'),
                'thumb_width'        => (int)($_POST['thumb_width'] ?? 150),
                'thumb_height'       => (int)($_POST['thumb_height'] ?? 150),
            ];
            foreach ($data as $k => $v) {
                $pdo->prepare("INSERT INTO pengaturan_situs (kunci, nilai) VALUES (?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)")
                    ->execute([$k, $v]);
            }
            $toast = 'Pengaturan Media & Upload berhasil disimpan.';
        } elseif ($form === 'landing') {
            // Kategori filter artikel — simpan sebagai comma-separated slugs
            $filterSlugs = array_filter(array_map('trim', $_POST['filter_kategori_slugs'] ?? []));
            $filterVal   = implode(',', $filterSlugs);
            $pdo->prepare("INSERT INTO pengaturan_situs (kunci,nilai) VALUES ('artikel_filter_kategori',?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)")->execute([$filterVal]);
            $toast = 'Pengaturan filter kategori artikel berhasil disimpan.';
        } elseif ($form === 'wilayah') {
            $wilayahJson = $_POST['wilayah_json'] ?? '[]';
            json_decode($wilayahJson);
            if (json_last_error() === JSON_ERROR_NONE) {
                $pdo->prepare("INSERT INTO pengaturan_situs (kunci,nilai) VALUES ('artikel_filter_wilayah',?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)")->execute([$wilayahJson]);
            }
            $toast = 'Pengaturan filter wilayah artikel berhasil disimpan.';
        } elseif ($form === 'keamanan') {
            $data = [
                'mode_maintenance'    => isset($_POST['mode_maintenance'])    ? '1' : '0',
                'maintenance_message' => trim($_POST['maintenance_message']   ?? ''),
                'cache_aktif'         => isset($_POST['cache_aktif'])         ? '1' : '0',
                'cache_durasi_menit'  => (int)($_POST['cache_durasi_menit']   ?? 60),
            ];
            foreach ($data as $k => $v) {
                $pdo->prepare("INSERT INTO pengaturan_situs (kunci, nilai) VALUES (?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)")
                    ->execute([$k, $v]);
            }
            $toast = isset($_POST['clear_cache'])
                ? 'Cache berhasil dibersihkan & pengaturan disimpan.'
                : 'Pengaturan Keamanan & Performa berhasil disimpan.';
        }
    }

    $rows = $pdo->query("SELECT kunci, nilai FROM pengaturan_situs")->fetchAll();
    foreach ($rows as $r) $cfg[$r['kunci']] = $r['nilai'];
}

if (!function_exists('cfgVal')) {
    function cfgVal(array $cfg, string $key, string $default = ''): string {
        return htmlspecialchars($cfg[$key] ?? $default);
    }
}

if (!function_exists('navActive')) {
    function navActive(string $target, string $current): string {
        return $target === $current ? 'class="active"' : '';
    }
}

if (!function_exists('adminUrl')) {
    function adminUrl(string $mode): string {
        return 'dashboardAdmin.php?mode=' . $mode;
    }
}

// ============================================================
//  Mulai render HTML
// ============================================================

// Judul halaman per mode
$titles = [
    'users'    => 'Manajemen Pengguna',
    'articles' => 'Manajemen Artikel',
    'settings' => 'Pengaturan',
];
$pageTitle = $titles[$mode] ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Admin JelajahinNusa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ========== BASE ========== */
        :root {
            --dark-bg: #000;
            --dark-bg-secondary: #1A1A1A;
            --text-primary: #FFF;
            --text-secondary: #AAA;
            --accent-green: #1F4529;
            --sidebar-width: 250px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Poppins',Arial,sans-serif; background:var(--dark-bg); color:var(--text-primary); overflow-x:hidden; zoom:90%; }
        a { text-decoration:none; color:inherit; }

        /* ========== LAYOUT ========== */
        .account-page { display:flex; min-height:100vh; }

        /* ========== SIDEBAR ========== */
        .sidebar { width:var(--sidebar-width); background:var(--dark-bg); display:flex; flex-direction:column; justify-content:space-between; padding:30px 0; position:fixed; top:0; left:0; height:100vh; border-right:1px solid #1A1A1A; }
        .sidebar-top { padding:0 20px; }
        .sidebar .logo { font-size:20px; font-weight:600; margin-bottom:50px; display:flex; align-items:center; gap:10px; }
        .sidebar h4 { color:var(--text-secondary); font-size:14px; font-weight:500; padding:10px 0; margin-top:10px; }
        .nav-menu-sidebar a { display:flex; align-items:center; padding:15px 20px; margin:5px 0; color:var(--text-secondary); transition:.3s; border-radius:8px; }
        .nav-menu-sidebar a:hover { color:var(--text-primary); }
        .nav-menu-sidebar a.active { background:var(--dark-bg-secondary); color:var(--text-primary); font-weight:500; }
        .nav-menu-sidebar i { margin-right:15px; width:20px; text-align:center; }
        .sidebar-bottom { padding:0 20px; }
        .logout-link { display:flex; align-items:center; padding:15px 20px; color:red; border-radius:8px; font-size:16px; font-weight:500; cursor:pointer; }
        .logout-link:hover { background:var(--dark-bg-secondary); }
        .logout-link i { margin-right:15px; }

        /* ========== MAIN ========== */
        .main-content { margin-left:var(--sidebar-width); flex-grow:1; position:relative; }
        .bg-img { position:fixed; top:0; left:var(--sidebar-width); right:0; bottom:0; z-index:0; background:url('Gambar/jawa.jpg') center/cover; filter:brightness(0.4) grayscale(0.2); }
        .content-wrapper { position:relative; z-index:1; padding:0 40px 60px; }
        .main-header { display:flex; justify-content:space-between; align-items:center; padding:20px 0; margin-bottom:30px; }
        .search-bar-main { display:flex; align-items:center; background:rgba(255,255,255,.1); border-radius:30px; padding:10px 20px; max-width:400px; width:100%; }
        .search-bar-main input { background:transparent; border:none; outline:none; color:#fff; margin-left:10px; font-family:'Poppins',sans-serif; flex-grow:1; }
        .admin-profile { display:flex; align-items:center; gap:15px; }
        .admin-profile-pic { width:40px; height:40px; border-radius:50%; background:#555; display:flex; justify-content:center; align-items:center; }

        /* ========== SECTION CARD (generic) ========== */
        .section-card { background:rgba(0,0,0,.75); border-radius:15px; padding:30px; box-shadow:0 4px 20px rgba(0,0,0,.5); margin-bottom:50px; }
        .section-card h3 { font-size:22px; font-weight:600; margin-bottom:20px; }
        .section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .section-header h3 { margin-bottom:0; }

        /* ========== STATS ROW ========== */
        .stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:30px; }
        .stats-row.cols4 { grid-template-columns:repeat(4,1fr); }
        .stat-box { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:18px; text-align:center; cursor:pointer; transition:.2s; }
        .stat-box:hover { background:rgba(255,255,255,.1); }
        .stat-box.active { border-color:var(--accent-green); background:rgba(31,69,41,.25); }
        .stat-box .num { font-size:28px; font-weight:700; }
        .stat-box .label { font-size:12px; color:var(--text-secondary); margin-top:4px; }
        .stat-box .num.pending-num  { color:#F39C12; }
        .stat-box .num.approved-num { color:#27AE60; }
        .stat-box .num.rejected-num { color:#E74C3C; }

        /* ========== TABLE ========== */
        table { width:100%; border-collapse:collapse; }
        thead tr { background:var(--text-primary); color:var(--accent-green); font-size:14px; text-align:left; }
        th,td { padding:14px 15px; vertical-align:middle; }
        tbody tr { border-bottom:1px solid rgba(255,255,255,.08); }
        tbody tr:nth-child(even) { background:rgba(255,255,255,.03); }
        .user-avatar { width:32px; height:32px; border-radius:50%; display:inline-flex; justify-content:center; align-items:center; font-size:13px; font-weight:600; color:#fff; }
        .user-avatar-img { width:32px; height:32px; border-radius:50%; object-fit:cover; }
        .action-btns { display:flex; gap:12px; font-size:18px; justify-content:center; }
        .action-btns i { cursor:pointer; transition:.2s; color:#aaa; }
        .action-btns .fa-pen-to-square:hover { color:#fff; }
        .action-btns .fa-circle-xmark:hover,
        .action-btns .fa-trash-alt:hover { color:#fff; }
        .action-btns .fa-check:hover { color:#fff; }
        .action-btns .fa-xmark:hover { color:#fff; }
        .action-btns .fa-eye:hover { color:#fff; }
        .action-btns .fa-ban:hover { color:#fff; }
        .action-btns .fa-rotate-left:hover { color:#fff; }
        .badge { display:inline-block; padding:4px 12px; border-radius:5px; font-size:12px; font-weight:600; }
        .badge-published { background:#27AE60; color:#fff; }
        .badge-draft { background:#F39C12; color:#fff; }
        .badge-role-user  { background:#2c6e49; color:#fff; }
        .badge-role-admin { background:#5a3e7a; color:#fff; }
        .badge-role-banned { background:#7b2a2a; color:#fff; }

        /* ========== PAGINATION ========== */
        .pagination { display:flex; justify-content:flex-end; align-items:center; color:var(--text-secondary); font-size:14px; margin-top:20px; gap:10px; }
        .pagination button { background:none; border:none; color:var(--text-secondary); cursor:pointer; font-size:18px; }
        .pagination button:hover { color:#fff; }

        /* ========== SEARCH / FILTER BAR ========== */
        .search-filter { display:flex; gap:12px; margin-bottom:20px; align-items:center; }
        .search-filter input,
        .search-filter select { background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15); border-radius:8px; padding:9px 14px; color:#fff; font-family:'Poppins',sans-serif; outline:none; }
        .search-filter input { width:260px; }

        /* ========== BUTTONS ========== */
        .btn-add { background:var(--accent-green); color:#fff; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:8px; }
        .btn-add:hover { background:#2e623b; }
        .btn-save { background:var(--accent-green); color:#fff; border:none; padding:10px 24px; border-radius:8px; cursor:pointer; font-weight:600; font-family:'Poppins',sans-serif; transition:.3s; }
        .btn-save:hover { background:#2e623b; }
        .btn-cancel { background:transparent; color:#aaa; border:1px solid #555; padding:10px 24px; border-radius:8px; cursor:pointer; }
        .btn-danger { background:#C0392B; color:#fff; border:none; padding:11px 20px; border-radius:8px; cursor:pointer; font-weight:500; font-family:'Poppins',sans-serif; transition:.3s; }
        .btn-danger:hover { background:#a93226; }

        /* ========== MODAL ========== */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:9999; align-items:center; justify-content:center; }
        .modal-overlay.active { display:flex; }
        .modal-box { background:#1A1A1A; border-radius:15px; padding:30px; width:460px; max-width:95vw; max-height:90vh; overflow-y:auto; }
        .modal-box h4 { font-size:18px; font-weight:600; margin-bottom:20px; }
        .form-group { margin-bottom:14px; }
        .form-group label { display:block; font-size:13px; color:#aaa; margin-bottom:6px; }
        .form-group input,
        .form-group textarea,
        .form-group select { width:100%; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15); border-radius:8px; padding:10px 14px; color:#fff; font-family:'Poppins',sans-serif; outline:none; }
        .form-group textarea { min-height:80px; resize:vertical; }
        .form-group select option, .search-filter select option { background:#1A1A1A; color:#fff; }
        .modal-btns { display:flex; gap:12px; justify-content:flex-end; margin-top:12px; }

        /* ========== SETTINGS (mode=settings) ========== */
        .admin-title { font-size:28px; font-weight:600; margin-bottom:30px; }
        .settings-container { display:grid; grid-template-columns:1fr 1fr; gap:30px; }
        .settings-section { background:rgba(0,0,0,.75); border-radius:15px; padding:30px; box-shadow:0 4px 20px rgba(0,0,0,.5); }
        .settings-section h3 { font-size:20px; font-weight:600; margin-bottom:25px; border-bottom:1px solid rgba(255,255,255,.1); padding-bottom:15px; }
        .settings-section .form-group input[type="text"],
        .settings-section .form-group input[type="email"],
        .settings-section .form-group input[type="number"],
        .settings-section .form-group select { background:var(--dark-bg-secondary); border:1px solid rgba(255,255,255,.1); padding:12px; font-size:15px; transition:border-color .3s; }
        .settings-section .form-group input:focus { border-color:var(--accent-green); }
        .form-group.switch-group { display:flex; justify-content:space-between; align-items:center; }
        .form-group.switch-group > label { margin-bottom:0; color:var(--text-primary); font-weight:500; font-size:15px; }
        .switch { position:relative; display:inline-block; width:50px; height:24px; flex-shrink:0; }
        .switch input { opacity:0; width:0; height:0; }
        .slider { position:absolute; cursor:pointer; inset:0; background:#ccc; transition:.4s; border-radius:24px; }
        .slider:before { position:absolute; content:""; height:16px; width:16px; left:4px; bottom:4px; background:#fff; transition:.4s; border-radius:50%; }
        input:checked + .slider { background:var(--accent-green); }
        input:checked + .slider:before { transform:translateX(26px); }
        .form-actions { display:flex; justify-content:flex-end; align-items:center; gap:12px; margin-top:24px; padding-top:16px; border-top:1px solid rgba(255,255,255,.08); }
        hr.divider { border:none; border-top:1px solid rgba(255,255,255,.08); margin:20px 0; }

        /* ========== GALERI KONTRIBUSI ========== */
        .review-section { background:rgba(0,0,0,.75); border-radius:15px; padding:30px; box-shadow:0 4px 20px rgba(0,0,0,.5); margin-bottom:50px; }
        .review-section h3 { font-size:22px; font-weight:600; margin-bottom:20px; }
        .filter-bar { display:flex; gap:12px; margin-bottom:22px; align-items:center; flex-wrap:wrap; }
        .filter-btn { padding:9px 18px; background:var(--dark-bg-secondary); color:var(--text-secondary); border:1px solid rgba(255,255,255,.1); border-radius:8px; cursor:pointer; font-weight:500; font-family:'Poppins',sans-serif; transition:.2s; }
        .filter-btn:hover { color:#fff; }
        .filter-btn.active { background:var(--accent-green); color:#fff; border-color:var(--accent-green); }
        .search-input { background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15); border-radius:8px; padding:9px 16px; color:#fff; font-family:'Poppins',sans-serif; outline:none; width:240px; margin-left:auto; }
        .review-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:18px; margin-top:10px; }
        .review-card { background:var(--dark-bg-secondary); border-radius:10px; padding:16px; border:1px solid rgba(255,255,255,.05); display:flex; flex-direction:column; gap:12px; }
        .card-header { display:flex; align-items:center; gap:10px; }
        .rv-avatar { width:34px; height:34px; border-radius:50%; display:flex; justify-content:center; align-items:center; font-size:14px; font-weight:600; color:#fff; flex-shrink:0; }
        .user-details { flex-grow:1; line-height:1.3; }
        .user-name { font-weight:600; font-size:15px; display:block; }
        .review-date { font-size:11px; color:var(--text-secondary); }
        .status-badge { display:inline-block; padding:4px 12px; border-radius:5px; font-size:12px; font-weight:600; flex-shrink:0; }
        .badge-pending  { background:#e67e22; color:#fff; }
        .badge-approved { background:#27AE60; color:#fff; }
        .badge-rejected { background:#C0392B; color:#fff; }
        .stars { color:#F1C40F; font-size:13px; }
        .stars .empty { color:#555; }
        .review-text { font-size:14px; line-height:1.6; color:#ddd; flex-grow:1; }
        .review-article { font-size:12px; color:#888; }
        .card-actions { display:flex; gap:10px; justify-content:flex-end; padding-top:10px; border-top:1px solid rgba(255,255,255,.05); }
        .action-btn { background:none; border:1px solid rgba(255,255,255,.15); padding:7px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-family:'Poppins',sans-serif; transition:.2s; display:flex; align-items:center; gap:6px; }
        .action-btn.approve { color:#27AE60; border-color:#27AE60; }
        .action-btn.approve:hover { background:#27AE60; color:#fff; }
        .action-btn.reject { color:#C0392B; border-color:#C0392B; }
        .action-btn.reject:hover { background:#C0392B; color:#fff; }
        .action-btn.delete { color:#888; border-color:#444; }
        .action-btn.delete:hover { background:#444; color:#fff; }
        .empty-state { text-align:center; padding:60px 20px; color:var(--text-secondary); }
        .empty-state i { font-size:48px; margin-bottom:16px; display:block; }
        .loading { text-align:center; padding:40px; color:#aaa; }

        /* ========== TOAST ========== */
        .toast { position:fixed; bottom:30px; right:30px; background:#1F4529; color:#fff; padding:14px 22px; border-radius:10px; font-size:14px; z-index:99999; opacity:0; transform:translateY(20px); transition:.3s; pointer-events:none; }
        .toast.show { opacity:1; transform:translateY(0); }
        .toast.error { background:#C0392B; }
    </style>
</head>
<body>
<script>
    let _confirmResolve = null;
    function showConfirm(title, msg, icon='⚠️', okLabel='Ya, Lanjutkan', okClass='btn-danger') {
        document.getElementById('confirmTitle').textContent = title;
        document.getElementById('confirmMsg').textContent   = msg;
        document.getElementById('confirmIcon').textContent  = icon;
        const btn = document.getElementById('confirmOkBtn');
        btn.textContent  = okLabel;
        btn.className    = okClass;
        document.getElementById('confirmModal').classList.add('active');
        return new Promise(res => {
            _confirmResolve = res;
            btn.onclick = () => { _confirmResolve = null; document.getElementById('confirmModal').classList.remove('active'); res(true); };
        });
    }
    function closeConfirm() {
        document.getElementById('confirmModal').classList.remove('active');
        if (_confirmResolve) { const r = _confirmResolve; _confirmResolve = null; r(false); }
    }
</script>

<!-- Modal Konfirmasi Custom — harus ada sebelum semua script lain yang memanggil showConfirm -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box" style="width:360px;text-align:center">
        <div id="confirmIcon" style="font-size:36px;margin-bottom:12px"></div>
        <h4 id="confirmTitle" style="margin-bottom:8px"></h4>
        <p id="confirmMsg" style="color:#aaa;font-size:13px;margin-bottom:24px;line-height:1.6"></p>
        <div style="display:flex;gap:10px;justify-content:center">
            <button class="btn-cancel" onclick="closeConfirm()">Batal</button>
            <button id="confirmOkBtn" class="btn-danger">Ya, Lanjutkan</button>
        </div>
    </div>
</div>
<div class="account-page">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar">
        <div class="sidebar-top">
            <a href="index.php" class="logo">JelajahinNusa</a>
            <h4>Dashboard Admin</h4>
            <nav class="nav-menu-sidebar">
                <a href="<?= adminUrl('users') ?>"            <?= navActive('users',            $mode) ?>><i class="fas fa-users"></i> Manajemen Pengguna</a>
                <a href="<?= adminUrl('articles') ?>"         <?= navActive('articles',         $mode) ?>><i class="fas fa-file-invoice"></i> Manajemen Artikel</a>
                <a href="<?= adminUrl('galeri_kontribusi') ?>" <?= navActive('galeri_kontribusi', $mode) ?>><i class="fas fa-images"></i> Manajemen Foto</a>
                <a href="<?= adminUrl('usul_user') ?>"        <?= navActive('usul_user',        $mode) ?>><i class="fas fa-lightbulb"></i> Usul User</a>
                <a href="<?= adminUrl('broadcast') ?>"         <?= navActive('broadcast',         $mode) ?>><i class="fas fa-bell"></i> Kirim Notifikasi</a>
                <a href="<?= adminUrl('settings') ?>"          <?= navActive('settings',          $mode) ?>><i class="fas fa-gear"></i> Pengaturan</a>
            </nav>
        </div>
        <div class="sidebar-bottom">
            <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Keluar</a>
        </div>
    </aside>

    <!-- ===== MAIN ===== -->
    <div class="main-content">
        <div class="bg-img"></div>
        <div class="content-wrapper">

            <!-- Header -->
            <div class="main-header">
                <?php if ($mode !== 'settings'): ?>
                <form class="search-bar-main" onsubmit="return false;">
                    <?php
                    $placeholders = [
                        'users'             => 'Cari username / email...',
                        'articles'          => 'Cari judul / kategori artikel...',
                        'galeri_kontribusi' => 'Cari foto, pengguna, artikel...',
                        'usul_user'         => 'Cari pesan / pengguna...',
                    ];
                    $ph = $placeholders[$mode] ?? 'Cari...';
                    ?>
                    <input type="text" id="globalSearch" placeholder="<?= htmlspecialchars($ph) ?>" autocomplete="off">
                    <i class="fas fa-search"></i>
                </form>
                <?php else: ?>
                <div></div>
                <?php endif; ?>
                <div class="admin-profile">
                    <div class="admin-profile-pic"><i class="fas fa-user-circle"></i></div>
                    <span><?= htmlspecialchars($session['username']) ?></span>
                </div>
            </div>

            <?php
            // ================================================================
            //  RENDER PER MODE
            // ================================================================

            /* ---- MODE: USERS ---- */
            if ($mode === 'users'): ?>

            <div class="stats-row" style="grid-template-columns:repeat(4,1fr)">
                <div class="stat-box" id="userStatAll" onclick="userSetFilter('all')" style="cursor:pointer">
                    <div class="num" id="statTotal">-</div><div class="label">Total Akun</div></div>
                <div class="stat-box" onclick="userSetFilter('user')" id="userStatUser" style="cursor:pointer">
                    <div class="num" style="color:#6fcf97" id="statUser">-</div><div class="label">Role User</div></div>
                <div class="stat-box" onclick="userSetFilter('admin')" id="userStatAdmin" style="cursor:pointer">
                    <div class="num" style="color:#9b59b6" id="statAdmin">-</div><div class="label">Role Admin</div></div>
                <div class="stat-box" id="userStatOnline" style="cursor:default">
                    <div class="num" style="color:#f39c12" id="statOnline">-</div><div class="label">Online Sekarang <span style="font-size:9px;color:#666">(15 mnt)</span></div></div>
            </div>

            <div class="section-card">
                <h3>Daftar Pengguna</h3>
                <div class="search-filter" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px">
                    <input type="hidden" id="userSearch">
                    <select id="userPerPage" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:9px 14px;color:#fff;font-family:'Poppins',sans-serif;outline:none">
                        <option value="10">10 / hal</option><option value="25" selected>25 / hal</option><option value="50">50 / hal</option><option value="100">100 / hal</option>
                    </select>
                </div>
                <table style="table-layout:fixed;width:100%">
                    <thead>
                        <tr>
                            <th style="width:40px">No</th>
                            <th style="width:44px">Foto</th>
                            <th class="sortable" data-col="username" onclick="userSort('username')" style="cursor:pointer;user-select:none;width:130px">Username <span id="sort-username" style="font-size:10px;color:#666">⇅</span></th>
                            <th class="sortable" data-col="email" onclick="userSort('email')" style="cursor:pointer;user-select:none">E-mail <span id="sort-email" style="font-size:10px;color:#666">⇅</span></th>
                            <th class="sortable" data-col="country" onclick="userSort('country')" style="cursor:pointer;user-select:none;width:100px">Negara <span id="sort-country" style="font-size:10px;color:#666">⇅</span></th>
                            <th class="sortable" data-col="created_at" onclick="userSort('created_at')" style="cursor:pointer;user-select:none;width:110px">Tgl Daftar <span id="sort-created_at" style="font-size:10px;color:#aaa">↓</span></th>
                            <th class="sortable" data-col="last_active" onclick="userSort('last_active')" style="cursor:pointer;user-select:none;width:120px">Terakhir Aktif <span id="sort-last_active" style="font-size:10px;color:#666">⇅</span></th>
                            <th style="width:80px">Role</th>
                            <th style="width:50px">Aksi</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody"></tbody>
                </table>
                <div class="pagination">
                    <span id="paginationInfo">0 pengguna</span>
                    <button id="prevBtn"><i class="fas fa-chevron-left"></i></button>
                    <span id="pageNum">1</span>
                    <button id="nextBtn"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>

            <script>
                const API = 'dashboardAdmin.php?mode=api&action=';
                let currentPage = 1, perPage = 25, allUsers = [], filteredUsers = [];
                let userSortCol = 'created_at', userSortDir = 'desc';
                let userRoleFilter = 'all'; // 'all' | 'user' | 'admin'

                function userSetFilter(f) {
                    userRoleFilter = f;
                    ['all','user','admin'].forEach(s => {
                        const key = s.charAt(0).toUpperCase()+s.slice(1);
                        const el = document.getElementById('userStat'+key);
                        if (el) el.style.outline = s === f ? '2px solid rgba(255,255,255,.4)' : 'none';
                    });
                    currentPage = 1;
                    applySortFilter();
                }

                function showToast(msg, type='success') {
                    const t = document.getElementById('toast');
                    t.textContent = msg;
                    t.className = 'toast show' + (type==='error' ? ' error' : '');
                    setTimeout(() => t.className='toast', 3000);
                }
                function colorFromName(name) {
                    let h=0; for(let i=0;i<name.length;i++) h=name.charCodeAt(i)+((h<<5)-h);
                    return `hsl(${Math.abs(h)%360},50%,40%)`;
                }

                // Format waktu terakhir aktif
                function formatLastActive(dt) {
                    if (!dt) return '<span style="color:#444">—</span>';
                    const diff = Math.floor((Date.now() - new Date(dt).getTime()) / 1000);
                    if (diff < 60)  return `<span style="color:#6fcf97;font-size:11px">● Online</span>`;
                    if (diff < 3600) return `<span style="color:#f39c12;font-size:11px">${Math.floor(diff/60)} mnt lalu</span>`;
                    if (diff < 86400) return `<span style="color:#aaa;font-size:11px">${Math.floor(diff/3600)} jam lalu</span>`;
                    const d = new Date(dt);
                    return `<span style="color:#666;font-size:11px">${d.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'})}</span>`;
                }

                function userSort(col) {
                    if (userSortCol === col) userSortDir = userSortDir === 'asc' ? 'desc' : 'asc';
                    else { userSortCol = col; userSortDir = col === 'created_at' || col === 'last_active' ? 'desc' : 'asc'; }
                    ['username','email','country','created_at','last_active'].forEach(c => {
                        const el = document.getElementById('sort-'+c);
                        if (el) el.textContent = c === userSortCol ? (userSortDir==='asc'?'↑':'↓') : '⇅';
                        if (el) el.style.color = c === userSortCol ? '#6fcf97' : '#666';
                    });
                    applySortFilter();
                }

                function applySortFilter() {
                    const q = document.getElementById('userSearch').value.toLowerCase();
                    filteredUsers = allUsers.filter(u => {
                        const matchQ = u.username.toLowerCase().includes(q)||u.email.toLowerCase().includes(q)||(u.country||'').toLowerCase().includes(q);
                        if (userRoleFilter === 'user')  return matchQ && u.type === 'user';
                        if (userRoleFilter === 'admin') return matchQ && u.type === 'admin';
                        return matchQ;
                    });

                    // ── Sorting ──
                    if (userSortCol === 'role') {
                        filteredUsers.sort((a,b) => {
                            const aA = a.type==='admin', bA = b.type==='admin';
                            if (aA !== bA) return userSortDir==='asc' ? (aA?-1:1) : (aA?1:-1);
                            return 0;
                        });
                    } else if (userSortCol === 'last_active') {
                        filteredUsers.sort((a,b) => {
                            const ta = a.last_active ? new Date(a.last_active).getTime() : 0;
                            const tb = b.last_active ? new Date(b.last_active).getTime() : 0;
                            return userSortDir==='desc' ? tb-ta : ta-tb;
                        });
                    } else {
                        filteredUsers.sort((a,b) => {
                            let va = (a[userSortCol]||'').toString().toLowerCase();
                            let vb = (b[userSortCol]||'').toString().toLowerCase();
                            return userSortDir==='asc' ? va.localeCompare(vb) : vb.localeCompare(va);
                        });
                    }

                    currentPage = 1;
                    const totalAdmin = allUsers.filter(u => u.type === 'admin').length;
                    const totalUser  = allUsers.filter(u => u.type === 'user').length;
                    document.getElementById('statTotal').textContent = allUsers.length;
                    document.getElementById('statUser').textContent  = totalUser;
                    document.getElementById('statAdmin').textContent = totalAdmin;
                    renderTable(filteredUsers);
                }

                function renderTable(users) {
                    const tbody = document.getElementById('userTableBody');
                    const start = (currentPage-1)*perPage;
                    const slice = users.slice(start, start+perPage);
                    tbody.innerHTML = '';
                    if (!slice.length) {
                        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;color:#aaa;padding:30px">Tidak ada pengguna.</td></tr>`;
                    }
                    slice.forEach((u, i) => {
                        const initials = (u.username||'?').charAt(0).toUpperCase();
                        const avatar = u.profile_photo
                            ? `<img src="${u.profile_photo}" class="user-avatar-img">`
                            : `<span class="user-avatar" style="background:${colorFromName(u.username||'?')}">${initials}</span>`;
                        const tr = document.createElement('tr');
                        const tgl = u.created_at ? u.created_at.split(' ')[0] : '-';
                        const isAdmin = u.type === 'admin';
                        const isBanned = parseInt(u.is_active) === 0;

                        // Badge Role
                        let roleBadge;
                        if (isAdmin) {
                            roleBadge = `<span class="badge badge-role-admin">Admin</span>`;
                        } else if (isBanned) {
                            roleBadge = `<span class="badge badge-role-banned">Banned</span>`;
                        } else {
                            roleBadge = `<span class="badge badge-role-user">User</span>`;
                        }

                        // Tombol aksi: admin tidak bisa di-ban atau dihapus
                        let actionBtns;
                        if (isAdmin) {
                            actionBtns = `<span style="color:#555;font-size:11px;padding:4px 8px">—</span>`;
                        } else {
                            const banIcon = isBanned
                                ? `<i class="fas fa-rotate-left" title="Cabut Ban" onclick="unbanUser('${u.id}')"></i>`
                                : `<i class="fas fa-ban" title="Ban User" onclick="banUser('${u.id}')"></i>`;
                            actionBtns = banIcon;
                        }

                        tr.innerHTML = `
                            <td>${start+i+1}</td><td>${avatar}</td>
                            <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${u.username}</td>
                            <td style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${u.email}</td>
                            <td style="font-size:12px">${u.country||'Indonesia'}</td>
                            <td style="font-size:11px;color:#777">${tgl}</td>
                            <td>${formatLastActive(u.last_active)}</td>
                            <td>${roleBadge}</td>
                            <td class="action-btns">${actionBtns}</td>
                            <td class="action-btns">${isAdmin ? '' : `<i class="fas fa-trash-alt" onclick="deleteUser('${u.id}')" title="Hapus Permanen"></i>`}</td>`;
                        tbody.appendChild(tr);
                    });
                    const total = users.length, totalPages = Math.max(1,Math.ceil(total/perPage));
                    document.getElementById('paginationInfo').textContent = `${total} pengguna`;
                    document.getElementById('pageNum').textContent = `${currentPage} / ${totalPages}`;
                    document.getElementById('prevBtn').disabled = currentPage<=1;
                    document.getElementById('nextBtn').disabled = currentPage>=totalPages;
                }

                async function loadUsers() {
                    try {
                        const res = await fetch(API+'get_users');
                        const j = await res.json();
                        if (j.success && Array.isArray(j.data)) {
                            allUsers = j.data;
                            filteredUsers = [...allUsers];
                            applySortFilter();
                        } else {
                            document.getElementById('userTableBody').innerHTML =
                                `<tr><td colspan="9" style="text-align:center;color:#e74c3c;padding:30px">Gagal memuat data: ${j.message||'unknown error'}</td></tr>`;
                        }
                    } catch(err) {
                        document.getElementById('userTableBody').innerHTML =
                            `<tr><td colspan="9" style="text-align:center;color:#e74c3c;padding:30px">Error: ${err.message}</td></tr>`;
                    }
                    // Load online users
                    try {
                        const o = await (await fetch(API+'get_active_users')).json();
                        if (o.success) document.getElementById('statOnline').textContent = o.data.active_users;
                    } catch(e) {}
                }

                document.getElementById('userSearch').addEventListener('input', applySortFilter);
                document.getElementById('userPerPage').addEventListener('change', function(){ perPage=parseInt(this.value); currentPage=1; renderTable(filteredUsers); });
                document.getElementById('prevBtn').addEventListener('click', () => { if(currentPage>1){currentPage--;renderTable(filteredUsers);} });
                document.getElementById('nextBtn').addEventListener('click', () => { currentPage++;renderTable(filteredUsers); });
                document.getElementById('globalSearch').addEventListener('input', function() {
                    document.getElementById('userSearch').value = this.value;
                    applySortFilter();
                });

                // Sort kolom Role
                document.querySelector('th:nth-child(9)')?.addEventListener('click', function(){
                    userSortCol = 'role';
                    userSortDir = userSortDir === 'asc' ? 'desc' : 'asc';
                    applySortFilter();
                });

                window.deleteUser = async function(id) {
                    if (!await showConfirm('Hapus Pengguna', 'Tindakan ini tidak dapat dibatalkan. Semua data pengguna akan dihapus permanen.', '🗑️', 'Ya, Hapus')) return;
                    const form = new FormData(); form.append('id',id);
                    const j = await (await fetch(API+'delete_user',{method:'POST',body:form})).json();
                    if (j.success) { loadUsers(); showToast('Pengguna berhasil dihapus.'); }
                    else showToast(j.message,'error');
                }
                window.banUser = async function(id) {
                    if (!await showConfirm('Ban Pengguna', 'Pengguna tidak akan bisa login sampai ban dicabut.', '🚫', 'Ya, Ban')) return;
                    const form = new FormData(); form.append('id', id);
                    const j = await (await fetch(API+'ban_user',{method:'POST',body:form})).json();
                    if (j.success) { loadUsers(); showToast('Pengguna berhasil dibanned.'); }
                    else showToast(j.message,'error');
                }
                window.unbanUser = async function(id) {
                    if (!await showConfirm('Cabut Ban', 'Pengguna akan bisa login kembali setelah ban dicabut.', '✅', 'Ya, Cabut Ban', 'btn-save')) return;
                    const form = new FormData(); form.append('id', id);
                    const j = await (await fetch(API+'unban_user',{method:'POST',body:form})).json();
                    if (j.success) { loadUsers(); showToast('Ban pengguna berhasil dicabut.'); }
                    else showToast(j.message,'error');
                }

                // Refresh online count setiap 60 detik
                setInterval(async () => {
                    try {
                        const o = await (await fetch(API+'get_active_users')).json();
                        if (o.success) document.getElementById('statOnline').textContent = o.data.active_users;
                    } catch(e){}
                }, 60000);

                loadUsers();
            </script>

            <?php /* ---- MODE: ARTICLES ---- */
            elseif ($mode === 'articles'): ?>

            <div class="stats-row cols4">
                <div class="stat-box active" id="artStat-all"       onclick="artSetFilter('all')">      <div class="num" id="artStatTotal">-</div><div class="label">Total Artikel</div></div>
                <div class="stat-box"         id="artStat-published" onclick="artSetFilter('published')"><div class="num approved-num" id="artStatPublished">-</div><div class="label">Terbit</div></div>
                <div class="stat-box"         id="artStat-draft"     onclick="artSetFilter('draft')">    <div class="num" style="color:#aaa" id="artStatDraft">-</div><div class="label">Draf</div></div>
                <div class="stat-box"         id="artStat-views"     style="cursor:default">             <div class="num" style="color:#6fcf97" id="artStatViews">-</div><div class="label">Total Views</div></div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <h3>Daftar Artikel</h3>
                    <button class="btn-add" onclick="openAddModal()"><i class="fas fa-plus-circle"></i> Tambah Artikel</button>
                </div>
                <div class="search-filter">
                    <input type="hidden" id="artSearch">
                    <select type="hidden" id="statusFilter" style="display:none"></select>
                    <select id="artPerPage" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:9px 14px;color:#fff;font-family:'Poppins',sans-serif;outline:none">
                        <option value="10">10 / hal</option><option value="25" selected>25 / hal</option><option value="50">50 / hal</option><option value="100">100 / hal</option>
                    </select>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th class="sortable" onclick="artSortBy('title')" style="cursor:pointer;user-select:none">Judul Artikel <span id="asort-title" style="font-size:10px;color:#666">⇅</span></th>
                            <th class="sortable" onclick="artSortBy('category')" style="cursor:pointer;user-select:none">Kategori <span id="asort-category" style="font-size:10px;color:#666">⇅</span></th>
                            <th class="sortable" onclick="artSortBy('date')" style="cursor:pointer;user-select:none">Tanggal <span id="asort-date" style="font-size:10px;color:#aaa">↓</span></th>
                            <th class="sortable" onclick="artSortBy('status')" style="cursor:pointer;user-select:none">Status <span id="asort-status" style="font-size:10px;color:#666">⇅</span></th>
                            <th class="sortable" onclick="artSortBy('views')" style="cursor:pointer;user-select:none">Views <span id="asort-views" style="font-size:10px;color:#666">⇅</span></th>
                            <th colspan="2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="artTableBody"></tbody>
                </table>
                <div class="pagination">
                    <span id="artPaginInfo"></span>
                    <button id="artPrev"><i class="fas fa-chevron-left"></i></button>
                    <span id="artPage">1</span>
                    <button id="artNext"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>

            <!-- Modal Artikel -->
            <div class="modal-overlay" id="artModal">
                <div class="modal-box" style="width:680px;">
                    <h4 id="modalTitle">Tambah Artikel</h4>
                    <input type="hidden" id="artId">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="grid-column:1/-1">
                            <label>Judul</label>
                            <input type="text" id="artTitle" placeholder="Judul artikel...">
                        </div>
                        <div class="form-group">
                            <label>Kategori</label>
                            <select id="artCategory"></select>
                        </div>
                        <div class="form-group">
                            <label>Wilayah</label>
                            <select id="artWilayah">
                                <option value="Jawa">Jawa</option>
                                <option value="Sumatra">Sumatra</option>
                                <option value="Kalimantan">Kalimantan</option>
                                <option value="Sulawesi">Sulawesi</option>
                                <option value="Bali">Bali &amp; Nusa Tenggara</option>
                                <option value="Papua">Maluku &amp; Papua</option>
                            </select>
                            <input type="hidden" id="artLocation">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select id="artStatus">
                                <option value="published">Terbit</option>
                                <option value="draft">Draf</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>URL / Path Gambar Hero</label>
                            <input type="text" id="artImage" placeholder="Gambar/nama.jpg">
                        </div>

                        <div class="form-group" style="grid-column:1/-1">
                            <label>📝 Deskripsi Pengenalan</label>
                            <textarea id="artContentDeskripsi" rows="5" placeholder="Tulis pengenalan umum destinasi wisata ini..."></textarea>
                        </div>
                        <div class="form-group" style="grid-column:1/-1">
                            <label>🗺️ Jalur &amp; Akses</label>
                            <textarea id="artContentJalur" rows="5" placeholder="Jelaskan cara menuju lokasi, jalur yang tersedia, transportasi, dll..."></textarea>
                        </div>
                        <div class="form-group" style="grid-column:1/-1">
                            <label>⭐ Daya Tarik</label>
                            <textarea id="artContentDayaTarik" rows="5" placeholder="Jelaskan keunikan, daya tarik utama, aktivitas yang bisa dilakukan..."></textarea>
                        </div>
                        <div class="form-group" style="grid-column:1/-1">
                            <label>Google Maps Embed URL</label>
                            <input type="text" id="artMaps" placeholder="https://www.google.com/maps/embed?pb=...">
                            <small style="color:#888;font-size:11px">Google Maps → Share → Embed a map → salin URL dari src="..."</small>
                        </div>
                        <div class="form-group" style="grid-column:1/-1">
                            <label>Info Kunjungan</label>
                            <textarea id="artInfo" rows="4" placeholder="Jam buka: 24 jam&#10;HTM: Rp 29.000&#10;Waktu terbaik: April – Oktober"></textarea>
                            <small style="color:#888;font-size:11px">Format: <strong>Kunci: Nilai</strong> — satu baris satu item.</small>
                        </div>
                        <div class="form-group" style="grid-column:1/-1">
                            <label>🖼️ Galeri Foto <small style="color:#888;font-weight:normal">(pilih dari tabel galeri)</small></label>
                            <div style="display:flex;gap:8px;margin-bottom:8px">
                                <input type="text" id="galSearch" placeholder="Cari foto..." style="flex:1;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:8px 12px;color:#fff;font-family:'Poppins',sans-serif;outline:none">
                                <button type="button" onclick="loadGaleriList()" style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:8px 14px;color:#fff;cursor:pointer;font-size:13px"><i class="fas fa-sync-alt"></i></button>
                            </div>
                            <div id="galeriGrid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;max-height:220px;overflow-y:auto;background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:8px"></div>
                            <div style="margin-top:8px">
                                <small style="color:#888;font-size:11px">Foto dipilih: </small>
                                <div id="galeriSelected" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px"></div>
                                <input type="hidden" id="artGalleryIds">
                            </div>
                        </div>
                    </div>

                    <div class="modal-btns">
                        <button class="btn-cancel" onclick="closeArtModal()">Batal</button>
                        <button class="btn-save" onclick="saveArticle()">Simpan Artikel</button>
                    </div>
                </div>
            </div>

            <script>
                const API = 'dashboardAdmin.php?mode=api&action=';
                let artPage=1, perPage=10, allArticles=[], filteredArticles=[], editingArtId=null, categories=[];
                let artSortCol='date', artSortDir='desc';

                function showToast(msg,type='success'){
                    const t=document.getElementById('toast'); t.textContent=msg;
                    t.className='toast show'+(type==='error'?' error':''); setTimeout(()=>t.className='toast',3000);
                }
                function artSortBy(col){
                    if(artSortCol===col) artSortDir=artSortDir==='asc'?'desc':'asc';
                    else { artSortCol=col; artSortDir=col==='views'?'desc':'asc'; }
                    ['title','category','date','status','views'].forEach(c=>{
                        const el=document.getElementById('asort-'+c);
                        if(el){ el.textContent=c===artSortCol?(artSortDir==='asc'?'↑':'↓'):'⇅'; el.style.color=c===artSortCol?'#6fcf97':'#666'; }
                    });
                    artPage=1; applyFilter();
                }
                function renderArt(articles){
                    const start=(artPage-1)*perPage, slice=articles.slice(start,start+perPage);
                    const tbody=document.getElementById('artTableBody'); tbody.innerHTML='';
                    if(!slice.length){ tbody.innerHTML=`<tr><td colspan="8" style="text-align:center;color:#aaa;padding:30px">Tidak ada artikel.</td></tr>`; }
                    slice.forEach((a,i)=>{
                        const badge=a.status==='published'
                            ?`<span class="badge badge-published">Terbit</span>`
                            :`<span class="badge badge-draft">Draf</span>`;
                        const viewerLink = `artikel_viewer.php?id=${encodeURIComponent(a.id)}`;
                        const tr=document.createElement('tr');
                        tr.innerHTML=`<td>${start+i+1}</td><td style="max-width:260px">${a.title}</td>
                            <td style="font-size:12px;color:#aaa">${a.category}</td><td style="font-size:12px">${a.date}</td>
                            <td>${badge}</td><td style="text-align:center">${a.views||0}</td>
                            <td class="action-btns">
                                <i class="fas fa-pen-to-square" title="Edit" onclick="openEditArt('${a.id}')"></i>
                                <i class="fas fa-eye" title="Preview" style="cursor:pointer;color:#aaa" onclick="location.href='${viewerLink}'"></i>
                            </td>
                            <td class="action-btns"><i class="fas fa-trash-alt" onclick="deleteArt('${a.id}')"></i></td>`;
                        tbody.appendChild(tr);
                    });
                    const total=articles.length, totalPages=Math.max(1,Math.ceil(total/perPage));
                    document.getElementById('artPaginInfo').textContent=`${total} artikel`;
                    document.getElementById('artPage').textContent=`${artPage} / ${totalPages}`;
                    document.getElementById('artPrev').disabled=artPage<=1;
                    document.getElementById('artNext').disabled=artPage>=totalPages;
                }
                async function loadArticles(){
                    const j=await(await fetch(API+'get_articles')).json();
                    if(j.success){
                        allArticles=j.data; categories=j.categories;
                        document.getElementById('artCategory').innerHTML=categories.map(c=>`<option value="${c.id}">${c.nama}</option>`).join('');
                        // Hitung stats
                        const published = allArticles.filter(a=>a.status==='published').length;
                        const draft     = allArticles.filter(a=>a.status==='draft').length;
                        const views     = allArticles.reduce((s,a)=>s+(+a.views||0),0);
                        document.getElementById('artStatTotal').textContent     = allArticles.length;
                        document.getElementById('artStatPublished').textContent = published;
                        document.getElementById('artStatDraft').textContent     = draft;
                        document.getElementById('artStatViews').textContent     = views.toLocaleString('id-ID');
                        applyFilter();
                    }
                }
                let artStatusFilter = '';

                function artSetFilter(f) {
                    artStatusFilter = (f === 'all') ? '' : f;
                    ['all','published','draft'].forEach(s =>
                        document.getElementById('artStat-'+s)?.classList.toggle('active', s === f)
                    );
                    document.getElementById('statusFilter').value = artStatusFilter;
                    artPage = 1; applyFilter();
                }
                function applyFilter(){
                    const q=document.getElementById('artSearch').value.toLowerCase();
                    const status = artStatusFilter !== '' ? artStatusFilter : document.getElementById('statusFilter').value;
                    filteredArticles=allArticles.filter(a=>(a.title.toLowerCase().includes(q)||a.category.toLowerCase().includes(q))&&(!status||a.status===status));
                    filteredArticles.sort((a,b)=>{
                        let va,vb;
                        if(artSortCol==='views'){ va=+(a.views||0); vb=+(b.views||0); return artSortDir==='asc'?va-vb:vb-va; }
                        va=(a[artSortCol]||'').toString().toLowerCase();
                        vb=(b[artSortCol]||'').toString().toLowerCase();
                        return artSortDir==='asc'?va.localeCompare(vb):vb.localeCompare(va);
                    });
                    artPage=1; renderArt(filteredArticles);
                }
                document.getElementById('artSearch').addEventListener('input',applyFilter);
                document.getElementById('statusFilter').addEventListener('change', function(){
                    artStatusFilter = this.value;
                    const active = this.value || 'all';
                    ['all','published','draft'].forEach(s =>
                        document.getElementById('artStat-'+s)?.classList.toggle('active', s === active)
                    );
                    applyFilter();
                });
                document.getElementById('globalSearch').addEventListener('input', function(){
                    document.getElementById('artSearch').value = this.value;
                    applyFilter();
                });
                document.getElementById('artPrev').addEventListener('click',()=>{if(artPage>1){artPage--;renderArt(filteredArticles);}});
                document.getElementById('artNext').addEventListener('click',()=>{artPage++;renderArt(filteredArticles);});
                document.getElementById('artPerPage').addEventListener('change',function(){ perPage=parseInt(this.value); artPage=1; renderArt(filteredArticles); });
                function clearArtForm(){
                    ['artTitle','artLocation','artImage','artMaps','artInfo',
                     'artContentDeskripsi','artContentJalur','artContentDayaTarik','artGalleryIds','galSearch']
                        .forEach(id=>{ const el=document.getElementById(id); if(el) el.value=''; });
                    document.getElementById('artStatus').value='published';
                    document.getElementById('artWilayah').value='';
                    document.getElementById('artId').value='';
                    selectedGaleri={};
                    renderSelectedGaleri();
                    document.getElementById('galeriGrid').innerHTML='<p style="color:#888;font-size:13px;text-align:center;padding:20px">Klik refresh untuk memuat foto dari galeri.</p>';
                }
                function openAddModal(){
                    editingArtId=null;
                    document.getElementById('modalTitle').textContent='Tambah Artikel Baru';
                    clearArtForm();
                    loadGaleriList();
                    document.getElementById('artModal').classList.add('active');
                }
                async function openEditArt(id){
                    const j=await(await fetch(API+'get_article&id='+id)).json();
                    if(!j.success) return showToast('Gagal memuat artikel.','error');
                    const a=j.data; editingArtId=id;
                    document.getElementById('modalTitle').textContent='Edit Artikel';
                    document.getElementById('artId').value                  = a.id;
                    document.getElementById('artTitle').value               = a.title              || '';
                    document.getElementById('artLocation').value            = a.location           || '';
                    document.getElementById('artContentDeskripsi').value    = a.content_deskripsi  || '';
                    document.getElementById('artContentJalur').value        = a.content_jalur      || '';
                    document.getElementById('artContentDayaTarik').value    = a.content_daya_tarik || '';
                    document.getElementById('artImage').value               = a.image              || '';
                    document.getElementById('artMaps').value                = a.maps_embed         || '';
                    document.getElementById('artInfo').value                = a.info_json_raw       || '';
                    document.getElementById('artStatus').value              = a.status             || 'draft';
                    // Set wilayah dropdown
                    const wilayahSel=document.getElementById('artWilayah');
                    // Nilai location = value wilayah (Jawa, Sumatra, Kalimantan, Sulawesi, Bali, Papua)
                    wilayahSel.value = a.location || '';
                    const sel=document.getElementById('artCategory');
                    if(sel.options.length===0){
                        sel.innerHTML=categories.map(c=>`<option value="${c.id}">${c.nama}</option>`).join('');
                    }
                    for(let opt of sel.options){ if(opt.value===String(a.kategori_id)){opt.selected=true;break;} }
                    // Load galeri list lalu set selected
                    await loadGaleriList();
                    selectedGaleri={};
                    (a.gallery_ids||[]).forEach(gid=>{
                        const found=allGaleri.find(g=>String(g.id)===String(gid));
                        if(found) selectedGaleri[gid]=found;
                    });
                    renderSelectedGaleri();
                    renderGaleriGrid(allGaleri);
                    document.getElementById('artModal').classList.add('active');
                }
                function closeArtModal(){ document.getElementById('artModal').classList.remove('active'); editingArtId=null; }
                async function saveArticle(){
                    const title=document.getElementById('artTitle').value.trim();
                    const katId=document.getElementById('artCategory').value;
                    if(!title||!katId) return showToast('Judul dan kategori wajib diisi.','error');
                    const form=new FormData();
                    form.append('title',              title);
                    form.append('kategori_id',        katId);
                    // Simpan nilai wilayah langsung (Jawa/Sumatra/dll) ke kolom location
                    const wilayahFinal = document.getElementById('artWilayah').value;
                    form.append('location', wilayahFinal);
                    form.append('content_deskripsi',  document.getElementById('artContentDeskripsi').value.trim());
                    form.append('content_jalur',      document.getElementById('artContentJalur').value.trim());
                    form.append('content_daya_tarik', document.getElementById('artContentDayaTarik').value.trim());
                    form.append('image',              document.getElementById('artImage').value.trim()||'Gambar/hero.jpg');
                    form.append('maps_embed',         document.getElementById('artMaps').value.trim());
                    form.append('info_json_raw',      document.getElementById('artInfo').value.trim());
                    form.append('gallery_ids',        Object.keys(selectedGaleri).join(','));
                    form.append('status',             document.getElementById('artStatus').value);
                    let url=API+'add_article';
                    if(editingArtId){ form.append('id',editingArtId); url=API+'update_article'; }
                    const j=await(await fetch(url,{method:'POST',body:form})).json();
                    if(j.success){ closeArtModal(); loadArticles(); showToast(j.message); }
                    else showToast(j.message,'error');
                }
                async function deleteArt(id){
                    if(!await showConfirm('Hapus Artikel', 'Tindakan ini tidak bisa dibatalkan.', '🗑️', 'Ya, Hapus')) return;
                    const form=new FormData(); form.append('id',id);
                    const j=await(await fetch(API+'delete_article',{method:'POST',body:form})).json();
                    if(j.success){ loadArticles(); showToast('Artikel berhasil dihapus.'); }
                    else showToast(j.message,'error');
                }
                // ============================
                // GALERI PICKER
                // ============================
                let allGaleri=[], selectedGaleri={};

                async function loadGaleriList(){
                    if(allGaleri.length){ renderGaleriGrid(allGaleri); return; }
                    const j=await(await fetch(API+'get_galeri_list')).json();
                    if(j.success){ allGaleri=j.data; renderGaleriGrid(allGaleri); }
                    else document.getElementById('galeriGrid').innerHTML='<p style="color:#e74c3c;font-size:13px;padding:10px">Gagal memuat galeri.</p>';
                }

                function renderGaleriGrid(list){
                    const q=document.getElementById('galSearch').value.toLowerCase();
                    const filtered=q ? list.filter(g=>(g.judul||'').toLowerCase().includes(q)||(g.lokasi||'').toLowerCase().includes(q)) : list;
                    const grid=document.getElementById('galeriGrid');
                    if(!filtered.length){ grid.innerHTML='<p style="color:#888;font-size:13px;text-align:center;padding:20px">Tidak ada foto ditemukan.</p>'; return; }
                    grid.innerHTML=filtered.map(g=>{
                        const selected=selectedGaleri[g.id]?'outline:2px solid #6fcf97;':'';
                        const check=selectedGaleri[g.id]?'<div style="position:absolute;top:4px;right:4px;background:#6fcf97;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:10px">✓</div>':'';
                        return `<div onclick="toggleGaleri(${g.id})" style="position:relative;cursor:pointer;border-radius:6px;overflow:hidden;aspect-ratio:1;${selected}">
                            <img src="${g.image_path}" alt="${g.judul||''}" style="width:100%;height:100%;object-fit:cover">
                            ${check}
                        </div>`;
                    }).join('');
                }

                function toggleGaleri(id){
                    const found=allGaleri.find(g=>g.id==id);
                    if(!found) return;
                    if(selectedGaleri[id]) delete selectedGaleri[id];
                    else selectedGaleri[id]=found;
                    renderSelectedGaleri();
                    renderGaleriGrid(allGaleri);
                }

                function renderSelectedGaleri(){
                    const box=document.getElementById('galeriSelected');
                    const ids=Object.keys(selectedGaleri);
                    document.getElementById('artGalleryIds').value=ids.join(',');
                    if(!ids.length){ box.innerHTML='<span style="color:#888;font-size:12px">Belum ada foto dipilih</span>'; return; }
                    box.innerHTML=ids.map(id=>{
                        const g=selectedGaleri[id];
                        return `<div style="position:relative;width:50px;height:50px;border-radius:6px;overflow:hidden">
                            <img src="${g.image_path}" style="width:100%;height:100%;object-fit:cover">
                            <div onclick="toggleGaleri(${id})" style="position:absolute;inset:0;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;cursor:pointer;opacity:0;transition:.2s" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0">
                                <i class="fas fa-times" style="color:#fff;font-size:14px"></i>
                            </div>
                        </div>`;
                    }).join('');
                }

                document.getElementById('galSearch').addEventListener('input',()=>renderGaleriGrid(allGaleri));

                loadArticles();
            </script>

            <?php /* ---- MODE: SETTINGS ---- */
            elseif ($mode === 'settings'): ?>

            <div class="admin-title">Pengaturan Sistem &amp; Situs Web</div>

            <div class="settings-container">

                <!-- Form 1: Tampilan & Umum -->
                <section class="settings-section">
                    <h3><i class="fas fa-desktop"></i> Tampilan &amp; Umum</h3>
                    <form method="POST" action="<?= adminUrl('settings') ?>">
                        <input type="hidden" name="form" value="umum">
                        <div class="form-group"><label>Nama Situs</label>
                            <input type="text" name="nama_situs" value="<?= cfgVal($cfg,'nama_situs','JelajahinNusa') ?>"></div>
                        <div class="form-group"><label>Deskripsi Situs</label>
                            <input type="text" name="deskripsi_situs" value="<?= cfgVal($cfg,'deskripsi_situs','Temukan pesona wisata alam Indonesia') ?>"></div>
                        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> Simpan</button></div>
                    </form>

                    <hr class="divider" style="margin:28px 0 22px">

                    <!-- Upload Logo Navbar -->
                    <div style="margin-top:4px">
                        <h4 style="font-size:15px;font-weight:600;color:#ddd;margin-bottom:6px"><i class="fas fa-image" style="margin-right:8px;color:#6fcf97"></i>Logo Navbar</h4>
                        <p style="font-size:12px;color:#777;margin-bottom:16px">Logo yang tampil di navbar website. Format: PNG, JPG, SVG, WebP. Maks 2 MB.</p>
                        <div style="display:flex;align-items:center;gap:18px;margin-bottom:16px">
                            <div style="width:90px;height:60px;background:#111;border-radius:10px;border:1px solid #333;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">
                                <?php $currentLogo = $cfg['site_logo'] ?? 'Gambar/logo2.png'; ?>
                                <img id="logoPreviewImg" src="<?= htmlspecialchars($currentLogo) ?>" alt="Logo"
                                     style="max-width:100%;max-height:100%;object-fit:contain"
                                     onerror="this.src='Gambar/logo2.png'">
                            </div>
                            <div>
                                <p style="font-size:12px;color:#888;margin-bottom:8px">Logo saat ini: <code style="color:#aaa;font-size:11px"><?= htmlspecialchars($currentLogo) ?></code></p>
                                <label for="logoFileInput" style="display:inline-flex;align-items:center;gap:8px;padding:8px 18px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);border-radius:8px;cursor:pointer;font-size:13px;color:#ccc;transition:.2s"
                                    onmouseover="this.style.background='rgba(255,255,255,.14)'" onmouseout="this.style.background='rgba(255,255,255,.08)'">
                                    <i class="fas fa-upload"></i> Pilih File Logo
                                </label>
                                <input type="file" id="logoFileInput" accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp" style="display:none">
                            </div>
                        </div>
                        <div id="logoFileName" style="font-size:12px;color:#888;margin-bottom:12px;display:none">
                            <i class="fas fa-file-image" style="margin-right:6px;color:#6fcf97"></i>
                            <span id="logoFileNameText"></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                            <button type="button" id="logoUploadBtn" onclick="uploadLogo()"
                                style="display:none;align-items:center;gap:8px;padding:9px 20px;background:var(--accent-green);border:none;border-radius:8px;color:#fff;font-family:'Poppins',sans-serif;font-size:13px;cursor:pointer;transition:.2s"
                                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                                <i class="fas fa-save"></i> Simpan Logo
                            </button>
                            <span id="logoUploadNote" style="font-size:11px;color:#555;display:none">Pilih file dulu lalu klik Simpan Logo</span>
                        </div>
                    </div>
                </section>

                <!-- Form 2: Email & Notifikasi -->
                <section class="settings-section">
                    <h3><i class="fas fa-bell"></i> Email &amp; Notifikasi</h3>
                    <form method="POST" action="<?= adminUrl('settings') ?>">
                        <input type="hidden" name="form" value="email">
                        <div class="form-group"><label>Alamat Email Admin Utama</label>
                            <input type="email" name="email_kontak" value="<?= cfgVal($cfg,'email_kontak','jelajahinusa@gmail.com') ?>"></div>
                        <div class="form-group"><label>Nama Pengirim Email</label>
                            <input type="text" name="nama_pengirim" value="<?= cfgVal($cfg,'nama_pengirim','JelajahinNusa Info') ?>"></div>
                        <hr class="divider">
                        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> Simpan</button></div>
                    </form>
                </section>

                <!-- Form 3: Media & Upload -->
                <section class="settings-section">
                    <h3><i class="fas fa-images"></i> Media &amp; Batas Upload</h3>
                    <form method="POST" action="<?= adminUrl('settings') ?>">
                        <input type="hidden" name="form" value="media">
                        <div class="form-group"><label>Batas Ukuran File (MB)</label>
                            <input type="number" name="max_upload_size_mb" min="1" value="<?= cfgVal($cfg,'max_upload_size_mb','5') ?>"></div>
                        <div class="form-group"><label>Tipe File yang Diizinkan (pisah koma)</label>
                            <input type="text" name="allowed_file_types" value="<?= cfgVal($cfg,'allowed_file_types','jpg, jpeg, png, webp') ?>"></div>
                        <hr class="divider">
                        <div class="form-group"><label>Lebar Thumbnail Default (px)</label>
                            <input type="number" name="thumb_width" min="50" value="<?= cfgVal($cfg,'thumb_width','150') ?>"></div>
                        <div class="form-group"><label>Tinggi Thumbnail Default (px)</label>
                            <input type="number" name="thumb_height" min="50" value="<?= cfgVal($cfg,'thumb_height','150') ?>"></div>
                        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> Simpan</button></div>
                    </form>
                </section>

                <!-- Form 4: Keamanan & Performa -->
                <section class="settings-section">
                    <h3><i class="fas fa-shield-alt"></i> Keamanan &amp; Performa</h3>
                    <form method="POST" action="<?= adminUrl('settings') ?>">
                        <input type="hidden" name="form" value="keamanan">
                        <div class="form-group switch-group">
                            <label>Mode Pemeliharaan Situs</label>
                            <label class="switch">
                                <input type="checkbox" name="mode_maintenance" <?= ($cfg['mode_maintenance']??'0')==='1'?'checked':'' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="form-group" style="margin-top:10px;"><label>Pesan Mode Pemeliharaan</label>
                            <input type="text" name="maintenance_message" value="<?= cfgVal($cfg,'maintenance_message','Situs sedang dalam pemeliharaan.') ?>"></div>
                        <hr class="divider">
                        <div class="form-group switch-group">
                            <label>Aktifkan Caching Halaman</label>
                            <label class="switch">
                                <input type="checkbox" name="cache_aktif" <?= ($cfg['cache_aktif']??'1')==='1'?'checked':'' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="form-group" style="margin-top:10px;"><label>Durasi Cache (Menit)</label>
                            <input type="number" name="cache_durasi_menit" min="1" value="<?= cfgVal($cfg,'cache_durasi_menit','60') ?>"></div>
                        <div class="form-actions">
                            <button type="submit" name="clear_cache" value="1" class="btn-danger"><i class="fas fa-trash-alt"></i> Bersihkan Cache</button>
                            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Simpan</button>
                        </div>
                    </form>
                </section>

            </div><!-- /.settings-container -->

            <!-- ===== PENGATURAN HALAMAN ARTIKEL ===== -->
            <?php
            $savedFilterSlugs = array_filter(array_map('trim', explode(',', $cfg['artikel_filter_kategori'] ?? '')));
            $savedWilayahJson = $cfg['artikel_filter_wilayah'] ?? '';
            $savedWilayahArr  = $savedWilayahJson ? json_decode($savedWilayahJson, true) : [];
            $defaultWilayah   = empty($savedWilayahArr) ? [
                ['slug'=>'jawa',              'nama'=>'Jawa',                'keywords'=>'Jawa'],
                ['slug'=>'kalimantan',        'nama'=>'Kalimantan',          'keywords'=>'Kalimantan'],
                ['slug'=>'sulawesi',          'nama'=>'Sulawesi',            'keywords'=>'Sulawesi'],
                ['slug'=>'sumatera',          'nama'=>'Sumatra',             'keywords'=>'Sumatra'],
                ['slug'=>'maluku-papua',      'nama'=>'Maluku & Papua',      'keywords'=>'Maluku,Papua'],
                ['slug'=>'bali-nusatenggara', 'nama'=>'Bali & Nusa Tenggara','keywords'=>'Bali,NTT,NTB,Nusa Tenggara'],
            ] : $savedWilayahArr;
            ?>
            <div style="margin-top:30px;display:grid;grid-template-columns:1fr 1fr;gap:30px">

                <!-- Panel Kategori -->
                <section class="settings-section">
                    <h3><i class="fas fa-tag"></i> Filter Kategori Artikel</h3>
                    <p style="font-size:13px;color:#888;margin-bottom:20px;margin-top:-10px">Pilih kategori yang tampil di dropdown filter halaman Artikel. Kosongkan semua = tampilkan seluruh kategori.</p>
                    <form method="POST" action="<?= adminUrl('settings') ?>">
                        <input type="hidden" name="form" value="landing">
                        <div style="display:flex;flex-direction:column;gap:8px">
                            <?php foreach ($allKategoriForFilter as $kat):
                                $chk = empty($savedFilterSlugs) || in_array($kat['slug'], $savedFilterSlugs); ?>
                            <label class="kat-toggle-row <?= $chk ? 'on' : '' ?>">
                                <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0">
                                    <i class="fas fa-grip-lines" style="color:#444;font-size:12px;flex-shrink:0"></i>
                                    <span style="font-size:13px;color:#ddd;font-weight:500"><?= htmlspecialchars($kat['nama']) ?></span>
                                    <code style="font-size:10px;color:#666;background:rgba(255,255,255,.06);padding:2px 7px;border-radius:4px;margin-left:auto;flex-shrink:0"><?= htmlspecialchars($kat['slug']) ?></code>
                                </div>
                                <label class="switch" style="flex-shrink:0;margin-left:12px">
                                    <input type="checkbox" name="filter_kategori_slugs[]" value="<?= htmlspecialchars($kat['slug']) ?>"
                                        <?= $chk ? 'checked' : '' ?>
                                        onchange="this.closest('.kat-toggle-row').classList.toggle('on', this.checked)">
                                    <span class="slider"></span>
                                </label>
                            </label>
                            <?php endforeach; ?>
                            <?php if (empty($allKategoriForFilter)): ?>
                            <p style="color:#888;font-size:13px;text-align:center;padding:16px 0">Belum ada kategori di database.</p>
                            <?php endif; ?>
                        </div>
                        <p style="color:#666;font-size:11px;margin-top:14px"><i class="fas fa-info-circle" style="margin-right:4px"></i>Opsi <strong style="color:#888">Lainnya</strong> selalu ditambahkan otomatis.</p>
                        <div class="form-actions">
                            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Simpan Kategori</button>
                        </div>
                    </form>
                </section>

                <!-- Panel Wilayah -->
                <section class="settings-section">
                    <h3><i class="fas fa-map-marker-alt"></i> Filter Wilayah Artikel</h3>
                    <p style="font-size:13px;color:#888;margin-bottom:20px;margin-top:-10px">Kelola daftar wilayah di dropdown filter halaman Artikel.</p>
                    <form method="POST" action="<?= adminUrl('settings') ?>">
                        <input type="hidden" name="form" value="wilayah">
                        <div id="wilayahList" style="display:flex;flex-direction:column;gap:8px">
                            <?php foreach ($defaultWilayah as $w): ?>
                            <div class="wilayah-row">
                                <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:0">
                                    <input type="text" placeholder="slug (tanpa spasi)" value="<?= htmlspecialchars($w['slug']) ?>"
                                        class="w-slug" style="width:140px;flex-shrink:0;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:7px;padding:8px 10px;color:#fff;font-family:'Poppins',sans-serif;font-size:12px;outline:none">
                                    <input type="text" placeholder="Nama tampilan" value="<?= htmlspecialchars($w['nama']) ?>"
                                        class="w-nama" style="flex:1;min-width:0;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:7px;padding:8px 10px;color:#fff;font-family:'Poppins',sans-serif;font-size:12px;outline:none">
                                    <button type="button" onclick="removeWilayah(this)" style="flex-shrink:0;background:rgba(231,76,60,.12);border:1px solid rgba(231,76,60,.3);color:#e74c3c;border-radius:7px;padding:7px 10px;cursor:pointer;line-height:1"><i class="fas fa-trash-alt" style="font-size:12px"></i></button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addWilayahRow()" style="margin-top:10px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.15);color:#ccc;border-radius:8px;padding:7px 16px;cursor:pointer;font-size:12px;font-family:'Poppins',sans-serif"><i class="fas fa-plus" style="margin-right:6px"></i>Tambah Wilayah</button>
                        <input type="hidden" name="wilayah_json" id="wilayahJson">
                        <div class="form-actions">
                            <button type="submit" class="btn-save" onclick="prepareWilayah()"><i class="fas fa-save"></i> Simpan Wilayah</button>
                        </div>
                    </form>
                </section>

            </div><!-- /.grid -->

            <style>
                .kat-toggle-row {
                    display:flex; align-items:center; padding:10px 14px; border-radius:9px;
                    border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.03);
                    cursor:pointer; transition:.15s;
                }
                .kat-toggle-row.on { border-color:rgba(111,207,151,.35); background:rgba(111,207,151,.06); }
                .wilayah-row { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.08); border-radius:9px; padding:10px 12px; }
                .wilayah-row input:focus { border-color:rgba(111,207,151,.5) !important; }
            </style>

            <script>
                // ---- Wilayah manager ----
                function addWilayahRow() {
                    const row = document.createElement('div');
                    row.className = 'wilayah-row';
                    row.innerHTML = `<div style="display:flex;align-items:center;gap:6px;flex:1;min-width:0">
                        <input type="text" placeholder="slug" class="w-slug" style="width:140px;flex-shrink:0;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:7px;padding:8px 10px;color:#fff;font-family:'Poppins',sans-serif;font-size:12px;outline:none">
                        <input type="text" placeholder="Nama tampilan" class="w-nama" style="flex:1;min-width:0;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:7px;padding:8px 10px;color:#fff;font-family:'Poppins',sans-serif;font-size:12px;outline:none">
                        <button type="button" onclick="removeWilayah(this)" style="flex-shrink:0;background:rgba(231,76,60,.12);border:1px solid rgba(231,76,60,.3);color:#e74c3c;border-radius:7px;padding:7px 10px;cursor:pointer;line-height:1"><i class="fas fa-trash-alt" style="font-size:12px"></i></button>
                    </div>`;
                    document.getElementById('wilayahList').appendChild(row);
                }
                function removeWilayah(btn) {
                    btn.closest('.wilayah-row').remove();
                }
                function prepareWilayah() {
                    const rows = [...document.querySelectorAll('.wilayah-row')].map(r => ({
                        slug:     r.querySelector('.w-slug').value.trim().toLowerCase().replace(/\s+/g,'-'),
                        nama:     r.querySelector('.w-nama').value.trim(),
                        keywords: r.querySelector('.w-nama').value.trim(), // keywords = nama otomatis
                    })).filter(r => r.slug && r.nama);
                    document.getElementById('wilayahJson').value = JSON.stringify(rows);
                }

                document.addEventListener('DOMContentLoaded', () => {
                    const toast = document.getElementById('toast');
                    if (toast && toast.classList.contains('show')) {
                        setTimeout(() => toast.classList.remove('show'), 3500);
                    }

                    // ── Logo upload preview ──
                    const logoInput = document.getElementById('logoFileInput');
                    const logoBtn   = document.getElementById('logoUploadBtn');
                    const logoFn    = document.getElementById('logoFileName');
                    const logoFnTxt = document.getElementById('logoFileNameText');
                    const logoPreview = document.getElementById('logoPreviewImg');

                    if (logoInput) {
                        logoInput.addEventListener('change', function() {
                            if (!this.files || !this.files[0]) return;
                            const f = this.files[0];
                            logoFnTxt.textContent = f.name + ' (' + (f.size/1024).toFixed(1) + ' KB)';
                            logoFn.style.display = 'block';
                            logoBtn.style.display = 'inline-flex';
                            const note = document.getElementById('logoUploadNote');
                            if (note) note.style.display = 'none';
                            // Preview lokal
                            const reader = new FileReader();
                            reader.onload = e => logoPreview.src = e.target.result;
                            reader.readAsDataURL(f);
                        });
                        // Tampilkan note saat pertama load
                        const note = document.getElementById('logoUploadNote');
                        if (note) note.style.display = 'inline';
                    }
                });

                window.uploadLogo = async function() {
                    const logoInput = document.getElementById('logoFileInput');
                    if (!logoInput || !logoInput.files[0]) return;
                    const btn = document.getElementById('logoUploadBtn');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengupload...';
                    try {
                        const fd = new FormData();
                        fd.append('logo', logoInput.files[0]);
                        const j = await (await fetch('dashboardAdmin.php?mode=api&action=upload_logo', {method:'POST', body:fd})).json();
                        if (j.success) {
                            document.getElementById('logoPreviewImg').src = j.data.path + '?t=' + Date.now();
                            showSettingsToast('Logo berhasil diperbarui!');
                            document.getElementById('logoFileName').style.display = 'none';
                            btn.style.display = 'none';
                            const note = document.getElementById('logoUploadNote');
                            if (note) note.style.display = 'inline';
                            logoInput.value = '';
                        } else {
                            showSettingsToast(j.message || 'Gagal upload logo.', true);
                        }
                    } catch(e) {
                        showSettingsToast('Error: ' + e.message, true);
                    }
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Simpan Logo';
                };

                function showSettingsToast(msg, isErr=false) {
                    const t = document.getElementById('toast');
                    if (!t) return;
                    t.textContent = msg;
                    t.className = 'toast show' + (isErr ? ' error' : '');
                    setTimeout(() => t.className = 'toast', 3500);
                }
            </script>

            <?php elseif ($mode === 'galeri_kontribusi'): ?>

            <!-- Stats di luar card, konsisten dengan halaman lain -->
            <div class="stats-row cols4">
                <div class="stat-box active" id="gc-stat-all"      onclick="gcSetFilter('all')">      <div class="num" id="gc-num-all">-</div><div class="label">Semua Foto</div></div>
                <div class="stat-box"         id="gc-stat-pending"  onclick="gcSetFilter('pending')">  <div class="num pending-num"  id="gc-num-pending">-</div><div class="label">Menunggu</div></div>
                <div class="stat-box"         id="gc-stat-approved" onclick="gcSetFilter('approved')"> <div class="num approved-num" id="gc-num-approved">-</div><div class="label">Disetujui</div></div>
                <div class="stat-box"         id="gc-stat-rejected" onclick="gcSetFilter('rejected')"> <div class="num rejected-num" id="gc-num-rejected">-</div><div class="label">Ditolak</div></div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <h3>Manajemen Foto Kontribusi</h3>
                </div>
                <!-- Sort & perpage toolbar -->
                <div class="search-filter" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px">
                    <input type="hidden" id="gcSearch">
                    <select id="gcPerPage" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:9px 14px;color:#fff;font-family:'Poppins',sans-serif;outline:none">
                        <option value="10">10 / hal</option><option value="25" selected>25 / hal</option><option value="50">50 / hal</option><option value="100">100 / hal</option>
                    </select>
                </div>
                <!-- Table layout mirip artikel -->
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px">No</th>
                            <th style="width:70px">Foto</th>
                            <th onclick="gcSortBy('judul')" style="cursor:pointer;user-select:none">Judul Foto <span id="gcsort-judul" style="font-size:10px;color:#666">⇅</span></th>
                            <th onclick="gcSortBy('artikel')" style="cursor:pointer;user-select:none">Artikel <span id="gcsort-artikel" style="font-size:10px;color:#666">⇅</span></th>
                            <th onclick="gcSortBy('user')" style="cursor:pointer;user-select:none">Pengguna <span id="gcsort-user" style="font-size:10px;color:#666">⇅</span></th>
                            <th onclick="gcSortBy('date')" style="cursor:pointer;user-select:none">Tanggal <span id="gcsort-date" style="font-size:10px;color:#aaa">↓</span></th>
                            <th onclick="gcSortBy('status')" style="cursor:pointer;user-select:none">Status <span id="gcsort-status" style="font-size:10px;color:#666">⇅</span></th>
                            <th style="width:110px">Aksi</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="gcTableBody">
                        <tr><td colspan="9" style="text-align:center;color:#aaa;padding:30px">Memuat data...</td></tr>
                    </tbody>
                </table>
                <div class="pagination">
                    <span id="gcPaginInfo">-</span>
                    <button id="gcPrev"><i class="fas fa-chevron-left"></i></button>
                    <span id="gcPageNum">1</span>
                    <button id="gcNext"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>

            <!-- Modal Kelola Galeri Artikel -->

            <script>
            (function(){
                let allGC = [], gcFilter = 'all';
                let gcPage = 1, gcPerPage = 25;
                let gcFiltered = [];
                let gcSortCol = 'date', gcSortDir = 'desc';
                const API_URL = 'dashboardAdmin.php?mode=api&action=';

                async function loadGC() {
                    try {
                        const r = await fetch(API_URL + 'get_galeri_kontribusi');
                        const j = await r.json();
                        if (!j.success) {
                            document.getElementById('gcTableBody').innerHTML = '<tr><td colspan="9" style="text-align:center;color:#e74c3c;padding:30px">Error: ' + (j.message || 'Gagal') + '</td></tr>';
                            return;
                        }
                        allGC = j.data || [];
                        updateStats();
                        gcApplyFilter();
                    } catch(e) {
                        document.getElementById('gcTableBody').innerHTML = '<tr><td colspan="9" style="text-align:center;color:#e74c3c;padding:30px">Gagal koneksi API.</td></tr>';
                    }
                }

                function updateStats() {
                    const c = {all: allGC.length, pending:0, approved:0, rejected:0};
                    allGC.forEach(g => { if (c[g.status] !== undefined) c[g.status]++; });
                    document.getElementById('gc-num-all').textContent      = c.all;
                    document.getElementById('gc-num-pending').textContent  = c.pending;
                    document.getElementById('gc-num-approved').textContent = c.approved;
                    document.getElementById('gc-num-rejected').textContent = c.rejected;
                }

                window.gcSetFilter = function(f) {
                    gcFilter = f;
                    ['all','pending','approved','rejected'].forEach(s =>
                        document.getElementById('gc-stat-' + s).classList.toggle('active', s === f)
                    );
                    gcApplyFilter();
                };

                function gcApplyFilter() {
                    const q = document.getElementById('gcSearch').value.toLowerCase();
                    gcPerPage = parseInt(document.getElementById('gcPerPage').value);
                    gcFiltered = allGC.filter(g =>
                        (gcFilter === 'all' || g.status === gcFilter) &&
                        (!q || (g.judul||'').toLowerCase().includes(q) ||
                               (g.username||'').toLowerCase().includes(q) ||
                               (g.judul_artikel||'').toLowerCase().includes(q))
                    );
                    gcFiltered.sort((a,b) => {
                        let va, vb;
                        if (gcSortCol==='date')   { va=a.created_at||''; vb=b.created_at||''; }
                        else if (gcSortCol==='judul')  { va=(a.judul||'').toLowerCase(); vb=(b.judul||'').toLowerCase(); }
                        else if (gcSortCol==='user')   { va=(a.username||'').toLowerCase(); vb=(b.username||'').toLowerCase(); }
                        else if (gcSortCol==='artikel') { va=(a.judul_artikel||'').toLowerCase(); vb=(b.judul_artikel||'').toLowerCase(); }
                        else if (gcSortCol==='status') { va=a.status||''; vb=b.status||''; }
                        else { va=''; vb=''; }
                        if (va < vb) return gcSortDir==='asc'?-1:1;
                        if (va > vb) return gcSortDir==='asc'?1:-1;
                        return 0;
                    });
                    gcPage = 1; renderGCTable();
                }

                window.gcSortBy = function(col) {
                    if (gcSortCol === col) gcSortDir = gcSortDir==='asc'?'desc':'asc';
                    else { gcSortCol = col; gcSortDir = col==='date'?'desc':'asc'; }
                    ['judul','artikel','user','date','status'].forEach(c => {
                        const el = document.getElementById('gcsort-'+c);
                        if (!el) return;
                        el.textContent = c===gcSortCol ? (gcSortDir==='asc'?'↑':'↓') : '⇅';
                        el.style.color  = c===gcSortCol ? '#aaa' : '#666';
                    });
                    gcApplyFilter();
                };

                function renderGCTable() {
                    const el = document.getElementById('gcTableBody');
                    const totalPages = Math.max(1, Math.ceil(gcFiltered.length / gcPerPage));
                    if (gcPage > totalPages) gcPage = totalPages;
                    const start = (gcPage-1)*gcPerPage;
                    const slice = gcFiltered.slice(start, start+gcPerPage);

                    if (!slice.length) {
                        el.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#aaa;padding:30px">Tidak ada data.</td></tr>';
                    } else {
                        const statusLabel = { pending:'Menunggu', approved:'Disetujui', rejected:'Ditolak' };
                        const statusColor = { pending:'#F39C12', approved:'#27AE60', rejected:'#C0392B' };
                        el.innerHTML = slice.map((g, i) => {
                            const icoApprove = g.status !== 'approved'
                                ? `<i class="fas fa-check gc-ico-approve" title="Setujui" data-id="${g.id}"></i>` : '';
                            const icoReject  = g.status !== 'rejected'
                                ? `<i class="fas fa-xmark gc-ico-reject" title="Tolak" data-id="${g.id}"></i>` : '';
                            const icoPreview = `<i class="fas fa-eye gc-ico-preview" title="Preview Foto" data-src="${encodeURIComponent(g.image_path||'')}" data-caption="${encodeURIComponent(g.judul||'')}" data-artikel-id="${g.artikel_id||''}"></i>`;
                            const icoDelete  = `<i class="fas fa-trash-alt gc-ico-delete" title="Hapus" data-id="${g.id}"></i>`;
                            return `<tr>
                                <td>${start+i+1}</td>
                                <td><img src="${g.image_path||''}" alt="" style="width:52px;height:52px;object-fit:cover;border-radius:8px;cursor:pointer"
                                    class="gc-ico-preview" data-src="${encodeURIComponent(g.image_path||'')}" data-caption="${encodeURIComponent(g.judul||'')}" data-artikel-id="${g.artikel_id||''}"
                                    onerror="this.src='Gambar/logo2.png'"></td>
                                <td style="font-size:13px;font-weight:500;max-width:200px">${(g.judul||'-').replace(/</g,'&lt;')}</td>
                                <td style="font-size:12px;color:#aaa;max-width:180px">${(g.judul_artikel||'-').replace(/</g,'&lt;')}</td>
                                <td style="font-size:12px;color:#ccc">${(g.username||'-').replace(/</g,'&lt;')}</td>
                                <td style="font-size:12px;color:#666">${g.tanggal||'-'}</td>
                                <td><span style="font-size:11px;font-weight:600;color:${statusColor[g.status]||'#aaa'};background:${statusColor[g.status]||'#aaa'}22;padding:3px 10px;border-radius:20px;">${statusLabel[g.status]||g.status}</span></td>
                                <td class="action-btns">${icoApprove}${icoReject}${icoPreview}</td>
                                <td class="action-btns">${icoDelete}</td>
                            </tr>`;
                        }).join('');
                    }

                    document.getElementById('gcPaginInfo').textContent = `${gcFiltered.length} foto`;
                    document.getElementById('gcPageNum').textContent = `${gcPage} / ${totalPages}`;
                    document.getElementById('gcPrev').disabled = gcPage <= 1;
                    document.getElementById('gcNext').disabled = gcPage >= totalPages;
                }

                // ── Event delegation — satu listener untuk semua aksi di tabel ──
                document.getElementById('gcTableBody').addEventListener('click', async function(e) {
                    const el = e.target.closest('i, img');
                    if (!el) return;

                    // Preview foto
                    if (el.classList.contains('gc-ico-preview')) {
                        openFotoLightbox(el.dataset.src || '', el.dataset.caption || '', el.dataset.artikelId || '');
                        return;
                    }

                    // Approve
                    if (el.classList.contains('gc-ico-approve')) {
                        const id = el.dataset.id;
                        const fd = new FormData();
                        fd.append('id', id); fd.append('status', 'approved');
                        const j = await (await fetch(API_URL + 'update_galeri_kontribusi_status', {method:'POST', body:fd})).json();
                        if (j.success) {
                            const g = allGC.find(x => x.id == id);
                            if (g) g.status = 'approved';
                            updateStats(); gcApplyFilter();
                            const t = document.getElementById('toast');
                            t.textContent = 'Foto disetujui dan tampil di artikel.';
                            t.className = 'toast show';
                            setTimeout(() => t.className = 'toast', 3000);
                        } else { alert('Gagal: ' + (j.message || '')); }
                        return;
                    }

                    // Tolak
                    if (el.classList.contains('gc-ico-reject')) {
                        const id = el.dataset.id;
                        if (!await showConfirm('Tolak Foto', 'Status foto akan berubah menjadi Ditolak.', '✕', 'Ya, Tolak')) return;
                        const fd = new FormData();
                        fd.append('id', id); fd.append('status', 'rejected');
                        const j = await (await fetch(API_URL + 'update_galeri_kontribusi_status', {method:'POST', body:fd})).json();
                        if (j.success) {
                            const g = allGC.find(x => x.id == id);
                            if (g) g.status = 'rejected';
                            updateStats(); gcApplyFilter();
                            const t = document.getElementById('toast');
                            t.textContent = 'Foto ditolak.';
                            t.className = 'toast show';
                            setTimeout(() => t.className = 'toast', 3000);
                        } else { alert('Gagal: ' + (j.message || '')); }
                        return;
                    }

                    // Hapus
                    if (el.classList.contains('gc-ico-delete')) {
                        const id = el.dataset.id;
                        if (!await showConfirm('Hapus Foto', 'Tindakan ini tidak bisa dibatalkan.', '🗑️', 'Ya, Hapus')) return;
                        const fd = new FormData();
                        fd.append('id', id);
                        const j = await (await fetch(API_URL + 'delete_galeri_kontribusi', {method:'POST', body:fd})).json();
                        if (j.success) {
                            allGC = allGC.filter(x => x.id != id);
                            updateStats(); gcApplyFilter();
                            const t = document.getElementById('toast');
                            t.textContent = 'Foto berhasil dihapus.';
                            t.className = 'toast show';
                            setTimeout(() => t.className = 'toast', 3000);
                        } else { alert('Gagal hapus: ' + (j.message || '')); }
                    }
                });

                document.getElementById('gcSearch').addEventListener('input', gcApplyFilter);
                document.getElementById('gcPerPage').addEventListener('change', gcApplyFilter);
                document.getElementById('gcPrev').addEventListener('click', ()=>{ if(gcPage>1){gcPage--;renderGCTable();} });
                document.getElementById('gcNext').addEventListener('click', ()=>{ gcPage++;renderGCTable(); });
                document.getElementById('globalSearch').addEventListener('input', function(){
                    document.getElementById('gcSearch').value = this.value;
                    gcApplyFilter();
                });
                loadGC();
            })();
            </script>

            <?php elseif ($mode === 'usul_user'): ?>
            <?php
            // Ambil data usul dari DB
            $usuls = [];
            try {
                $usuls = $pdo->query("
                    SELECT ua.id, ua.pesan, ua.created_at,
                           COALESCE(u.username, '—') AS username
                    FROM usul_artikel ua
                    LEFT JOIN users u ON ua.user_id = u.id
                    ORDER BY ua.created_at DESC
                ")->fetchAll();
            } catch (\Throwable $e) { /* tabel belum ada */ }
            ?>

            <!-- ===== USUL USER ===== -->
            <?php
            $usulTotal    = count($usuls);
            $usulHariIni  = count(array_filter($usuls, fn($u) => date('Y-m-d', strtotime($u['created_at'])) === date('Y-m-d')));
            $usulBulanIni = count(array_filter($usuls, fn($u) => date('Y-m', strtotime($u['created_at'])) === date('Y-m')));
            ?>
            <div class="stats-row">
                <div class="stat-box active" id="usulStat-all"   onclick="usulSetFilter('all')">    <div class="num"><?= $usulTotal ?></div><div class="label">Total Usulan</div></div>
                <div class="stat-box"         id="usulStat-today" onclick="usulSetFilter('today')">  <div class="num" style="color:#6fcf97"><?= $usulHariIni ?></div><div class="label">Hari Ini</div></div>
                <div class="stat-box"         id="usulStat-month" onclick="usulSetFilter('month')">  <div class="num" style="color:#F39C12"><?= $usulBulanIni ?></div><div class="label">Bulan Ini</div></div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <h3>Usul User</h3>
                    <p style="color:var(--text-secondary);font-size:13px;margin-top:4px">Daftar saran artikel yang dikirimkan oleh pengguna.</p>
                </div>
                <div class="search-filter" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px">
                    <input type="hidden" id="usulSearch">
                    <select id="usulPerPage" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:9px 14px;color:#fff;font-family:'Poppins',sans-serif;outline:none">
                        <option value="10" selected>10 / hal</option><option value="25">25 / hal</option><option value="50">50 / hal</option><option value="100">100 / hal</option>
                    </select>
                </div>
                <table style="margin-top:12px">
                    <thead>
                        <tr>
                            <th style="width:40px">No</th>
                            <th onclick="usulSortBy('pesan')" style="cursor:pointer;user-select:none">Pesan Usulan <span id="usulsort-pesan" style="font-size:10px;color:#666">⇅</span></th>
                            <th onclick="usulSortBy('user')" style="width:160px;cursor:pointer;user-select:none">Pengguna <span id="usulsort-user" style="font-size:10px;color:#666">⇅</span></th>
                            <th onclick="usulSortBy('date')" style="width:140px;cursor:pointer;user-select:none">Tanggal <span id="usulsort-date" style="font-size:10px;color:#aaa">↓</span></th>
                            <th style="width:80px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="usulTableBody">
                        <tr><td colspan="5" style="text-align:center;color:#aaa;padding:30px">Memuat...</td></tr>
                    </tbody>
                </table>
                <div class="pagination">
                    <span id="usulPaginInfo">-</span>
                    <button id="usulPrev"><i class="fas fa-chevron-left"></i></button>
                    <span id="usulPageNum">1</span>
                    <button id="usulNext"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>

            <script>
            (function(){
                const usulData = <?= json_encode(array_map(fn($u) => [
                    'id'         => $u['id'],
                    'pesan'      => $u['pesan'],
                    'username'   => $u['username'],
                    'created_at' => $u['created_at'],
                    'tanggal'    => date('d M Y H:i', strtotime($u['created_at'])),
                ], $usuls), JSON_UNESCAPED_UNICODE) ?>;

                let usulFiltered = [...usulData];
                let usulPage = 1, usulPerPage = 10;
                let usulDateFilter = 'all';
                let usulSortCol = 'date', usulSortDir = 'desc';
                const API_USUL = 'dashboardAdmin.php?mode=api&action=';
                const todayStr = new Date().toISOString().slice(0,10);
                const monthStr = new Date().toISOString().slice(0,7);

                function showUsulToast(msg) {
                    const t = document.getElementById('toast');
                    if (t) { t.textContent = msg; t.classList.add('show'); setTimeout(() => t.classList.remove('show'), 3000); }
                    else alert(msg);
                }

                function usulSetFilter(f) {
                    usulDateFilter = f;
                    ['all','today','month'].forEach(s =>
                        document.getElementById('usulStat-'+s)?.classList.toggle('active', s === f)
                    );
                    usulPage = 1; usulApplyFilter();
                }

                function usulApplyFilter() {
                    const q = document.getElementById('usulSearch').value.toLowerCase();
                    usulPerPage = parseInt(document.getElementById('usulPerPage').value);
                    usulFiltered = usulData.filter(u => {
                        const matchQ = !q || u.pesan.toLowerCase().includes(q) || u.username.toLowerCase().includes(q);
                        const tgl = u.created_at.slice(0,10);
                        const matchDate = usulDateFilter === 'today' ? tgl === todayStr
                                        : usulDateFilter === 'month' ? tgl.slice(0,7) === monthStr
                                        : true;
                        return matchQ && matchDate;
                    });
                    usulFiltered.sort((a,b) => {
                        let va, vb;
                        if      (usulSortCol==='date')  { va=a.created_at||''; vb=b.created_at||''; }
                        else if (usulSortCol==='user')  { va=(a.username||'').toLowerCase(); vb=(b.username||'').toLowerCase(); }
                        else if (usulSortCol==='pesan') { va=(a.pesan||'').toLowerCase(); vb=(b.pesan||'').toLowerCase(); }
                        else { va=''; vb=''; }
                        if (va < vb) return usulSortDir==='asc'?-1:1;
                        if (va > vb) return usulSortDir==='asc'?1:-1;
                        return 0;
                    });
                    usulPage = 1;
                    renderUsul();
                }

                window.usulSortBy = function(col) {
                    if (usulSortCol === col) usulSortDir = usulSortDir==='asc'?'desc':'asc';
                    else { usulSortCol = col; usulSortDir = col==='date'?'desc':'asc'; }
                    ['pesan','user','date'].forEach(c => {
                        const el = document.getElementById('usulsort-'+c);
                        if (!el) return;
                        el.textContent = c===usulSortCol ? (usulSortDir==='asc'?'\u2191':'\u2193') : '\u21c5';
                        el.style.color  = c===usulSortCol ? '#aaa' : '#666';
                    });
                    usulApplyFilter();
                };

                function renderUsul() {
                    const tbody = document.getElementById('usulTableBody');
                    const totalPages = Math.max(1, Math.ceil(usulFiltered.length / usulPerPage));
                    if (usulPage > totalPages) usulPage = totalPages;
                    const start = (usulPage-1)*usulPerPage;
                    const slice = usulFiltered.slice(start, start+usulPerPage);
                    if (!slice.length) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#aaa;padding:30px">Tidak ada usulan.</td></tr>';
                    } else {
                        tbody.innerHTML = slice.map((u, i) => `<tr>
                            <td style="color:#555">${start+i+1}</td>
                            <td style="font-size:13px">${u.pesan.replace(/</g,'&lt;')}</td>
                            <td style="color:var(--text-secondary);font-size:13px">${u.username.replace(/</g,'&lt;')}</td>
                            <td style="color:#555;font-size:12px">${u.tanggal}</td>
                            <td class="action-btns"><i class="fas fa-trash-alt" onclick="deleteUsulRow(${u.id}, this)" title="Hapus"></i></td>
                        </tr>`).join('');
                    }
                    document.getElementById('usulPaginInfo').textContent = `${usulFiltered.length} usulan`;
                    document.getElementById('usulPageNum').textContent = `${usulPage} / ${totalPages}`;
                    document.getElementById('usulPrev').disabled = usulPage <= 1;
                    document.getElementById('usulNext').disabled = usulPage >= totalPages;
                }

                window.deleteUsulRow = async function(id, btn) {
                    if (!await showConfirm('Hapus Usulan', 'Tindakan ini tidak bisa dibatalkan.', '🗑️', 'Ya, Hapus')) return;
                    const form = new FormData();
                    form.append('id', id);
                    const j = await (await fetch(API_USUL + 'delete_usul', { method: 'POST', body: form })).json();
                    if (j.success) {
                        const idx = usulData.findIndex(u => u.id == id);
                        if (idx !== -1) usulData.splice(idx, 1);
                        usulApplyFilter();
                        showUsulToast('Usulan berhasil dihapus.');
                    } else {
                        showUsulToast(j.message || 'Gagal menghapus.');
                    }
                };

                document.getElementById('usulPerPage').addEventListener('change', usulApplyFilter);
                document.getElementById('usulPrev').addEventListener('click', ()=>{ if(usulPage>1){usulPage--;renderUsul();} });
                document.getElementById('usulNext').addEventListener('click', ()=>{ usulPage++;renderUsul(); });
                document.getElementById('globalSearch').addEventListener('input', function(){
                    document.getElementById('usulSearch').value = this.value;
                    usulApplyFilter();
                });

                renderUsul();
            })();
            </script>

            <?php elseif ($mode === 'broadcast'): ?>

            <!-- ===== MODE: KIRIM NOTIFIKASI ===== -->
            <div class="admin-title" style="font-size:26px;font-weight:600;margin-bottom:24px">
                <i class="fas fa-bell" style="color:var(--accent-green);margin-right:10px"></i>Kirim Notifikasi ke Pengguna
            </div>

            <!-- Form Kirim -->
            <div class="section-card" style="margin-bottom:28px">
                <h3 style="font-size:17px;font-weight:600;margin-bottom:20px">📢 Buat Notifikasi Baru</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Judul Notifikasi</label>
                        <input type="text" id="bcJudul" placeholder="Contoh: Fitur baru telah hadir!" class="form-group input" style="width:100%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:10px 14px;color:#fff;font-family:'Poppins',sans-serif;outline:none">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Isi Pesan</label>
                        <textarea id="bcPesan" rows="4" placeholder="Tulis pesan notifikasi di sini..." style="width:100%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:10px 14px;color:#fff;font-family:'Poppins',sans-serif;outline:none;resize:vertical"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Tipe Notifikasi</label>
                        <select id="bcTipe" style="width:100%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:10px 14px;color:#fff;font-family:'Poppins',sans-serif;outline:none">
                            <option value="info">📢 Info Umum</option>
                            <option value="artikel_baru">📰 Artikel Baru</option>
                            <option value="sistem">⚙️ Update Sistem</option>
                            <option value="populer">🔥 Konten Populer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kirim ke</label>
                        <select id="bcTarget" style="width:100%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:10px 14px;color:#fff;font-family:'Poppins',sans-serif;outline:none">
                            <option value="semua">👥 Semua Pengguna Aktif</option>
                            <option value="notif_sistem">⚙️ Yang aktifkan Notif Sistem</option>
                            <option value="notif_artikel_baru">📰 Yang aktifkan Notif Artikel Baru</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:14px;margin-top:18px">
                    <button onclick="sendBroadcast()" id="bcBtn"
                        style="background:var(--accent-green);color:#fff;border:none;padding:11px 28px;border-radius:8px;cursor:pointer;font-weight:600;font-family:'Poppins',sans-serif;font-size:14px;display:flex;align-items:center;gap:8px;transition:.2s">
                        <i class="fas fa-paper-plane"></i> Kirim Sekarang
                    </button>
                    <span id="bcStatus" style="font-size:13px;color:#aaa"></span>
                </div>
            </div>

            <!-- Riwayat Broadcast -->
            <div class="section-card">
                <div class="section-header">
                    <h3 style="font-size:17px;font-weight:600">🕓 Riwayat Broadcast</h3>
                    <button onclick="loadBroadcasts()" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:8px 16px;color:#ccc;cursor:pointer;font-size:13px;font-family:'Poppins',sans-serif">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <table style="margin-top:8px">
                    <thead>
                        <tr>
                            <th style="width:40px">No</th>
                            <th>Judul</th>
                            <th style="width:110px">Tipe</th>
                            <th style="width:160px">Target</th>
                            <th style="width:80px;text-align:center">Terkirim</th>
                            <th style="width:140px">Waktu</th>
                            <th style="width:60px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="bcTableBody">
                        <tr><td colspan="7" style="text-align:center;color:#aaa;padding:30px">Memuat...</td></tr>
                    </tbody>
                </table>
            </div>

            <script>
            (function(){
                const API_BC = 'dashboardAdmin.php?mode=api&action=';

                const tipeLabel = {
                    info:         '📢 Info',
                    artikel_baru: '📰 Artikel Baru',
                    sistem:       '⚙️ Sistem',
                    populer:      '🔥 Populer',
                };
                const targetLabel = {
                    semua:              '👥 Semua User',
                    notif_sistem:       '⚙️ Notif Sistem',
                    notif_artikel_baru: '📰 Notif Artikel',
                };

                async function loadBroadcasts() {
                    const tbody = document.getElementById('bcTableBody');
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#aaa;padding:20px">Memuat...</td></tr>';
                    try {
                        const j = await (await fetch(API_BC + 'get_broadcasts')).json();
                        if (!j.success || !j.data.length) {
                            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#aaa;padding:30px">Belum ada riwayat broadcast.</td></tr>';
                            return;
                        }
                        tbody.innerHTML = j.data.map((b, i) => `<tr>
                            <td style="color:#555">${i+1}</td>
                            <td style="font-weight:500;max-width:260px">${b.judul.replace(/</g,'&lt;')}</td>
                            <td style="font-size:12px">${tipeLabel[b.tipe] || b.tipe}</td>
                            <td style="font-size:12px;color:#aaa">${targetLabel[b.target] || b.target}</td>
                            <td style="text-align:center;font-weight:600;color:var(--accent-green)">${b.total_kirim}</td>
                            <td style="font-size:12px;color:#666">${b.tanggal}</td>
                            <td class="action-btns">
                                <i class="fas fa-trash-alt" title="Hapus" data-id="${b.id}" onclick="deleteBroadcast(${b.id})"></i>
                            </td>
                        </tr>`).join('');
                    } catch(e) {
                        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#e74c3c;padding:20px">Gagal memuat data.</td></tr>';
                    }
                }

                window.sendBroadcast = async function() {
                    const judul  = document.getElementById('bcJudul').value.trim();
                    const pesan  = document.getElementById('bcPesan').value.trim();
                    const tipe   = document.getElementById('bcTipe').value;
                    const target = document.getElementById('bcTarget').value;
                    const status = document.getElementById('bcStatus');
                    const btn    = document.getElementById('bcBtn');

                    if (!judul || !pesan) {
                        status.textContent = '⚠️ Judul dan pesan tidak boleh kosong.';
                        status.style.color = '#e74c3c';
                        return;
                    }

                    if (!await showConfirm(
                        'Kirim Notifikasi',
                        `Notifikasi "${judul}" akan dikirim ke: ${targetLabel[target]}. Lanjutkan?`,
                        '📢', 'Ya, Kirim'
                    )) return;

                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
                    status.textContent = '';

                    const fd = new FormData();
                    fd.append('judul', judul);
                    fd.append('pesan', pesan);
                    fd.append('tipe', tipe);
                    fd.append('target', target);

                    try {
                        const j = await (await fetch(API_BC + 'send_broadcast', {method:'POST', body:fd})).json();
                        if (j.success) {
                            status.textContent = '✅ ' + j.message;
                            status.style.color = '#27ae60';
                            document.getElementById('bcJudul').value = '';
                            document.getElementById('bcPesan').value = '';
                            loadBroadcasts();
                        } else {
                            status.textContent = '❌ ' + (j.message || 'Gagal mengirim.');
                            status.style.color = '#e74c3c';
                        }
                    } catch(e) {
                        status.textContent = '❌ Koneksi gagal.';
                        status.style.color = '#e74c3c';
                    }

                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Sekarang';
                };

                window.deleteBroadcast = async function(id) {
                    if (!await showConfirm('Hapus Broadcast', 'Semua notifikasi terkait juga akan dihapus.', '🗑️', 'Ya, Hapus')) return;
                    const fd = new FormData(); fd.append('id', id);
                    const j = await (await fetch(API_BC + 'delete_broadcast', {method:'POST', body:fd})).json();
                    if (j.success) {
                        loadBroadcasts();
                        const t = document.getElementById('toast');
                        t.textContent = 'Broadcast dihapus.';
                        t.className = 'toast show';
                        setTimeout(() => t.className = 'toast', 3000);
                    } else {
                        alert('Gagal: ' + (j.message || ''));
                    }
                };

                loadBroadcasts();
            })();
            </script>

            <?php endif; /* end mode switch */ ?>

        </div><!-- /.content-wrapper -->
    </div><!-- /.main-content -->
</div><!-- /.account-page -->

<!-- ===== LIGHTBOX PREVIEW FOTO GLOBAL ===== -->
<div class="modal-overlay" id="fotoLightbox" onclick="if(event.target===this)closeFotoLightbox()">
    <div style="position:relative;max-width:90vw;max-height:90vh;display:flex;flex-direction:column;align-items:center;gap:14px">
        <img id="fotoLightboxImg" src="" alt=""
            style="max-width:90vw;max-height:72vh;object-fit:contain;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.8)">
        <p id="fotoLightboxCaption" style="color:#ccc;font-size:13px;text-align:center;max-width:500px"></p>
        <a id="fotoLightboxLink" href="#" target="_self"
            style="display:none;align-items:center;gap:8px;padding:9px 20px;border-radius:8px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff;font-size:13px;text-decoration:none;transition:.2s"
            onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.1)'">
            <i class="fas fa-external-link-alt"></i> Lihat di Artikel
        </a>
        <button onclick="closeFotoLightbox()"
            style="position:absolute;top:-14px;right:-14px;width:32px;height:32px;border-radius:50%;background:#222;border:1px solid #444;color:#aaa;font-size:18px;cursor:pointer;line-height:1">&times;</button>
    </div>
</div>
<script>
function closeFotoLightbox() {
    document.getElementById('fotoLightbox').classList.remove('active');
    document.body.style.overflow = '';
}
function openFotoLightbox(src, caption, artikelId) {
    document.getElementById('fotoLightboxImg').src = decodeURIComponent(src);
    document.getElementById('fotoLightboxCaption').textContent = decodeURIComponent(caption);
    const link = document.getElementById('fotoLightboxLink');
    if (artikelId) {
        link.href = 'artikel_viewer.php?id=' + encodeURIComponent(artikelId);
        link.style.display = 'inline-flex';
    } else {
        link.style.display = 'none';
    }
    document.getElementById('fotoLightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}
</script>

<!-- ===== TOAST GLOBAL ===== -->
<div class="toast <?= $toast ? 'show' : '' ?>" id="toast"><?= htmlspecialchars($toast) ?></div>

<script>
    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeConfirm();
    });
</script>

</body>
</html>