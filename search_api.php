<?php
// ============================================================
//  search_api.php — Endpoint Autocomplete Pencarian
//  GET ?q=keyword  →  JSON array sugesti (artikel + galeri)
// ============================================================
require_once 'config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $pdo  = getDB();
    $like = '%' . $q . '%';

    // ── Artikel ──
    $stmtA = $pdo->prepare("
        SELECT a.id, a.title, a.location, a.image, a.views,
               k.nama AS kategori, k.slug AS kat_slug
        FROM artikel a
        JOIN kategori_artikel k ON a.kategori_id = k.id
        WHERE a.status = 'published'
          AND (a.title LIKE ? OR a.location LIKE ? OR k.nama LIKE ?)
        ORDER BY CASE WHEN a.title LIKE ? THEN 0 ELSE 1 END, a.views DESC
        LIMIT 5
    ");
    $stmtA->execute([$like, $like, $like, $q . '%']);
    $artikels = array_map(fn($r) => [
        'type'     => 'artikel',
        'id'       => $r['id'],
        'title'    => $r['title'],
        'location' => $r['location'] ?? '',
        'kategori' => $r['kategori'],
        'image'    => $r['image'] ?? '',
        'views'    => (int)$r['views'],
        'url'      => 'artikel_viewer.php?id=' . urlencode($r['id']),
    ], $stmtA->fetchAll(PDO::FETCH_ASSOC));

    // ── Galeri ──
    $stmtG = $pdo->prepare("
        SELECT id, judul, lokasi, image_path, created_at
        FROM galeri
        WHERE judul LIKE ? OR lokasi LIKE ? OR caption LIKE ?
        ORDER BY CASE WHEN judul LIKE ? THEN 0 ELSE 1 END, created_at DESC
        LIMIT 4
    ");
    $stmtG->execute([$like, $like, $like, $q . '%']);
    $galeris = array_map(fn($r) => [
        'type'     => 'galeri',
        'id'       => $r['id'],
        'title'    => $r['judul'] ?? '',
        'location' => $r['lokasi'] ?? '',
        'kategori' => 'Galeri',
        'image'    => $r['image_path'] ?? '',
        'views'    => 0,
        'url'      => 'galeri.php#foto-' . $r['id'],
    ], $stmtG->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode(array_merge($artikels, $galeris));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}