<?php
/**
 * Endpoint JSON penghitung pengunjung.
 * Dipanggil berkala oleh assets/js/app.js agar angka "Online" tetap segar.
 */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(pengunjung(), JSON_UNESCAPED_UNICODE);
