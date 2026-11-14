<?php
/**
 * Beranda publik: perkenalan sistem, statistik ringkas, profil madrasah.
 * Tidak memerlukan login. Pencarian & pengelolaan data tetap khusus admin.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/struktur_fungsi.php';

$masuk = masuk();
$admin = is_admin();
$rekap = rekap();
$jenis = array_sum(array_column($rekap, 'jenis'));
$stok  = array_sum(array_column($rekap, 'stok'));
$baik  = array_sum(array_column($rekap, 'baik'));
$rusak = array_sum(array_column($rekap, 'ringan')) + array_sum(array_column($rekap, 'berat'));
$dipinjam = array_sum(array_column($rekap, 'dipinjam'));

// Struktur Lab: bila tabel belum bisa dibaca, beranda tetap tampil normal.
try {
    $struktur = struktur_ambil();
} catch (Throwable $e) {
    $struktur = [];
}

$fitur = [
    ['&#128230;', 'Pendataan Terpusat', 'Seluruh barang laboratorium tercatat rapi lengkap dengan kode, merk, kategori, jumlah, kondisi, dan tanggal masuk.'],
    ['&#128269;', 'Pencarian Cepat', 'Temukan barang hanya dengan mengetik kata kunci. Hasil muncul langsung, kata yang dicari ditandai.'],
    ['&#128290;', 'Kode Barang Otomatis', 'Kode barang mengikuti laboratorium yang dipilih, misalnya POD-001 untuk Lab Podcast, dan mengisi nomor yang kosong.'],
    ['&#128260;', 'Pemantauan Kondisi', 'Pantau barang yang baik, rusak ringan, atau rusak berat agar perbaikan dapat diprioritaskan.'],
    ['&#129309;', 'Peminjaman via WhatsApp', 'Ajukan peminjaman, lalu pesan berformat otomatis terkirim ke admin lewat WhatsApp. Status menunggu sampai disetujui.'],
    ['&#128424;', 'Laporan Siap Cetak', 'Cetak laporan inventaris dengan kop surat, tabel rapi, dan kolom tanda tangan dalam satu klik.'],
    ['&#9989;', 'Konfirmasi & Validasi', 'Setiap penambahan, perubahan, dan penghapusan data diperiksa dan dikonfirmasi lewat pop-up agar data tetap akurat.'],
    ['&#128101;', 'Manajemen Pengguna', 'Super Admin mengelola semua akun, Admin membuat akun pengguna. Pengguna biasa hanya dapat meminjam barang.'],
    ['&#128274;', 'Akses Aman', 'Kata sandi disimpan terenkripsi, login dibatasi dari percobaan berulang, dan halaman admin terlindungi.'],
];

$langkah = [
    ['Masuk dengan Akun', 'Akun dibuat oleh admin. Admin mengelola inventaris, pengguna hanya meminjam barang.'],
    ['Kelola atau Pinjam', 'Admin menambah, mengubah, dan menghapus data. Pengguna memilih barang lalu menekan Pinjam.'],
    ['Setujui & Laporkan', 'Pengajuan pinjam menunggu persetujuan admin, lalu seluruh data dapat dicetak sebagai laporan resmi.'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= APLIKASI ?> | <?= SEKOLAH ?></title>
<meta name="description" content="<?= APLIKASI ?> <?= SEKOLAH ?>: pendataan, pencarian, dan laporan barang laboratorium.">
<link rel="icon" type="image/png" href="assets/img/favicon.png">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>">
<link rel="stylesheet" href="assets/css/home.css?v=<?= @filemtime(__DIR__ . '/assets/css/home.css') ?>">
<link rel="stylesheet" href="assets/css/struktur.css?v=<?= @filemtime(__DIR__ . '/assets/css/struktur.css') ?>">
<script>document.documentElement.classList.add('js')</script>
<script src="assets/js/app.js?v=<?= @filemtime(__DIR__ . '/assets/js/app.js') ?>" defer></script>
<script src="assets/js/home.js" defer></script>
<script src="assets/js/loader.js?v=<?= @filemtime(__DIR__ . '/assets/js/loader.js') ?>" defer></script>
</head>
<body class="home">

<header class="pnav" id="pnav">
    <div class="container">
        <!-- Logo Kemenag & Logo Mumpuni (ukuran Mumpuni diperbesar menyamai Kemenag dan dipaksa tampil dengan !important) -->
        <a class="brand" href="#beranda" aria-label="<?= SEKOLAH ?> Mumpuni - Beranda" style="display: flex; align-items: center; gap: 8px;">
            <img src="assets/img/logo-kemenag.png" alt="Kemenag" style="height: 36px; width: auto; display: inline-block !important;">
            <img class="brand-logo" src="assets/img/logo-mumpuni.png" alt="<?= SEKOLAH ?> Mumpuni" style="height: 35px; width: auto; object-fit: contain; display: inline-block !important;">
        </a>
        <button class="burger-p" id="burger" type="button" aria-label="Buka menu" aria-expanded="false" aria-controls="pmenu"><i></i><i></i><i></i></button>
        <nav id="pmenu" aria-label="Menu utama">
            <a href="#beranda" class="on">Beranda</a>
            <a href="#tentang">Tentang Sistem</a>
            <a href="#fitur">Fitur</a>
            <a href="#statistik">Statistik</a>
            <a href="#madrasah">Madrasah</a>
            <a href="#struktur">Struktur Lab</a>
            <?php if ($masuk): ?>
                <a class="btn sm" href="<?= beranda_peran() ?>"><?= $admin ? 'Dashboard' : 'Data Barang' ?></a>
                <a class="btn sm ghost" href="logout.php" data-konfirm="keluar">Keluar</a>
            <?php else: ?>
                <a class="btn sm" href="login.php">Masuk</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<!-- ============ HERO ============ -->
<section class="hero-p" id="beranda">
    <canvas id="partikel" aria-hidden="true"></canvas>
    <i class="blob b1"></i><i class="blob b2"></i>

    <div class="container hero-grid">
        <div class="hero-text">
            <span class="chip rv"><b class="chip-utama"><?= CHIP_UTAMA ?></b><i class="chip-sep" aria-hidden="true">|</i><span class="chip-unit"><?= CHIP_UNIT ?></span></span>
            <h1 class="rv" style="--d:.08s"><?= APLIKASI ?></h1>
            <p class="ketik rv" style="--d:.16s">
                <?= SEKOLAH ?> yang <b id="ketik" data-kata='["Mulia","Mandiri","Berprestasi","Unggul","Melayani"]'>Mulia</b><span class="kursor" aria-hidden="true">|</span>
            </p>
            <p class="lead rv" style="--d:.24s">
                Kelola, cari, dan laporkan seluruh barang laboratorium dalam satu tempat. Lebih rapi, lebih cepat, dan selalu siap dicetak.
            </p>

            <form class="hero-cari rv" style="--d:.32s" action="barang.php" method="get" role="search">
                <input type="search" name="cari" placeholder="Cari barang: monitor, mikroskop, KOM-001..." maxlength="100" aria-label="Cari barang inventaris" autocomplete="off">
                <button class="btn gold">Cari</button>
            </form>
            <p class="hint rv" style="--d:.36s">
                <?= $masuk ? 'Anda sudah masuk. Pencarian akan langsung membuka data barang.' : 'Pencarian dan peminjaman barang memerlukan akun, Anda akan diminta masuk terlebih dahulu.' ?>
            </p>

            <div class="cta rv" style="--d:.42s">
                <?php if (!$masuk): ?>
                    <a class="btn gold" href="login.php">Masuk</a>
                <?php endif; ?>
                <a class="btn ghost" href="<?= WEB_SEKOLAH ?>" target="_blank" rel="noopener">Kunjungi Website MTsN 1 Klaten &#8599;</a>
            </div>
        </div>

        <div class="stage-wrap">
            <div class="stage" id="stage">
                <div class="glow"></div>
                <svg class="rings" viewBox="0 0 400 400" aria-hidden="true">
                    <circle class="r1" cx="200" cy="200" r="190"/>
                    <circle class="r2" cx="200" cy="200" r="160"/>
                    <circle class="r3" cx="200" cy="200" r="130"/>
                </svg>
                <div class="orbit o1"><i></i></div>
                <div class="orbit o2"><i></i></div>
                <div class="orbit o3"><i></i></div>
                <span class="pulse"></span><span class="pulse p2"></span>
                <div class="tilt" id="tilt">
                    <div class="float">
                        <button class="medal" id="medal" type="button" aria-label="Logo <?= SEKOLAH ?>. Tekan untuk melihat animasi">
                            <img src="assets/img/logo-mtsn1.png" alt="Logo <?= SEKOLAH ?>" draggable="false">
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <svg class="wave" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true"><path d="M0,40 C240,100 480,0 720,40 C960,80 1200,10 1440,50 L1440,90 L0,90 Z"/></svg>
</section>

<!-- ============ TENTANG SISTEM ============ -->
<section class="sec" id="tentang">
    <div class="container tentang">
        <div class="rv">
            <span class="eyebrow">Tentang Sistem</span>
            <h2>Apa itu Sistem Informasi Inventaris Laboratorium?</h2>
            <p><?= APLIKASI ?> adalah aplikasi web yang membantu <?= SEKOLAH ?> mencatat, memantau, dan melaporkan seluruh barang di laboratorium secara terpusat. Pencatatan manual yang tersebar di banyak buku diganti dengan data digital yang rapi, mudah dicari, dan selalu terbarui.</p>
            <p>Setiap barang memiliki kode unik, kategori, jumlah, serta kondisi. Admin dapat menambah, mengubah, dan menghapus data dengan validasi, mengelola akun pengguna, menyetujui peminjaman, lalu mencetak laporan resmi lengkap dengan kop surat madrasah.</p>
            <div class="chips">
                <?php foreach ($labs as $id => $nama): ?><span class="lab-chip"><?= e($nama) ?></span><?php endforeach; ?>
            </div>
        </div>
        <ol class="langkah">
            <?php foreach ($langkah as $i => [$jd, $isi]): ?>
                <li class="rv spot" style="--d:<?= $i * .1 ?>s"><span class="no"><?= $i + 1 ?></span><div><b><?= e($jd) ?></b><p><?= e($isi) ?></p></div></li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<!-- ============ FITUR ============ -->
<section class="sec alt" id="fitur">
    <div class="container">
        <div class="head rv"><span class="eyebrow">Fitur</span><h2>Dirancang agar nyaman digunakan</h2></div>
        <div class="fitur-grid">
            <?php foreach ($fitur as $i => [$ic, $jd, $isi]): ?>
                <article class="fitur rv spot" style="--d:<?= ($i % 3) * .08 ?>s">
                    <span class="ic" aria-hidden="true"><?= $ic ?></span>
                    <h3><?= e($jd) ?></h3>
                    <p><?= e($isi) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ STATISTIK ============ -->
<section class="sec" id="statistik">
    <div class="container">
        <div class="head rv"><span class="eyebrow">Statistik</span><h2>Gambaran inventaris saat ini</h2></div>
        <div class="stat-grid">
            <div class="stat rv"><b data-count="<?= count($labs) ?>">0</b><span>Laboratorium</span></div>
            <div class="stat rv" style="--d:.08s"><b data-count="<?= $jenis ?>">0</b><span>Jenis Barang</span></div>
            <div class="stat rv" style="--d:.16s"><b data-count="<?= $stok ?>">0</b><span>Total Stok</span></div>
            <div class="stat warn rv" style="--d:.24s"><b data-count="<?= $rusak ?>">0</b><span>Perlu Perbaikan</span></div>
        </div>

        <p class="note-aset rv"><?= $dipinjam > 0 ? 'Saat ini <b>' . $dipinjam . ' unit</b> sedang dipinjam di luar ' . SEKOLAH . ' dan ' : 'Unit yang dipinjam di luar ' . SEKOLAH . ' ' ?>tetap dihitung sebagai aset madrasah.</p>

        <div class="lab-grid">
            <?php foreach ($rekap as $i => $r): $pct = $r['stok'] ? round($r['baik'] / $r['stok'] * 100) : 0; ?>
                <div class="labcard rv spot" style="--d:<?= $i * .07 ?>s">
                    <h3><?= e($r['nama_lab']) ?></h3>
                    <p><b><?= $r['jenis'] ?></b> jenis &middot; <b><?= $r['stok'] ?></b> unit<?= $r['dipinjam'] ? ' &middot; <b>' . $r['dipinjam'] . '</b> dipinjam' : '' ?></p>
                    <div class="bar2" role="img" aria-label="<?= $pct ?> persen kondisi baik"><i data-w="<?= $pct ?>"></i></div>
                    <small><?= $pct ?>% kondisi baik</small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ MADRASAH ============ -->
<section class="sec alt" id="madrasah">
    <div class="container">
        <div class="head rv"><span class="eyebrow">Madrasah</span><h2>Mengenal <?= SEKOLAH ?></h2></div>
        <div class="mad-grid">
            <div class="mad rv spot"><span class="ic" aria-hidden="true">&#128214;</span><h3>Akhlak &amp; Agama</h3><p>Pendidikan yang mengedepankan akhlak dan agama sebagai fondasi.</p></div>
            <div class="mad rv spot" style="--d:.08s"><span class="ic" aria-hidden="true">&#128161;</span><h3>Akademik &amp; Teknologi</h3><p>Tanpa mengesampingkan kemampuan akademik dan kemajuan teknologi.</p></div>
            <div class="mad rv spot" style="--d:.16s"><span class="ic" aria-hidden="true">&#128205;</span><h3>Lokasi Strategis</h3><p>Di Jalan Ki Ageng Gribig, dekat hutan kota Gergunung dan beberapa kilometer dari jalan utama Solo-Klaten-Jogja.</p></div>
        </div>
    </div>
</section>

<!-- ============ STRUKTUR LAB ============ -->
<section class="sec" id="struktur">
    <div class="container">
        <div class="head rv">
            <span class="eyebrow">Struktur Lab</span>
            <h2>Struktur Pengelola Laboratorium</h2>
            <p class="org-sub">Susunan pengelola laboratorium <?= SEKOLAH ?>, dari pimpinan madrasah hingga laboran.</p>
        </div>

        <div class="org-scroll rv">
            <div class="org">
                <ul>
                    <li>
                        <?= struktur_kartu($struktur, 'kamad', 'lvl1') ?>
                        <ul>
                            <li>
                                <?= struktur_kartu($struktur, 'waka_sarpras', 'lvl2') ?>
                                <ul>
                                    <li><?= struktur_kartu($struktur, 'koordinator_media', 'lvl3') ?></li>
                                </ul>
                            </li>
                            <li>
                                <?= struktur_kartu($struktur, 'kalab', 'lvl2') ?>
                                <ul>
                                    <li><?= struktur_kartu($struktur, 'laboran_ipa', 'lvl3') ?></li>
                                    <li><?= struktur_kartu($struktur, 'laboran_komputer', 'lvl3') ?></li>
                                    <li><?= struktur_kartu($struktur, 'laboran_bahasa', 'lvl3') ?></li>
                                    <li><?= struktur_kartu($struktur, 'laboran_agama', 'lvl3') ?></li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php $publik = true; require __DIR__ . '/includes/footer.php'; ?>