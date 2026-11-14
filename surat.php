<?php
/** Melihat/mengunduh surat resmi peminjaman. Hanya admin dan pemilik pengajuan yang boleh mengakses. */
require __DIR__ . '/includes/bootstrap.php';
auth();

$s = $db->prepare('SELECT id_user, surat_file, surat_nama FROM peminjaman WHERE id_pinjam = ?');
$s->execute([(int) ($_GET['id'] ?? 0)]);
$p = $s->fetch();

$path = $p && preg_match('/^[a-f0-9]{32}$/', (string) $p['surat_file']) ? DIR_SURAT . '/' . $p['surat_file'] : '';
if (!$p || $path === '' || !is_file($path) || (!is_admin() && (int) $p['id_user'] !== user_id())) {
    http_response_code(404);
    flash('Surat tidak ditemukan.');
    header('Location: peminjaman.php');
    exit;
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
$inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
$nama = $p['surat_nama'] ?: 'surat';

header('Content-Type: ' . ($inline ? $mime : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($nama));
header('X-Content-Type-Options: nosniff');
readfile($path);