<?php
require __DIR__ . '/includes/bootstrap.php';
auth(true); // khusus admin

$rekap = rekap();
$jenis = array_sum(array_column($rekap, 'jenis'));
$stok  = array_sum(array_column($rekap, 'stok'));
$baik  = array_sum(array_column($rekap, 'baik'));
$rusak = array_sum(array_column($rekap, 'ringan')) + array_sum(array_column($rekap, 'berat'));
$dipinjam = array_sum(array_column($rekap, 'dipinjam'));
$menunggu = jumlah_menunggu();

// Permintaan pinjam terbaru yang menunggu persetujuan
$antrean = $db->query("
    SELECT p.id_pinjam, p.nama_peminjam, p.jumlah, p.tgl_pinjam, b.nama_barang, b.kode_barang, b.satuan
    FROM peminjaman p JOIN barang b ON b.id_barang = p.id_barang
    WHERE p.status = 'pending' ORDER BY p.id_pinjam DESC LIMIT 5")->fetchAll();

// Barang yang perlu perhatian (rusak berat lebih dulu)
$perhatian = $db->query("
    SELECT b.kode_barang, b.nama_barang, b.merk, b.jumlah, b.kondisi, l.nama_lab
    FROM barang b JOIN lab l ON l.id_lab = b.id_lab
    WHERE b.kondisi <> 'Baik'
    ORDER BY b.kondisi DESC, b.id_barang DESC LIMIT 6")->fetchAll();

// Barang yang sedang dipinjam (disetujui, belum dikembalikan)
$aktif = $db->query("
    SELECT p.id_pinjam, p.nama_peminjam, p.jumlah, p.tgl_pinjam, p.tgl_kembali_rencana, p.status, b.nama_barang, b.satuan
    FROM peminjaman p JOIN barang b ON b.id_barang = p.id_barang
    WHERE p.status = 'disetujui' ORDER BY p.tgl_pinjam ASC, p.id_pinjam ASC LIMIT 5")->fetchAll();

// ---- Data diagram ----
$ringan = array_sum(array_column($rekap, 'ringan'));
$berat  = array_sum(array_column($rekap, 'berat'));
$kondisi = [
    ['Baik',         $baik,   'var(--g)'],
    ['Rusak Ringan', $ringan, 'var(--gold)'],
    ['Rusak Berat',  $berat,  'var(--bad)'],
];
$totalK = max(1, $baik + $ringan + $berat);
$maxStok = max(1, ...array_map('intval', array_column($rekap, 'stok') ?: [1]));

// Peminjaman 6 bulan terakhir
$bulanNama = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$bulan = [];
for ($i = 5; $i >= 0; $i--) {
    $t = strtotime("first day of -$i month");
    $bulan[date('Y-m', $t)] = [$bulanNama[(int) date('n', $t) - 1], 0];
}
$q = $db->prepare("SELECT DATE_FORMAT(tgl_pinjam, '%Y-%m') ym, COUNT(*) n FROM peminjaman
                   WHERE status <> 'dibatalkan' AND tgl_pinjam >= ? GROUP BY ym");
$q->execute([date('Y-m-01', strtotime('first day of -5 month'))]);
foreach ($q->fetchAll() as $r) {
    if (isset($bulan[$r['ym']])) { $bulan[$r['ym']][1] = (int) $r['n']; }
}
$maxBln = max(1, ...array_column($bulan, 1));
$adaPinjam = array_sum(array_column($bulan, 1)) > 0;

$title = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="cards">
    <div class="card"><b><?= count($labs) ?></b><span>Laboratorium</span></div>
    <div class="card"><b><?= $jenis ?></b><span>Jenis Barang</span></div>
    <div class="card"><b><?= $stok ?></b><span>Total Aset (unit)</span></div>
    <div class="card pinjam"><b><?= $dipinjam ?></b><span>Sedang Dipinjam</span></div>
    <div class="card warn"><b><?= $menunggu ?></b><span>Menunggu Persetujuan</span></div>
    <div class="card warn"><b><?= $rusak ?></b><span>Perlu Perbaikan</span></div>
</div>

<div class="quick noprint">
    <a class="btn" href="form.php">+ Tambah Barang</a>
    <a class="btn alt" href="barang.php">Cari Barang</a>
    <a class="btn alt" href="peminjaman.php">Kelola Peminjaman</a>
    <a class="btn alt" href="pengguna.php">Kelola Pengguna</a>
    <a class="btn alt" href="laporan.php">Cetak Laporan</a>
    <a class="btn alt" href="<?= WEB_SEKOLAH ?>" target="_blank" rel="noopener">Website MTsN 1 Klaten &#8599;</a>
</div>

<div class="charts">
    <section class="panel">
        <h2>Kondisi Barang</h2>
        <div class="chart-body donut-wrap">
            <svg class="donut" viewBox="0 0 36 36" role="img" aria-label="Diagram kondisi barang">
                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#e4e9e6" stroke-width="4"/>
                <?php $cum = 0; foreach ($kondisi as [$lbl, $n, $warna]): if ($n <= 0) continue; $pc = $n / $totalK * 100; ?>
                    <circle class="seg" cx="18" cy="18" r="15.9155" fill="none" stroke="<?= $warna ?>" stroke-width="4"
                            stroke-dasharray="<?= round($pc, 2) ?> <?= round(100 - $pc, 2) ?>" stroke-dashoffset="<?= round(25 - $cum, 2) ?>"><title><?= e($lbl) ?>: <?= $n ?> unit</title></circle>
                <?php $cum += $pc; endforeach; ?>
                <text x="18" y="18.6" text-anchor="middle" class="donut-num"><?= $baik + $ringan + $berat ?></text>
                <text x="18" y="23" text-anchor="middle" class="donut-lbl">unit</text>
            </svg>
            <ul class="legend">
                <?php foreach ($kondisi as [$lbl, $n, $warna]): ?>
                    <li><i style="background:<?= $warna ?>"></i><span><?= e($lbl) ?></span><b><?= $n ?></b><small><?= round($n / $totalK * 100) ?>%</small></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="panel">
        <h2>Aset per Laboratorium</h2>
        <div class="chart-body hbars">
            <?php foreach ($rekap as $r):
                $w  = $r['stok'] / $maxStok * 100;
                $pb = $r['stok'] ? $r['baik']   / $r['stok'] * 100 : 0;
                $pr = $r['stok'] ? $r['ringan'] / $r['stok'] * 100 : 0;
                $pk = max(0, 100 - $pb - $pr);
            ?>
                <div class="hb" title="<?= e($r['nama_lab']) ?>: <?= $r['stok'] ?> unit">
                    <span class="hb-l"><?= e($r['nama_lab']) ?></span>
                    <div class="hb-track"><div class="hb-bar" style="width:<?= round($w, 1) ?>%">
                        <i style="width:<?= round($pb, 1) ?>%;background:var(--g)"></i><i style="width:<?= round($pr, 1) ?>%;background:var(--gold)"></i><i style="width:<?= round($pk, 1) ?>%;background:var(--bad)"></i>
                    </div></div>
                    <b class="hb-v"><?= $r['stok'] ?></b>
                </div>
            <?php endforeach; ?>
            <p class="chart-ket"><i style="background:var(--g)"></i>Baik <i style="background:var(--gold)"></i>Rusak ringan <i style="background:var(--bad)"></i>Rusak berat</p>
        </div>
    </section>

    <section class="panel">
        <h2>Peminjaman 6 Bulan Terakhir</h2>
        <div class="chart-body">
            <div class="vbars">
                <?php foreach ($bulan as [$nm, $n]): ?>
                    <div class="vb" title="<?= $nm ?>: <?= $n ?> pengajuan">
                        <b><?= $n ?></b>
                        <div class="vb-bar" style="height:<?= $n ? max(6, round($n / $maxBln * 100)) : 2 ?>%"></div>
                        <span><?= $nm ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!$adaPinjam): ?><p class="chart-ket">Belum ada pengajuan pinjam pada 6 bulan terakhir.</p><?php endif; ?>
        </div>
    </section>
</div>

<div class="two">
    <div class="col">
    <section class="panel">
        <h2>Rekap per Laboratorium</h2>
        <div class="scroll">
        <table class="r">
            <thead><tr><th>Laboratorium</th><th>Jenis</th><th>Aset</th><th>Dipinjam</th><th>Kondisi Baik</th></tr></thead>
            <tbody>
            <?php foreach ($rekap as $r): $pct = $r['stok'] ? round($r['baik'] / $r['stok'] * 100) : 0; ?>
                <tr>
                    <td data-label="Laboratorium"><?= e($r['nama_lab']) ?></td>
                    <td data-label="Jenis"><?= $r['jenis'] ?></td>
                    <td data-label="Aset"><?= $r['stok'] ?></td>
                    <td data-label="Dipinjam"><?= $r['dipinjam'] ? '<span class="badge b-info">' . $r['dipinjam'] . '</span>' : '0' ?></td>
                    <td data-label="Kondisi Baik"><div class="bar"><i style="width:<?= $pct ?>%"></i></div> <?= $pct ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
    </div>

    <div class="col">
    <section class="panel">
        <h2>Menunggu Persetujuan <a class="lihat" href="peminjaman.php?status=pending">Lihat semua</a></h2>
        <?php if (!$antrean): ?>
            <p class="empty"><b>Semua permintaan sudah diproses</b><small>Pengajuan pinjam baru dari pengguna akan muncul di sini.</small></p>
        <?php else: ?>
            <ul class="list">
            <?php foreach ($antrean as $r): ?>
                <li>
                    <div><b><?= e($r['nama_barang']) ?></b><small><?= e($r['nama_peminjam']) ?> &middot; <?= $r['jumlah'] . ' ' . e($r['satuan']) ?> &middot; <?= tgl($r['tgl_pinjam']) ?></small></div>
                    <a class="btn sm" href="peminjaman.php?status=pending">Proses</a>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Sedang Dipinjam <a class="lihat" href="peminjaman.php?status=disetujui">Lihat semua</a></h2>
        <?php if (!$aktif): ?>
            <p class="empty"><b>Semua barang ada di laboratorium</b><small>Barang yang disetujui untuk dipinjam akan muncul di sini beserta tanggal pinjamnya.</small></p>
        <?php else: ?>
            <ul class="list">
            <?php foreach ($aktif as $r): ?>
                <li>
                    <div><b><?= e($r['nama_barang']) ?></b><small><?= e($r['nama_peminjam']) ?> &middot; <?= $r['jumlah'] . ' ' . e($r['satuan']) ?></small></div>
                    <span class="badge b-info">Sejak <?= tgl($r['tgl_pinjam']) ?></span><?php if (pinjam_terlambat($r)): ?> <span class="badge b-bad">Terlambat <?= hari_terlambat($r) ?> hari</span><?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    </div>
</div>
<section class="panel">
    <h2>Perlu Perhatian <span class="badge b-warn"><?= $rusak ?> unit perlu perbaikan</span></h2>
    <?php if (!$perhatian): ?>
        <p class="empty">Semua barang dalam kondisi baik.</p>
    <?php else: ?>
        <ul class="list cols2">
        <?php foreach ($perhatian as $r): ?>
            <li>
                <div><b><?= e($r['nama_barang']) ?></b><small><?= e($r['kode_barang']) ?> &middot; <?= e($r['nama_lab']) ?> &middot; <?= $r['jumlah'] ?> unit</small></div>
                <span class="badge b-<?= KONDISI[$r['kondisi']] ?>"><?= e($r['kondisi']) ?></span>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<p class="note-aset">Unit yang sedang dipinjam tetap dihitung sebagai aset <?= SEKOLAH ?>.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>