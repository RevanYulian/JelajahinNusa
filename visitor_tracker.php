<?php
// ============================================================
//  visitor_tracker.php — Pencatat Pengunjung JelajahinNusa
//
//  Cara pakai: include/require file ini di bagian ATAS
//  setiap halaman publik (index.php, artikel_viewer.php, dll.)
//
//  Contoh:
//      require_once 'visitor_tracker.php';
//
//  Pastikan sudah menjalankan statistik_tables.sql terlebih dulu.
// ============================================================

if (!defined('VISITOR_TRACKER_LOADED')) {
    define('VISITOR_TRACKER_LOADED', true);

    require_once __DIR__ . '/config.php';

    try {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $pdo        = getDB();
        $session_id = session_id() ?: bin2hex(random_bytes(16));
        $ip         = $_SERVER['HTTP_X_FORWARDED_FOR']
                        ?? $_SERVER['HTTP_X_REAL_IP']
                        ?? $_SERVER['REMOTE_ADDR']
                        ?? '';
        // Ambil IP pertama jika ada proxy
        $ip         = trim(explode(',', $ip)[0]);
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $url        = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                        . ($_SERVER['REQUEST_URI'] ?? '/');
        $referrer   = $_SERVER['HTTP_REFERER'] ?? null;
        $today      = date('Y-m-d');

        // Abaikan bot umum
        $botPatterns = ['bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'facebookexternalhit', 'curl', 'wget', 'python', 'go-http'];
        $uaLower = strtolower($user_agent);
        foreach ($botPatterns as $bot) {
            if (str_contains($uaLower, $bot)) return;
        }

        // Catat ke visitor_log
        $pdo->prepare(
            "INSERT INTO visitor_log (session_id, ip_address, user_agent, url, referrer, visited_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        )->execute([$session_id, $ip, $user_agent, substr($url, 0, 500), $referrer ? substr($referrer, 0, 500) : null]);

        // Update ringkasan harian (visitor_daily)
        // Hitung ulang dari visitor_log untuk hari ini
        $total = (int)$pdo->prepare(
            "SELECT COUNT(*) FROM visitor_log WHERE DATE(visited_at) = ?"
        )->execute([$today]) ? $pdo->query("SELECT COUNT(*) FROM visitor_log WHERE DATE(visited_at) = '$today'")->fetchColumn() : 0;

        $unique = (int)$pdo->query(
            "SELECT COUNT(DISTINCT session_id) FROM visitor_log WHERE DATE(visited_at) = '$today'"
        )->fetchColumn();

        $pdo->prepare(
            "INSERT INTO visitor_daily (tanggal, total_kunjungan, unique_visitor)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                total_kunjungan = VALUES(total_kunjungan),
                unique_visitor  = VALUES(unique_visitor)"
        )->execute([$today, $total, $unique]);

    } catch (\Throwable $e) {
        // Jangan sampai error tracker mengganggu halaman utama
        // error_log('visitor_tracker error: ' . $e->getMessage());
    }
}
