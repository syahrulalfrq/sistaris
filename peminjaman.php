<?php
/**
 * Daftar peminjaman.
 * Admin: semua permintaan + setujui / tolak / tandai kembali / hapus riwayat.
 * Pengguna: hanya peminjaman miliknya (boleh dibatalkan saat masih menunggu).
 */
require __DIR__ . '/includes/bootstrap.php';
auth();

$admin  = is_admin();
$status = $_GET['status'] ?? '';
if (!isset(STATUS_PINJAM[$status])) { $status = ''; }

// ---- Filter periode laporan: bulan & tahun, boleh lebih dari satu (mis. bulan=1,3&tahun=2025,2026) ----
$daftarAngka = function ($v, int $min, int $max): array {
    $v = is_array($v) ? $v : explode(',', (string) $v);
    $out = [];
    foreach ($v as $x) {
        $x = trim((string) $x);
        if (ctype_digit($x) && (int) $x >= $min && (int) $x <= $max) { $out[(int) $x] = (int) $x; }
    }
    sort($out);
    return array_values($out);
};
$blnList = $daftarAngka($_GET['bulan'] ?? '', 1, 12);
$thnList = $daftarAngka($_GET['tahun'] ?? '', 2000, 2100);
if (!$admin) { $blnList = []; $thnList = []; } // filter periode & cetak laporan hanya untuk admin/superadmin
$flt = array_filter(['bulan' => implode(',', $blnList), 'tahun' => implode(',', $thnList)]);

$NAMA_BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Syarat umum (hak akses + periode) dipakai untuk daftar maupun hitungan tab.
$wu = [];
$pu = [];
if (!$admin) { $wu[] = 'p.id_user = ?'; $pu[] = user_id(); }
if ($blnList) { $wu[] = 'MONTH(p.tgl_pinjam) IN (' . implode(',', array_fill(0, count($blnList), '?')) . ')'; foreach ($blnList as $v) { $pu[] = $v; } }
if ($thnList) { $wu[] = 'YEAR(p.tgl_pinjam) IN (' . implode(',', array_fill(0, count($thnList), '?')) . ')';  foreach ($thnList as $v) { $pu[] = $v; } }

// ---- Mode cetak: pilihan status laporan (boleh lebih dari satu, atau 'semua') ----
$modeCetak  = $admin && isset($_GET['cetak']);
$cetakSemua = false;
$cetakList  = [];
if ($modeCetak) {
    $mentah = is_array($_GET['cetak']) ? $_GET['cetak'] : explode(',', (string) $_GET['cetak']);
    foreach ($mentah as $c) {
        $c = trim((string) $c);
        if ($c === 'semua') { $cetakSemua = true; }
        elseif ($c !== 'dibatalkan' && isset(STATUS_PINJAM[$c])) { $cetakList[$c] = true; }
    }
    $cetakList = array_keys($cetakList);
    if (!$cetakList) { $cetakSemua = true; }
    if ($cetakSemua) { $cetakList = []; }
}

$w = $wu;
$p = $pu;
if ($modeCetak && $cetakList) {
    $w[] = 'p.status IN (' . implode(',', array_fill(0, count($cetakList), '?')) . ')';
    foreach ($cetakList as $c) { $p[] = $c; }
} elseif ($modeCetak) {
    $w[] = "p.status != 'dibatalkan'";
} elseif ($status) {
    $w[] = 'p.status = ?';
    $p[] = $status;
} else {
    // Sembunyikan status dibatalkan jika berada di tab 'Semua'
    $w[] = "p.status != 'dibatalkan'";
}
$where = $w ? ' WHERE ' . implode(' AND ', $w) : '';

// Tanpa filter periode: 200 data terbaru. Dengan filter (mode laporan): tampilkan semua agar cetakan lengkap.
$batas = ($flt || $modeCetak) ? 5000 : 200;

$s = $db->prepare('SELECT p.*, b.nama_barang, b.kode_barang, b.satuan, l.nama_lab
    FROM peminjaman p
    JOIN barang b ON b.id_barang = p.id_barang
    JOIN lab l ON l.id_lab = b.id_lab' . $where . '
    ORDER BY FIELD(p.status, \'pending\', \'disetujui\', \'dikembalikan\', \'ditolak\', \'dibatalkan\'), p.id_pinjam DESC
    LIMIT ' . $batas);
$s->execute($p);
$rows = $s->fetchAll();

// Hitungan per status untuk tab (mengikuti filter periode)
$q = $db->prepare('SELECT p.status, COUNT(*) FROM peminjaman p' . ($wu ? ' WHERE ' . implode(' AND ', $wu) : '') . ' GROUP BY p.status');
$q->execute($pu);
$hit = $q->fetchAll(PDO::FETCH_KEY_PAIR);

// Abaikan status dibatalkan pada counter total
$hit_temp = $hit;
unset($hit_temp['dibatalkan']);
$total_hit = array_sum($hit_temp);

// Pilihan tahun: dari tahun peminjaman paling awal sampai tahun ini
$thn_ini = (int) date('Y');
$thn_min = (int) $db->query('SELECT MIN(YEAR(tgl_pinjam)) FROM peminjaman')->fetchColumn();
$thn_min = $thn_min >= 2000 ? min($thn_min, $thn_ini) : $thn_ini;
$thn_list = [];
for ($y = $thn_ini; $y >= $thn_min; $y--) { $thn_list[$y] = $y; }
foreach ($thnList as $y) { $thn_list[$y] = $y; }
krsort($thn_list);

// Keterangan periode untuk judul cetakan
$ket = [];
if ($blnList) { $ket[] = 'Bulan ' . implode(', ', array_map(fn($m) => $NAMA_BULAN[$m], $blnList)); }
if ($thnList) { $ket[] = 'Tahun ' . implode(', ', $thnList); }
if (!$ket)    { $ket[] = 'Semua periode'; }
if ($modeCetak && $cetakList) { $ket[] = 'Status: ' . implode(', ', array_map(fn($c) => STATUS_PINJAM[$c][0], $cetakList)); }
elseif ($modeCetak)    { $ket[] = 'Status: Semua'; }
elseif ($status)       { $ket[] = 'Status: ' . STATUS_PINJAM[$status][0]; }

// Ringkasan data yang tampil
$ringkas = [];
$tot_unit = 0;
foreach ($rows as $r) {
    $ringkas[$r['status']] = ($ringkas[$r['status']] ?? 0) + 1;
    $tot_unit += (int) $r['jumlah'];
}

// Alamat tab: pertahankan filter periode
$qs_flt = fn(array $tambah = []) => 'peminjaman.php' . (($a = http_build_query($tambah + $flt)) !== '' ? '?' . $a : '');

$hari  = date('Y-m-d');
$title = $admin ? 'Peminjaman Barang' : 'Peminjaman Saya';
$crumb = [$title];
require __DIR__ . '/includes/header.php';
?>

<style>
.btn-surat{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;font-size:.82rem;font-weight:600;line-height:1.2;white-space:nowrap;text-decoration:none;color:#1d4ed8;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;transition:all .2s}
.btn-surat:hover{background:#2563eb;border-color:#2563eb;color:#fff;box-shadow:0 4px 10px rgba(37,99,235,.25)}
.btn.wa{display:inline-flex;align-items:center;gap:6px;background:#25d366;color:#fff;text-decoration:none}
.btn.wa:hover{background:#1ebe57}
.btn.wa .i{width:14px;height:14px}
.surat-kosong{display:inline-block;padding:4px 10px;font-size:.78rem;color:#64748b;background:#f1f5f9;border:1px dashed #cbd5e1;border-radius:6px;white-space:nowrap}

/* Filter periode laporan */
.pjm-ket{font-size:13px;color:var(--mu);margin:0 0 10px}
.pjm-ket b{color:var(--gd)}
.pjm-sum{display:flex;gap:6px 18px;flex-wrap:wrap}

/* Filter bulan & tahun (otomatis, ringkas) */
.pjm-filter{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:flex-end;gap:8px 12px;margin:0 0 0 auto}
.t-wrap{display:flex;align-items:center;height:34px;margin:0 0 6px;border:1px solid var(--line);border-radius:6px;background:#fff;overflow:hidden}
.t-wrap:focus-within{border-color:var(--g)}
.t-pre{padding:0 2px 0 10px;font-size:14px;font-weight:700;color:var(--gd);user-select:none}
.dd-menu .t-wrap input[type=text]{flex:1;width:auto;height:100%;margin:0;padding:0 10px 0 0;border:0;outline:0;background:transparent}
.f-cetak .dd-menu{left:auto;right:0}
/* menu dropdown harus tampil di atas tab & tabel di bawahnya */
.main .toolbar.noprint{position:relative;z-index:10} /* di bawah menu navigasi (.nav z-index:20), di atas tab & tabel */
.main .toolbar.noprint .dd-menu{z-index:40}
.pjm-filter .f{display:flex;flex-direction:column}
.pjm-filter .fl{font-size:12.5px;font-weight:600;color:var(--gd);margin:0 0 3px;line-height:1.2}
.dd{position:relative}
.dd-btn{display:flex;align-items:center;justify-content:space-between;gap:10px;width:190px;max-width:100%;height:36px;padding:0 12px;font:inherit;font-size:14px;color:var(--tx);background:#fff;border:1px solid var(--line);border-radius:6px;cursor:pointer;text-align:left}
.dd-btn:hover,.dd.open>.dd-btn{border-color:var(--g)}
.dd-btn::after{content:'\25BE';font-size:.85em;color:var(--mu)}
.dd-txt{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dd-menu{display:none;position:absolute;left:0;top:calc(100% + 4px);z-index:40;min-width:100%;width:max-content;max-width:calc(100vw - 32px);background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 8px 20px rgba(0,0,0,.14);padding:8px 10px}
.dd.open>.dd-menu{display:block}
.dd-menu p{margin:0 0 4px;font-size:12.5px;font-weight:700;color:var(--gd)}
.dd-menu label{display:flex;align-items:center;gap:8px;margin:0;padding:5px 6px;font-size:14px;font-weight:500;line-height:1.3;cursor:pointer;border-radius:5px;white-space:nowrap}
.dd-menu label:hover{background:var(--gl)}
.dd-menu label.off{opacity:.45;cursor:not-allowed}
.dd-menu .all{font-weight:700;color:var(--gd)}
.dd-menu input[type=checkbox]{width:auto;margin:0}
.dd-menu input[type=text]{width:100%;height:34px;margin:0 0 6px;padding:0 10px;font-size:14px}
.dd-menu hr{border:0;border-top:1px solid var(--line);margin:4px 0 6px}
.dd-menu .grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 10px}
.dd-menu .btn{width:100%;margin-top:8px;justify-content:center}
.dd-menu .btn[disabled]{opacity:.5;cursor:not-allowed}
.cetak-menu{min-width:230px}
.cetak-dd>.btn::after{content:'\25BE';margin-left:6px;font-size:.8em}
.pjm-filter .f-cetak>.btn,.pjm-filter .f-cetak>.dd>.btn{height:36px;display:inline-flex;align-items:center;padding-top:0;padding-bottom:0}
.cetak-info{display:flex;gap:8px 12px;flex-wrap:wrap;align-items:center;background:var(--gl);border:1px solid var(--line);border-radius:8px;padding:8px 14px;margin-bottom:10px;font-size:13.5px}
@media (max-width:640px){.pjm-filter{width:100%;margin:0}.pjm-filter .f{flex:1 1 140px}.dd-btn{width:100%}}

/* Kolom Aksi: isi sejajar tengah dengan baris (tampilan tombol tidak diubah) */
@media (min-width:1025px){
    .pjm td.acts{display:table-cell;vertical-align:middle;white-space:nowrap}
    .pjm td.acts>*{display:inline-flex;vertical-align:middle;margin:2px 6px 2px 0}
}

td.kosong-aksi{padding:0!important;border:0!important}
@media (max-width:1024px){td.kosong-aksi{display:none!important}}

/* Cetak laporan peminjaman */
@media print{
    /* Saat cetak, lebar halaman < 1024px sehingga aturan "tabel jadi kartu" versi HP ikut aktif.
       Kembalikan ke tabel biasa (kolom berjajar ke samping). */
    table.r.pjm{display:table!important;width:186mm!important;max-width:186mm;table-layout:fixed;border-collapse:collapse;margin:0}
    table.r.pjm thead{display:table-header-group!important}
    table.r.pjm tbody{display:table-row-group!important;width:auto!important}
    table.r.pjm tfoot{display:table-footer-group!important}
    table.r.pjm tr{display:table-row!important;padding:0!important;margin:0!important;border:0!important}
    table.r.pjm th,table.r.pjm td{display:table-cell!important;padding:2.6pt 4pt!important;text-align:left!important;white-space:normal!important}
    table.r.pjm th{text-align:center!important}
    table.r.pjm td:first-child{text-align:center!important;padding-left:1.5pt!important;padding-right:1.5pt!important}
    table.r.pjm td::before,table.r.pjm td::after{content:none!important;display:none!important}
    table.r.pjm td.empty{text-align:center!important;padding:8pt 4pt!important;font-style:italic}
    table.r.pjm td.kosong-aksi{display:none!important}
    table.r.pjm th{vertical-align:middle!important;word-break:keep-all;overflow-wrap:normal}
    .pjm th,.pjm td{border:.7pt solid #000!important;padding:2.6pt 4pt;font-size:8.6pt;line-height:1.3;text-align:left;vertical-align:top;white-space:normal;overflow-wrap:anywhere;min-width:0;max-width:none}
    .pjm th{text-align:center;vertical-align:middle;font-weight:700;text-transform:none;letter-spacing:0;background:#e6e6e6!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .pjm thead{display:table-header-group}
    .pjm tr{break-inside:avoid;page-break-inside:avoid}
    .pjm .sub-ket{display:block;font-size:7.8pt;margin-top:1pt}
    .pjm .badge{display:inline;padding:0;border:0;font-weight:400;font-size:8.6pt}
    /* Kolom Aksi & isi layar tidak ikut dicetak (aturan display:table-cell di atas mengalahkan .noprint, jadi disembunyikan lagi di sini) */
    table.r.pjm th.noprint,table.r.pjm td.noprint,table.r.pjm td.acts,.pjm .surat-layar{display:none!important}
    table.r.pjm td.td-surat,table.r.pjm th.w-srt{text-align:center!important}
    .pjm th.w-no{width:8mm}.pjm th.w-pem{width:28mm}.pjm th.w-brg{width:32mm}.pjm th.w-jml{width:14mm}.pjm th.w-tgl{width:36mm}.pjm th.w-kep{width:24mm}.pjm th.w-srt{width:14mm}.pjm th.w-sta{width:30mm}
    .doc .panel-foot{border:.7pt solid #000!important;border-top:0!important;padding:3pt 4pt;font-size:8.6pt;display:block}
    .doc .panel-foot>span{display:inline-block;margin-right:14pt}

    /* ===== Perapian cetak (PC & HP) ===== */
    html{-webkit-text-size-adjust:100%!important;text-size-adjust:100%!important}
    .doc,.doc .panel,.doc .scroll{box-sizing:border-box}
    table.r.pjm{font-size:9pt}
    table.r.pjm th,table.r.pjm td{box-sizing:border-box;font-size:9pt!important;line-height:1.35;padding:3.5pt 4pt!important;overflow:hidden;overflow-wrap:anywhere;word-break:normal;vertical-align:middle!important}
    table.r.pjm th{padding:4.5pt 3pt!important;font-size:9pt!important;white-space:normal!important}
    /* badge status: boleh turun baris, tanpa kotak, supaya tidak keluar dari kolom */
    .pjm .badge{display:inline!important;white-space:normal!important;padding:0!important;border:0!important;font-weight:700!important;font-size:9pt!important;line-height:1.3}
    .pjm .badge+.badge{display:block!important;margin-top:1.5pt;font-weight:400!important}
    table.r.pjm td b{font-weight:700}
    table.r.pjm td .sub-ket{display:block;font-size:7.8pt!important;line-height:1.3;margin-top:1.5pt;white-space:normal!important}
    /* teks panjang (keperluan) rata kiri; kolom pendek di tengah */
    table.r.pjm td[data-label="No"],table.r.pjm td[data-label="Jumlah"],table.r.pjm td[data-label="Surat"],table.r.pjm td[data-label="Status"]{text-align:center!important}
    table.r.pjm td[data-label="Peminjam"],table.r.pjm td[data-label="Barang"],table.r.pjm td[data-label="Tanggal"],table.r.pjm td[data-label="Keperluan"]{text-align:left!important}
    table.r.pjm td[data-label="Keperluan"]{vertical-align:top!important}
    table.r.pjm td[data-label="Peminjam"],table.r.pjm td[data-label="Barang"],table.r.pjm td[data-label="Tanggal"]{vertical-align:top!important}
    table.r.pjm tbody tr:nth-child(even) td{background:#f6f6f6!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .doc .panel-foot{width:100%;box-sizing:border-box;padding:4pt 6pt;font-size:9pt}
    .doc .pjm-sum{display:block}
    .doc .pjm-sum>span{display:inline-block;margin-right:14pt}
    .ttd{margin-top:22pt}
    .ttd>div{width:44%}
}
</style>

<div class="toolbar noprint">
    <p class="muted"><?= $admin ? 'Setujui atau tolak permintaan pinjam. Unit yang disetujui tetap dihitung sebagai aset ' . SEKOLAH . '.' : 'Pengajuan Anda berstatus menunggu sampai disetujui admin.' ?></p>
    <?php if (!$admin): ?><a class="btn" href="barang.php">+ Pinjam Barang</a><?php endif; ?>
<?php if ($admin): ?>
<form class="pjm-filter noprint" method="get" action="peminjaman.php" onsubmit="return false">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="f">
        <span class="fl">Bulan</span>
        <div class="dd" id="dd-bulan">
            <button type="button" class="dd-btn" aria-haspopup="true" aria-expanded="false"><span class="dd-txt">Semua bulan</span></button>
            <div class="dd-menu">
                <label class="all"><input type="checkbox" id="b-semua"<?= $blnList ? '' : ' checked' ?>> Semua bulan</label>
                <hr>
                <div class="grid2">
                <?php foreach ($NAMA_BULAN as $k => $nm): ?>
                    <label><input type="checkbox" class="b-item" value="<?= (int) $k ?>" data-nama="<?= e($nm) ?>"<?= in_array($k, $blnList, true) ? ' checked' : '' ?>> <?= e($nm) ?></label>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="f">
        <span class="fl">Tahun</span>
        <div class="dd" id="dd-tahun">
            <button type="button" class="dd-btn" aria-haspopup="true" aria-expanded="false"><span class="dd-txt">Semua tahun</span></button>
            <div class="dd-menu">
                <label class="all"><input type="checkbox" id="t-semua"<?= $thnList ? '' : ' checked' ?>> Semua tahun</label>
                <hr>
                <div class="t-wrap"><span class="t-pre">20</span><input type="text" id="t-input" inputmode="numeric" maxlength="2" placeholder="26" autocomplete="off" aria-label="Dua angka terakhir tahun, awalan 20 sudah terisi"></div>
                <div class="grid2" id="t-list">
                <?php foreach ($thn_list as $y): ?>
                    <label><input type="checkbox" class="t-item" value="<?= (int) $y ?>"<?= in_array($y, $thnList, true) ? ' checked' : '' ?>> <?= (int) $y ?></label>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="f f-cetak">
        <?php if ($status === ''): ?>
        <div class="dd cetak-dd" id="cetak-dd">
            <button type="button" class="btn alt" id="cetak-btn" aria-haspopup="true" aria-expanded="false">Cetak Laporan</button>
            <div class="dd-menu cetak-menu" id="cetak-menu" role="group" aria-label="Pilih status yang dicetak">
                <p>Status yang dicetak</p>
                <label class="all"><input type="checkbox" value="semua" id="cetak-semua"<?= ($modeCetak ? $cetakSemua : true) ? ' checked' : '' ?>> Semua</label>
                <hr>
                <?php foreach (STATUS_PINJAM as $k => [$label]): if ($k === 'dibatalkan') continue; ?>
                    <label><input type="checkbox" class="cetak-item" value="<?= e($k) ?>"<?= ($modeCetak && in_array($k, $cetakList, true)) ? ' checked' : '' ?>> <?= e($label) ?></label>
                <?php endforeach; ?>
                <button type="button" class="btn" id="cetak-go">Cetak</button>
            </div>
        </div>
        <?php else: ?>
        <button type="button" class="btn alt" onclick="window.print()">Cetak Laporan</button>
        <?php endif; ?>
    </div>
</form>
<?php endif; ?>
</div>


<p class="pjm-ket noprint">Menampilkan <b><?= count($rows) ?></b> peminjaman &mdash; <?= e(implode(' | ', $ket)) ?><?php if ($admin): ?>. Pilih bulan/tahun lalu klik <b>Cetak Laporan</b>; data yang tercetak sama dengan yang tampil di bawah<?php endif; ?>.</p>

<?php if ($modeCetak): ?>
<div class="cetak-info noprint">
    <span>Mode cetak &mdash; <b><?= e(implode(' | ', $ket)) ?></b></span>
    <button type="button" class="btn sm" onclick="window.print()">Cetak lagi</button>
    <a class="btn sm alt" href="<?= e($qs_flt()) ?>">Kembali</a>
</div>
<?php endif; ?>

<div class="tabs" role="tablist">
    <a href="<?= e($qs_flt()) ?>"<?= $status === '' ? ' class="on"' : '' ?>>Semua <span><?= $total_hit ?></span></a>
    <?php foreach (STATUS_PINJAM as $k => [$label]): ?>
        <?php if ($k === 'dibatalkan') continue; // Sembunyikan tab Dibatalkan ?>
        <a href="<?= e($qs_flt(['status' => $k])) ?>"<?= $status === $k ? ' class="on"' : '' ?>><?= e($label) ?> <span><?= (int) ($hit[$k] ?? 0) ?></span></a>
    <?php endforeach; ?>
</div>

<article class="doc">
    <?php require __DIR__ . '/includes/kop.php'; ?>
    <h3 class="judul only-print"><?= $admin ? 'LAPORAN PEMINJAMAN BARANG LABORATORIUM' : 'RIWAYAT PEMINJAMAN BARANG' ?></h3>
    <p class="subjudul only-print"><?= e(implode(' | ', $ket)) ?></p>

<section class="panel">
    <div class="scroll">
    <table class="r pjm">
        <thead><tr>
            <th class="w-no">No</th><?php if ($admin): ?><th class="w-pem">Peminjam</th><?php endif; ?><th class="w-brg">Barang</th><th class="w-jml">Jumlah</th>
            <th class="w-tgl">Tanggal</th><th class="<?= $admin ? 'w-kep' : '' ?>">Keperluan</th><th class="w-srt">Surat</th><th class="w-sta">Status</th><th class="noprint">Aksi</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $r):
            [$lbl, $kls] = STATUS_PINJAM[$r['status']];
            $nama  = $r['nama_barang'] . ' (' . $r['kode_barang'] . ') ' . $r['jumlah'] . ' ' . $r['satuan'] . ' - ' . $r['nama_peminjam'];
        ?>
            <tr>
                <td data-label="No"><?= $i + 1 ?></td>
                <?php if ($admin): ?>
                <td data-label="Peminjam"><b><?= e($r['nama_peminjam']) ?></b><small class="sub-ket"><?= e($r['no_hp']) ?></small></td>
                <?php endif; ?>
                <td data-label="Barang"><?= e($r['nama_barang']) ?><small class="sub-ket"><?= e($r['kode_barang']) ?> &middot; <?= e($r['nama_lab']) ?></small></td>
                <td data-label="Jumlah"><?= (int) $r['jumlah'] . ' ' . e($r['satuan']) ?></td>
                <td data-label="Tanggal"><?= tgl($r['tgl_pinjam']) ?><?php if (!empty($r['waktu_pinjam'])): ?><small class="sub-ket">Dipinjam pukul <?= date('H.i', strtotime($r['waktu_pinjam'])) ?> WIB</small><?php elseif (!empty($r['created_at'])): ?><small class="sub-ket">Diajukan pukul <?= date('H.i', strtotime($r['created_at'])) ?> WIB</small><?php endif; ?><?php if (!empty($r['tgl_kembali_rencana'])): ?><small class="sub-ket<?= pinjam_terlambat($r) ? ' telat-teks' : '' ?>">Batas kembali: <?= tgl($r['tgl_kembali_rencana']) ?><?php if (pinjam_terlambat($r)): ?> (terlambat <?= hari_terlambat($r) ?> hari)<?php endif; ?></small><?php endif; ?><?php if ($r['tgl_kembali']): ?><small class="sub-ket">Kembali: <?= tgl($r['tgl_kembali']) ?></small><?php endif; ?></td>
                <td data-label="Keperluan" class="wrap"><?= e($r['keperluan']) ?></td>
                <td data-label="Surat" class="td-surat"><span class="only-print"><?= !empty($r['surat_file']) ? 'Ada' : 'Tidak ada' ?></span><span class="surat-layar noprint"><?php if (!empty($r['surat_file'])): ?><a class="btn-surat" href="surat.php?id=<?= (int) $r['id_pinjam'] ?>" target="_blank" rel="noopener" title="<?= e($r['surat_nama']) ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Lihat Surat</a><?php else: ?><span class="surat-kosong">Tanpa surat</span><?php endif; ?></span></td>
                <td data-label="Status"><span class="badge b-<?= $kls ?>"><?= e($lbl) ?></span><?php if (pinjam_terlambat($r)): ?> <span class="badge b-bad">Terlambat</span><?php endif; ?></td>
                <td data-label="Aksi" class="acts noprint">
                <?php
                    $form = function (string $aksi, string $teks, string $kelas, string $jenis) use ($r, $nama, $status) {
                        echo '<form method="post" action="peminjaman_aksi.php" class="aksi" data-konfirm="' . $jenis . '" data-nama="' . e($nama) . '">'
                           . '<input type="hidden" name="csrf" value="' . csrf() . '">'
                           . '<input type="hidden" name="id" value="' . (int) $r['id_pinjam'] . '">'
                           . '<input type="hidden" name="aksi" value="' . $aksi . '">'
                           . '<input type="hidden" name="status" value="' . e($status) . '">'
                           . '<button class="btn sm ' . $kelas . '">' . $teks . '</button></form>';
                    };

                    if ($admin && $r['status'] === 'pending') {
                        $form('setujui', 'Setujui', '', 'setuju');
                        $form('tolak', 'Tolak', 'del', 'tolak');
                    } elseif ($admin && $r['status'] === 'disetujui') {
                        // Follow up peminjam lewat WhatsApp (pesan pengingat sudah terisi otomatis).
                        $hp = normal_hp($r['no_hp']);
                        if ($hp !== '') {
                            echo '<a class="btn sm wa" href="' . e(wa_link($hp, pesan_tagih($r))) . '" target="_blank" rel="noopener" title="Chat ' . e($r['nama_peminjam']) . ' lewat WhatsApp">'
                               . ikon('whatsapp') . 'Chat Peminjam' . '</a>';
                        } else {
                            echo '<span class="surat-kosong" title="Nomor HP peminjam tidak valid">No. HP tidak valid</span>';
                        }
                        $form('kembali', 'Tandai Kembali', '', 'kembali');
                    } elseif (!$admin && $r['status'] === 'pending') {
                        $form('batal', 'Batalkan', 'del', 'batal');
                    }

                    // Hanya tampilkan tombol Hapus pada status Dikembalikan dan Ditolak
                    if (is_superadmin() && in_array($r['status'], ['dikembalikan', 'ditolak'], true)) {
                        $form('hapus', 'Hapus', 'del', 'hapus');
                    }
                ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="<?= $admin ? 8 : 7 ?>" class="empty">Belum ada data peminjaman<?= $status ? ' dengan status ini' : '' ?>.</td><td class="noprint kosong-aksi"></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <?php if ($rows): ?>
    <div class="panel-foot">
        <span class="pjm-sum">
            <span>Total: <b><?= count($rows) ?></b> peminjaman (<b><?= $tot_unit ?></b> unit)</span>
            <?php foreach (STATUS_PINJAM as $k => [$label]): if (empty($ringkas[$k])) continue; ?>
                <span><?= e($label) ?>: <b><?= $ringkas[$k] ?></b></span>
            <?php endforeach; ?>
        </span>
    </div>
    <?php endif; ?>
</section>

<?php if ($admin): ?>
    <div class="ttd only-print">
        <div><p>Mengetahui,<br>Kepala Madrasah</p><span>(.....................................)</span><small>NIP. ..............................</small></div>
        <div><p>Klaten, <?= tgl_id() ?><br>Pengelola Laboratorium</p><span>(.....................................)</span><small>NIP. ..............................</small></div>
    </div>
<?php endif; ?>
</article>
<script>
(function () {
    var f = document.querySelector('.pjm-filter');
    if (!f) return;
    var $ = function (id) { return document.getElementById(id); };
    var qsa = function (sel, root) { return [].slice.call((root || f).querySelectorAll(sel)); };
    var dds = qsa('.dd');

    // ---- grup centang Bulan & Tahun ----
    var B = {
        semua: $('b-semua'), txt: qsa('#dd-bulan .dd-txt')[0],
        items: function () { return qsa('.b-item'); },
        label: function () {
            if (B.semua.checked) return 'Semua bulan';
            var n = B.items().filter(function (c) { return c.checked; }).map(function (c) { return c.getAttribute('data-nama'); });
            return !n.length ? 'Pilih bulan' : (n.length <= 2 ? n.join(', ') : n.length + ' bulan dipilih');
        }
    };
    var T = {
        semua: $('t-semua'), txt: qsa('#dd-tahun .dd-txt')[0], input: $('t-input'), list: $('t-list'),
        items: function () { return qsa('.t-item'); },
        label: function () {
            if (T.semua.checked) return 'Semua tahun';
            var n = T.items().filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
            return !n.length ? 'Pilih tahun' : (n.length <= 3 ? n.join(', ') : n.length + ' tahun dipilih');
        }
    };
    function sinkron(g) {
        g.items().forEach(function (c) {
            if (g.semua.checked) c.checked = false;
            c.disabled = g.semua.checked;
            c.parentNode.classList.toggle('off', g.semua.checked);
        });
        if (g.input) g.input.disabled = g.semua.checked;
        g.txt.textContent = g.label();
    }
    function kosong(g) { return !g.items().some(function (c) { return c.checked; }); }
    function pulihkan(g) { if (!g.semua.checked && kosong(g)) { g.semua.checked = true; sinkron(g); } }

    function param() {
        var q = [];
        [[B, 'bulan'], [T, 'tahun']].forEach(function (x) {
            if (x[0].semua.checked) return;
            var v = x[0].items().filter(function (c) { return c.checked; }).map(function (c) { return parseInt(c.value, 10); })
                .sort(function (a, b) { return a - b; });
            if (v.length) q.push(x[1] + '=' + v.join(','));
        });
        return q;
    }

    // ---- terapkan otomatis (tanpa tombol Tampilkan) ----
    var timer = null;
    function terapkan() {
        var q = param(), st = f.elements['status'];
        if (st && st.value) q.unshift('status=' + encodeURIComponent(st.value));
        var buka = dds.filter(function (d) { return d.classList.contains('open') && d.id !== 'cetak-dd'; })[0];
        try { sessionStorage.setItem('pjmOpen', buka ? buka.id : ''); } catch (e) {}
        location.href = 'peminjaman.php' + (q.length ? '?' + q.join('&') : '');
    }
    function tunda() { clearTimeout(timer); timer = setTimeout(terapkan, 450); }

    [B, T].forEach(function (g) {
        g.semua.addEventListener('change', function () { sinkron(g); if (g.semua.checked) tunda(); });
    });
    $('dd-bulan').addEventListener('change', function (e) {
        if (!e.target.classList.contains('b-item')) return;
        if (kosong(B)) B.semua.checked = true;
        sinkron(B); tunda();
    });
    T.list.addEventListener('change', function (e) {
        if (!e.target.classList.contains('t-item')) return;
        if (kosong(T)) T.semua.checked = true;
        sinkron(T); tunda();
    });

    // Tahun: cukup ketik 2 angka terakhir (26 = 2026), langsung ditambahkan & ditampilkan
    T.input.addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '').slice(0, 2);
        this.value = v;
        if (v.length < 2) return;
        var y = String(2000 + parseInt(v, 10));
        var ada = T.items().filter(function (c) { return c.value === y; })[0];
        if (!ada) {
            var lb = document.createElement('label'), cb = document.createElement('input');
            cb.type = 'checkbox'; cb.className = 't-item'; cb.value = y;
            lb.appendChild(cb); lb.appendChild(document.createTextNode(' ' + y));
            var sebelum = null;
            T.items().some(function (c) { if (parseInt(c.value, 10) < parseInt(y, 10)) { sebelum = c.parentNode; return true; } return false; });
            T.list.insertBefore(lb, sebelum);
            ada = cb;
        }
        ada.checked = true;
        this.value = '';
        sinkron(T); tunda();
    });

    // ---- buka / tutup dropdown ----
    function tutupSemua() {
        dds.forEach(function (d) {
            if (!d.classList.contains('open')) return;
            d.classList.remove('open'); d.firstElementChild.setAttribute('aria-expanded', 'false');
        });
        pulihkan(B); pulihkan(T);
    }
    dds.forEach(function (d) {
        var b = d.firstElementChild;
        b.addEventListener('click', function (e) {
            e.stopPropagation();
            var buka = !d.classList.contains('open');
            tutupSemua();
            if (buka) { d.classList.add('open'); b.setAttribute('aria-expanded', 'true'); if (d.id === 'dd-tahun' && !T.semua.checked) T.input.focus(); }
        });
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('.dd')) tutupSemua(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutupSemua(); });

    sinkron(B); sinkron(T);
    try {   // buka lagi dropdown yang tadi dipakai supaya bisa lanjut memilih
        var id = sessionStorage.getItem('pjmOpen');
        sessionStorage.removeItem('pjmOpen');
        if (id && $(id)) { $(id).classList.add('open'); $(id).firstElementChild.setAttribute('aria-expanded', 'true'); if (id === 'dd-tahun' && !T.semua.checked) T.input.focus(); }
    } catch (e) {}

    // ---- Cetak Laporan (tab Semua): pilih status ----
    var cd = $('cetak-dd');
    if (cd) {
        var cs = $('cetak-semua'), cg = $('cetak-go'), ci = qsa('.cetak-item', cd);
        var cs_sinkron = function () {
            ci.forEach(function (c) {
                if (cs.checked) c.checked = false;
                c.disabled = cs.checked;
                c.parentNode.classList.toggle('off', cs.checked);
            });
            cg.disabled = !cs.checked && !ci.some(function (c) { return c.checked; });
        };
        cs.addEventListener('change', cs_sinkron);
        ci.forEach(function (c) { c.addEventListener('change', cs_sinkron); });
        cs_sinkron();
        cg.addEventListener('click', function () {
            if (cg.disabled) return;
            var pilih = cs.checked ? ['semua'] : ci.filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
            var q = param();
            q.push('cetak=' + pilih.join(','));
            q.push('auto=1');
            window.open('peminjaman.php?' + q.join('&'), '_blank');
            cd.classList.remove('open');
        });
    }
<?php if ($modeCetak && !empty($_GET['auto'])): ?>
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
<?php endif; ?>
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>