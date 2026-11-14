<?php
/**
 * Kerangka atas halaman setelah login (admin & pengguna).
 * Variabel: $title (wajib), $crumb (opsional, array label breadcrumb).
 */
$hal    = basename($_SERVER['PHP_SELF']);
$lab_id = (int) ($_GET['lab'] ?? 0);
$crumb  = $crumb ?? [$title];
$admin  = is_admin();
$on     = fn($aktif) => $aktif ? ' class="on"' : '';
$tunggu = $admin ? jumlah_menunggu() : 0;
require_once __DIR__ . '/foto_akun.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | <?= SEKOLAH ?></title>
<link rel="icon" type="image/png" href="assets/img/favicon.png">
<link rel="stylesheet" href="<?= aset('assets/css/style.css') ?>">
<script src="<?= aset('assets/js/app.js') ?>" defer></script>
<script src="<?= aset('assets/js/ui.js') ?>" defer></script>
<script src="<?= aset('assets/js/loader.js') ?>" defer></script>
</head>
<body class="hal-<?= e(pathinfo($hal, PATHINFO_FILENAME)) ?>">

<div class="topbar noprint">
    <div class="container">
        <span><?= APLIKASI ?></span>
        <span class="tb-right">
            <a class="ext" href="<?= WEB_SEKOLAH ?>" target="_blank" rel="noopener">Website MTsN 1 Klaten &#8599;</a>
            <a class="ext" href="<?= e(wa_link(WA_ADMIN, 'Assalamu\'alaikum Admin Lab ' . SEKOLAH . ', saya ingin bertanya untuk peminjaman inventaris MTsN 1 Klaten itu prosedurnya bagaimana?.')) ?>" target="_blank" rel="noopener">WhatsApp Admin</a>
        </span>
    </div>
</div>

<header class="site-head noprint">
    <div class="container">
        <a class="logos" href="index.php" aria-label="Ke beranda">
            <img src="assets/img/logo-kemenag.png" alt="Logo Kementerian Agama">
            <img src="assets/img/logo-mtsn1.png" alt="Logo <?= SEKOLAH ?>">
        </a>
        <div class="identity">
            <small>Kementerian Agama Republik Indonesia</small>
            <h2><?= mb_strtoupper(NAMA_LONG) ?></h2>
            <p><?= DAERAH ?></p>
        </div>
    </div>
</header>

<nav class="nav noprint" aria-label="Menu utama">
    <div class="container">
        <button type="button" class="burger" aria-label="Menu" aria-controls="menu" onclick="document.getElementById('menu').classList.toggle('open')">&#9776; Menu</button>
        <ul id="menu">
            <li><a href="index.php">Beranda</a></li>
            <?php if ($admin): ?>
                <li><a href="dashboard.php"<?= $on($hal === 'dashboard.php') ?>>Dashboard</a></li>
            <?php endif; ?>
            <li><a href="barang.php"<?= $on(in_array($hal, ['barang.php', 'form.php', 'pinjam.php']) && !$lab_id) ?>>Data Barang</a></li>
            <li class="has-sub<?= ($hal === 'barang.php' && $lab_id) ? ' open' : '' ?>">
                <a href="#"<?= $on($hal === 'barang.php' && $lab_id) ?> aria-haspopup="true">Laboratorium <span class="caret" aria-hidden="true">&#9662;</span></a>
                <ul class="sub">
                    <?php foreach ($labs as $id => $nama): ?>
                        <li><a href="barang.php?lab=<?= $id ?>"><?= e($nama) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <li><a href="peminjaman.php"<?= $on(in_array($hal, ['peminjaman.php', 'pinjam_wa.php'])) ?>><?= $admin ? 'Peminjaman' : 'Peminjaman Saya' ?><?php if ($tunggu): ?> <span class="dot" title="Menunggu persetujuan"><?= $tunggu ?></span><?php endif; ?></a></li>
            <?php if ($admin): ?>
                <li><a href="laporan.php"<?= $on($hal === 'laporan.php') ?>>Laporan</a></li>
                <li><a href="pengguna.php"<?= $on($hal === 'pengguna.php') ?>>Pengguna</a></li>
                <?php if (is_superadmin()): ?>
                <li><a href="struktur_kelola.php"<?= $on($hal === 'struktur_kelola.php') ?>>Struktur Lab</a></li>
                <?php endif; ?>
            <?php endif; ?>
            <li class="has-sub um">
                <a href="profil.php"<?= $on($hal === 'profil.php') ?>><?= akun_avatar($_SESSION['user'], akun_foto_nama()) ?> <?= e($_SESSION['user']) ?> <span class="caret" aria-hidden="true">&#9662;</span></a>
                <ul class="sub">
                    <li class="sub-info"><small><?= e(label_peran($_SESSION['role'] ?? 'user')) ?></small></li>
                    <li><a href="profil.php">Profil Saya</a></li>
                    <li><a href="logout.php" data-konfirm="keluar">Keluar</a></li>
                </ul>
            </li>
        </ul>
    </div>
</nav>

<section class="hero noprint">
    <div class="container">
        <h1><?= e($title) ?></h1>
        <ol class="crumb">
            <li><a href="index.php">Beranda</a></li>
            <?php foreach ($crumb as $k => $c): ?><li><?php if (is_string($k)): ?><a href="<?= e($c) ?>"><?= e($k) ?></a><?php else: ?><?= e($c) ?><?php endif; ?></li><?php endforeach; ?>
        </ol>
    </div>
</section>

<main class="container main">
<?php
// Pengingat: barang yang dipinjam akun ini sudah melewati batas pengembalian (tampil di setiap halaman setelah login).
$telat_saya = pinjam_terlambat_saya();
$ada_telat  = (bool) array_filter($telat_saya, fn($t) => hari_terlambat($t) > 0);
if ($telat_saya): ?>
<div class="alert <?= $ada_telat ? 'error' : 'info' ?> telat-banner noprint" role="alert">
    <b>&#9888; <?= $ada_telat ? 'Anda belum mengembalikan barang pinjaman sesuai batas waktu:' : 'Batas pengembalian barang pinjaman Anda jatuh tempo hari ini:' ?></b>
    <ul>
    <?php foreach ($telat_saya as $t): $hr = hari_terlambat($t); ?>
        <li><?= e($t['nama_barang']) ?> (<?= e($t['kode_barang']) ?>) &middot; <?= (int) $t['jumlah'] . ' ' . e($t['satuan']) ?> &mdash; batas kembali <?= tgl_id($t['tgl_kembali_rencana']) ?><?= $hr > 0 ? ', terlambat ' . $hr . ' hari' : ' (hari ini)' ?></li>
    <?php endforeach; ?>
    </ul>
    <small>Mohon kembalikan ke laboratorium atau hubungi admin.</small>
</div>
<?php endif; ?>