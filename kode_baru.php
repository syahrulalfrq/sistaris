<?php
/**
 * Endpoint JSON: kode barang otomatis untuk laboratorium terpilih.
 * Dipanggil oleh formulir barang saat laboratorium diganti. Khusus admin.
 */
require __DIR__ . '/includes/bootstrap.php';

if (!masuk() || !is_admin()) {
    json_keluar(['ok' => false, 'pesan' => 'Tidak berwenang.'], 403);
}
$lab = (int) ($_GET['lab'] ?? 0);
$id  = (int) ($_GET['id'] ?? 0);
if (!isset($labs[$lab])) {
    json_keluar(['ok' => false, 'pesan' => 'Laboratorium tidak ditemukan.'], 404);
}
json_keluar(['ok' => true, 'kode' => kode_berikut($lab, $id), 'awalan' => kode_lab($lab)]);
