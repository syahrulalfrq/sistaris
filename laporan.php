<?php
require __DIR__ . '/includes/bootstrap.php';
auth(true); // khusus admin

// Filter laporan (opsional)
$lab = (int) ($_GET['lab'] ?? 0);
$kon = $_GET['kondisi'] ?? '';
if (!isset($labs[$lab]))  { $lab = 0; }
if (!isset(KONDISI[$kon])) { $kon = ''; }

$rekap = array_values(array_filter(rekap(), fn($r) => !$lab || $r['nama_lab'] === $labs[$lab]));

$w = [];
$p = [];
if ($lab) { $w[] = 'b.id_lab = ?';   $p[] = $lab; }
if ($kon) { $w[] = 'b.kondisi = ?'; $p[] = $kon; }

$s = $db->prepare('SELECT b.*, k.nama_kategori, l.nama_lab, ' . SQL_DIPINJAM . ' AS dipinjam
    FROM barang b
    JOIN kategori k ON k.id_kategori = b.id_kategori
    JOIN lab l ON l.id_lab = b.id_lab' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . '
    ORDER BY b.id_lab, b.kode_barang');
$s->execute($p);
$barang = $s->fetchAll();

$jml = ['jenis' => 0, 'stok' => 0, 'baik' => 0, 'ringan' => 0, 'berat' => 0, 'dipinjam' => 0];
foreach ($rekap as $r) {
    foreach ($jml as $k => $_) { $jml[$k] += $r[$k]; }
}

$ket = array_filter([
    $lab ? 'Laboratorium: ' . $labs[$lab] : 'Semua Laboratorium',
    $kon ? 'Kondisi: ' . $kon : null,
]);

$title = 'Laporan';
require __DIR__ . '/includes/header.php';
?>
<!-- ===== Tampilan layar: filter & tombol cetak ===== -->
<style id="lap-hp">
/* Laporan di HP: tabel menjadi kartu (hanya di layar; cetak tidak berubah) */
@media screen and (max-width: 640px) {
    .doc table.lap, .doc table.lap tbody, .doc table.lap tfoot { display: block; width: 100%; }
    .doc table.lap colgroup, .doc table.lap thead { display: none; }
    .doc .scroll { overflow-x: visible; }
    .doc .lap tr { display: block; padding: 10px 14px; border-bottom: 1px solid var(--line); }
    .doc .lap tbody tr:nth-child(even) { background: #fafcfb; }
    .doc .lap td {
        display: block; position: relative; min-height: 26px;
        padding: 4px 0 4px 44%; border: 0 !important;
        text-align: left !important; white-space: normal !important; overflow-wrap: anywhere;
    }
    .doc .lap td[data-label]::before {
        content: attr(data-label); position: absolute; left: 0; top: 4px;
        width: 40%; padding-right: 8px;
        font-weight: 600; font-size: 12.5px; line-height: 1.4; color: var(--mu);
    }
    .doc .lap td.empty { padding-left: 0; text-align: center !important; }
    .doc .lap tfoot tr { background: var(--gl); border-top: 2px solid var(--g); }
    .doc .lap tfoot td:first-child { padding-left: 0; font-size: 14px; }
}
</style>

<div class="toolbar noprint">
    <p class="muted">Data per <?= tgl_id() ?></p>
    <form class="filter inline" method="get">
        <select name="lab" aria-label="Laboratorium" onchange="this.form.submit()">
            <option value="0">Semua Lab</option><?php opts($labs, $lab); ?>
        </select>
        <select name="kondisi" aria-label="Kondisi" onchange="this.form.submit()">
            <option value="">Semua Kondisi</option><?php opts(daftar_kondisi(), $kon); ?>
        </select>
        <button type="button" class="btn" onclick="window.print()">Cetak Laporan</button>
    </form>
</div>
<p class="tip noprint">
    <b>Tips cetak:</b> matikan <i>Headers and footers</i> agar tanggal &amp; alamat halaman dari browser tidak ikut tercetak.
</p>

<!-- ===== Dokumen laporan (layar & cetak) ===== -->
<article class="doc">
    <?php require __DIR__ . '/includes/kop.php'; ?>

    <h3 class="judul only-print">LAPORAN INVENTARIS LABORATORIUM</h3>
    <p class="subjudul only-print"><?= e(implode(' | ', $ket)) ?> &mdash; Per <?= tgl_id() ?></p>

    <section class="panel sect">
        <h2>A. Rekap per Laboratorium</h2>
        <div class="scroll">
        <table class="lap lap-rekap">
            <colgroup>
                <col class="a-no"><col class="a-lab"><col class="a-jenis"><col class="a-aset">
                <col class="a-baik"><col class="a-ring"><col class="a-berat"><col class="a-pinjam">
            </colgroup>
            <thead><tr>
                <th class="c">No</th><th>Laboratorium</th><th class="c">Jenis</th><th class="c">Total Aset</th>
                <th class="c">Baik</th><th class="c">Rusak Ringan</th><th class="c">Rusak Berat</th><th class="c">Dipinjam</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rekap as $i => $r): ?>
                <tr>
                    <td class="c" data-label="No"><?= $i + 1 ?></td><td data-label="Laboratorium"><?= e($r['nama_lab']) ?></td>
                    <td class="c" data-label="Jenis"><?= $r['jenis'] ?></td><td class="c" data-label="Total Aset"><?= $r['stok'] ?></td>
                    <td class="c" data-label="Baik"><?= $r['baik'] ?></td><td class="c" data-label="Rusak Ringan"><?= $r['ringan'] ?></td><td class="c" data-label="Rusak Berat"><?= $r['berat'] ?></td><td class="c" data-label="Dipinjam"><?= $r['dipinjam'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr>
                <td colspan="2" class="c"><b>JUMLAH</b></td>
                <td class="c" data-label="Jenis"><b><?= $jml['jenis'] ?></b></td><td class="c" data-label="Total Aset"><b><?= $jml['stok'] ?></b></td>
                <td class="c" data-label="Baik"><b><?= $jml['baik'] ?></b></td><td class="c" data-label="Rusak Ringan"><b><?= $jml['ringan'] ?></b></td><td class="c" data-label="Rusak Berat"><b><?= $jml['berat'] ?></b></td><td class="c" data-label="Dipinjam"><b><?= $jml['dipinjam'] ?></b></td>
            </tr></tfoot>
        </table>
        </div>
    </section>

    <section class="panel sect">
        <h2>B. Rincian Barang</h2>
        <div class="scroll">
        <table class="lap lap-rinci">
            <colgroup>
                <col class="b-no"><col class="b-kode"><col class="b-nama"><col class="b-merk"><col class="b-kat">
                <col class="b-lab"><col class="b-jml"><col class="b-pjm"><col class="b-kon">
            </colgroup>
            <thead><tr>
                <th class="c">No</th><th class="c">Kode</th><th>Nama Barang</th><th>Merk</th>
                <th>Kategori</th><th>Laboratorium</th><th class="c">Jumlah</th><th class="c">Dipinjam</th><th class="c">Kondisi</th>
            </tr></thead>
            <tbody>
            <?php foreach ($barang as $i => $r): ?>
                <tr>
                    <td class="c" data-label="No"><?= $i + 1 ?></td>
                    <td class="c nw" data-label="Kode"><?= e($r['kode_barang']) ?></td>
                    <td data-label="Nama Barang"><?= e($r['nama_barang']) ?></td>
                    <td data-label="Merk"><?= e($r['merk'] ?: '-') ?></td>
                    <td data-label="Kategori"><?= e($r['nama_kategori']) ?></td>
                    <td data-label="Laboratorium"><?= e($r['nama_lab']) ?></td>
                    <td class="c nw" data-label="Jumlah"><?= $r['jumlah'] . ' ' . e($r['satuan']) ?></td>
                    <td class="c" data-label="Dipinjam"><?= (int) $r['dipinjam'] ?: '-' ?></td>
                    <td class="c" data-label="Kondisi"><?= e($r['kondisi']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$barang): ?><tr><td colspan="9" class="empty">Tidak ada data barang.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>

    <div class="ttd only-print">
        <div><p>Mengetahui,<br>Kepala Madrasah</p><span>(.....................................)</span><small>NIP. ..............................</small></div>
        <div><p>Klaten, <?= tgl_id() ?><br>Pengelola Laboratorium</p><span>(.....................................)</span><small>NIP. ..............................</small></div>
    </div>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>