<?php
/**
 * Data Barang: daftar, pencarian langsung (AJAX), filter lab & kondisi.
 * Letakkan di folder utama (sejajar index.php), bukan di includes/.
 * Memakai: includes/barang_hasil.php (tabel), includes/header.php & footer.php
 */
require __DIR__ . '/includes/bootstrap.php';
auth();

// ---- Filter (nilai dipaksa string/int agar aman dari ?cari[]=x dsb.) ----
$cari = $_GET['cari'] ?? '';
$cari = is_string($cari) ? mb_substr(trim($cari), 0, 100) : '';

$lab = (int) ($_GET['lab'] ?? 0);
if (!isset($labs[$lab])) { $lab = 0; }          // lab tidak dikenal => semua lab

$kon = $_GET['kondisi'] ?? '';
$kon = is_string($kon) ? $kon : '';

$w = [];
$p = [];
if ($cari !== '') {
    // Karakter khusus LIKE (% dan _) dianggap huruf biasa.
    $like = '%' . addcslashes($cari, '%_\\') . '%';
    $w[]  = '(b.nama_barang LIKE ? OR b.kode_barang LIKE ? OR b.merk LIKE ? OR k.nama_kategori LIKE ? OR l.nama_lab LIKE ? OR b.keterangan LIKE ?)';
    array_push($p, $like, $like, $like, $like, $like, $like);
}
if ($lab)                 { $w[] = 'b.id_lab = ?';   $p[] = $lab; }
if (isset(KONDISI[$kon])) { $w[] = 'b.kondisi = ?'; $p[] = $kon; }
$where = $w ? ' WHERE ' . implode(' AND ', $w) : '';

$dari = ' FROM barang b
    JOIN kategori k ON k.id_kategori = b.id_kategori
    JOIN lab l ON l.id_lab = b.id_lab';

// ---- Total jenis & total aset ----
$s = $db->prepare('SELECT COUNT(*) AS jenis, COALESCE(SUM(b.jumlah), 0) AS stok' . $dari . $where);
$s->execute($p);
$total = $s->fetch();

// Unit yang sedang dipinjam (di luar madrasah, tetap dihitung aset MTsN 1 Klaten)
$s = $db->prepare('SELECT COALESCE(SUM(p.jumlah), 0)' . $dari
    . ' JOIN peminjaman p ON p.id_barang = b.id_barang AND p.status = \'disetujui\'' . $where);
$s->execute($p);
$total['dipinjam'] = (int) $s->fetchColumn();
$total['tersedia'] = max(0, (int) $total['stok'] - $total['dipinjam']);

// ---- Halaman ----
$pages  = max(1, (int) ceil($total['jenis'] / PER_HALAMAN));
$page   = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * PER_HALAMAN;

// ---- Data ----
$s = $db->prepare('SELECT b.*, k.nama_kategori, l.nama_lab, ' . SQL_DIPINJAM . ' AS dipinjam' . $dari . $where . '
    ORDER BY b.id_lab ASC,
             SUBSTRING_INDEX(b.kode_barang, \'-\', 1) ASC,
             CAST(SUBSTRING_INDEX(b.kode_barang, \'-\', -1) AS UNSIGNED) ASC,
             b.id_barang ASC
    LIMIT ' . (int) PER_HALAMAN . ' OFFSET ' . (int) $offset);
$s->execute($p);
$rows = $s->fetchAll();

$title = $labs[$lab] ?? 'Data Barang';
$admin = is_admin();
$kolom = $lab ? 9 : 10;   // jumlah kolom tabel (kolom Laboratorium disembunyikan saat filter lab)

// ---- Pencarian langsung (AJAX): kirim bagian hasil saja ----
if (isset($_GET['partial'])) {
    header('Cache-Control: no-store');
    require __DIR__ . '/includes/barang_hasil.php';
    exit;
}

$crumb = $lab ? ['Laboratorium', $title] : [$title];
require __DIR__ . '/includes/header.php';
?>

<div class="toolbar">
    <p class="muted">Tekan <kbd>/</kbd> untuk langsung mengetik pencarian.</p>
    <?php if ($admin): ?>
        <a class="btn" href="form.php<?= $lab ? '?lab=' . $lab : '' ?>">+ Tambah Barang</a>
    <?php else: ?>
        <a class="btn alt" href="peminjaman.php">Peminjaman Saya</a>
    <?php endif; ?>
</div>

<form class="filter" method="get" id="filter" data-live="#hasil" role="search">
    <?php if ($lab): ?><input type="hidden" name="lab" value="<?= $lab ?>"><?php endif; ?>
    <input type="search" name="cari" id="cari" value="<?= e($cari) ?>" placeholder="Cari kode, nama, merk, kategori, atau laboratorium" maxlength="100" autocomplete="off" aria-label="Kata kunci pencarian">
    <?php if (!$lab): ?>
        <select name="lab" aria-label="Filter laboratorium"><option value="0">Semua Lab</option><?php opts($labs, $lab); ?></select>
    <?php endif; ?>
    <select name="kondisi" aria-label="Filter kondisi"><option value="">Semua Kondisi</option><?php opts(daftar_kondisi(), $kon); ?></select>
    <button class="btn">Cari</button>
    <a class="btn alt" href="barang.php<?= $lab ? '?lab=' . $lab : '' ?>" data-reset>Reset</a>
</form>

<div id="hasil" aria-live="polite">
    <?php require __DIR__ . '/includes/barang_hasil.php'; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
