<?php
require __DIR__ . '/includes/bootstrap.php';

$_SESSION = [];
session_destroy();
// Mulai sesi baru agar pesan perpisahan bisa tampil di halaman login.
session_start();
session_regenerate_id(true);
flash('Anda telah keluar. Sampai jumpa!', 'info');
header('Location: login.php');
