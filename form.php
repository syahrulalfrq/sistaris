<?php
require __DIR__ . '/includes/bootstrap.php';
auth(true); // khusus admin

const SATUAN_DEFAULT = 'unit';

$id       = (int) ($_GET['id'] ?? 0);
$kategori = $db->query('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori')->fetchAll(PDO::FETCH_KEY_PAIR);
// Urutan field: laboratorium lebih dulu, lalu kode barang.
$fields   = ['id_lab', 'kode_barang', 'nama_barang', 'merk', 'id_kategori', 'jumlah', 'kondisi', 'tanggal_masuk', 'keterangan'];

$row = [];
if ($id) {
    $s = $db->prepare('SELECT * FROM barang WHERE id_barang = ?');
    $s->execute([$id]);
    if (!($row = $s->fetch())) { flash('Data barang tidak ditemukan.'); header('Location: barang.php'); exit; }
}

$lab_awal = (int) ($_GET['lab'] ?? 0);
$v = $row ?: [
    'id_lab' => isset($labs[$lab_awal]) ? $lab_awal : '', 'kode_barang' => '', 'nama_barang' => '', 'merk' => '', 'id_kategori' => '',
    'jumlah' => 1, 'kondisi' => 'Baik', 'tanggal_masuk' => date('Y-m-d'), 'keterangan' => '',
];
// Tambah barang dari halaman lab tertentu: kode otomatis langsung terisi.
if (!$id && $v['id_lab']) {
    $v['kode_barang'] = kode_berikut((int) $v['id_lab']);
}
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    foreach ($fields as $k) $v[$k] = trim($_POST[$k] ?? '');
    $v['satuan'] = SATUAN_DEFAULT; // satuan tidak diisi manual: selalu "unit"

    if (!isset($labs[$v['id_lab']]))          $err['id_lab']        = 'Pilih laboratorium.';

    // Kode barang dibuat otomatis oleh server berdasarkan laboratorium yang dipilih.
    if (!isset($err['id_lab'])) {
        if ($id && (int) $v['id_lab'] === (int) $row['id_lab']) {
            $v['kode_barang'] = $row['kode_barang'];             // laboratorium tidak berubah: kode tetap
        } else {
            $v['kode_barang'] = kode_berikut((int) $v['id_lab'], $id); // lab baru/berbeda: kode sesuai lab
        }
    } else {
        $v['kode_barang'] = '';
    }

    $n = mb_strlen($v['nama_barang']);
    if ($n < 3 || $n > 150)                  $err['nama_barang']   = 'Nama barang harus 3-150 karakter.';
    if (mb_strlen($v['merk']) > 100)          $err['merk']          = 'Merk maksimal 100 karakter.';
    if (!isset($kategori[$v['id_kategori']])) $err['id_kategori']   = 'Pilih kategori.';
    if (filter_var($v['jumlah'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99999]]) === false)
                                              $err['jumlah']        = 'Jumlah harus berupa angka 0-99999.';
    if (!isset(KONDISI[$v['kondisi']]))       $err['kondisi']       = 'Pilih kondisi.';
    $d = DateTime::createFromFormat('Y-m-d', $v['tanggal_masuk']);
    if (!$d || $d->format('Y-m-d') !== $v['tanggal_masuk'] || $d > new DateTime())
                                              $err['tanggal_masuk'] = 'Tanggal tidak valid atau melebihi hari ini.';
    if (mb_strlen($v['keterangan']) > 500)    $err['keterangan']    = 'Keterangan maksimal 500 karakter.';

    // Jumlah aset tidak boleh lebih kecil dari unit yang sedang dipinjam.
    if ($id && !isset($err['jumlah'])) {
        $q = $db->prepare("SELECT COALESCE(SUM(jumlah), 0) FROM peminjaman WHERE id_barang = ? AND status = 'disetujui'");
        $q->execute([$id]);
        $dip = (int) $q->fetchColumn();
        if ((int) $v['jumlah'] < $dip) $err['jumlah'] = "Sedang ada $dip unit yang dipinjam, jumlah tidak boleh kurang dari itu.";
    }

    if (!$err) {
        try {
            if ($id) {
                $db->prepare('UPDATE barang SET kode_barang=?, nama_barang=?, merk=?, id_kategori=?, id_lab=?, jumlah=?, kondisi=?, satuan=?, tanggal_masuk=?, keterangan=? WHERE id_barang=?')
                   ->execute([$v['kode_barang'], $v['nama_barang'], $v['merk'], $v['id_kategori'], $v['id_lab'], $v['jumlah'], $v['kondisi'], $v['satuan'], $v['tanggal_masuk'], $v['keterangan'], $id]);
            } else {
                // ID memakai nomor kosong terkecil (bukan selalu angka terakhir + 1).
                sisip_id('barang', 'id_barang', [
                    'kode_barang' => $v['kode_barang'], 'nama_barang' => $v['nama_barang'], 'merk' => $v['merk'],
                    'id_kategori' => $v['id_kategori'], 'id_lab' => $v['id_lab'], 'jumlah' => $v['jumlah'],
                    'kondisi' => $v['kondisi'], 'satuan' => $v['satuan'], 'tanggal_masuk' => $v['tanggal_masuk'], 'keterangan' => $v['keterangan'],
                ]);
            }
            // Barang pindah laboratorium: rapikan kode di laboratorium asalnya.
            if ($id && (int) $row['id_lab'] !== (int) $v['id_lab']) {
                rapikan_kode((int) $row['id_lab']);
            }
            flash($id ? 'Data barang berhasil diperbarui.' : 'Barang ' . $v['kode_barang'] . ' berhasil ditambahkan.');
            header('Location: barang.php?lab=' . $v['id_lab']);
            exit;
        } catch (PDOException $ex) {
            $err['kode_barang'] = 'Kode barang bentrok dengan data lain. Silakan simpan ulang.';
        }
    }
}

function err($k) { global $err; return isset($err[$k]) ? '<small class="err">' . e($err[$k]) . '</small>' : ''; }

$title = $id ? 'Edit Barang' : 'Tambah Barang';
$crumb = ['Data Barang', $title];
require __DIR__ . '/includes/header.php';
?>
<form class="panel pad" method="post" data-validate novalidate<?= ($id && $row) ? ' data-konfirm="simpan" data-nama="' . e(($row['nama_barang'] ?? '') . ' (' . ($row['kode_barang'] ?? '') . ')') . '"' : '' ?>>
    <p class="muted form-note">Pilih laboratorium terlebih dahulu, kode barang akan terisi otomatis. Kolom bertanda * wajib diisi.</p>
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <div class="grid">
        <div>
            <label for="id_lab">Laboratorium</label>
            <select id="id_lab" name="id_lab" required<?= ($id && $row) ? ' data-lab-asli="' . (int) ($row['id_lab'] ?? 0) . '" data-kode-asli="' . e($row['kode_barang'] ?? '') . '"' : '' ?> data-kode-url="kode_baru.php" data-id="<?= $id ?>"><option value="">Pilih laboratorium</option><?php opts($labs, $v['id_lab']); ?></select>
            <?= err('id_lab') ?>
        </div>
        <div>
            <label for="kode_barang">Kode Barang <small class="muted">(otomatis)</small></label>
            <input id="kode_barang" name="kode_barang" value="<?= e($v['kode_barang']) ?>" placeholder="Pilih laboratorium dahulu" readonly class="auto" autocomplete="off" aria-describedby="kode-info">
            <small class="hint-kode" id="kode-info">Mengikuti kode laboratorium terpilih, mis. POD-001 untuk Lab Podcast.</small>
            <?= err('kode_barang') ?>
        </div>
        <div>
            <label for="nama_barang">Nama Barang</label>
            <input id="nama_barang" name="nama_barang" value="<?= e($v['nama_barang']) ?>" required minlength="3" maxlength="150">
            <?= err('nama_barang') ?>
        </div>
        <div>
            <label for="merk">Merk / Tipe</label>
            <input id="merk" name="merk" value="<?= e($v['merk']) ?>" maxlength="100">
            <?= err('merk') ?>
        </div>
        <div>
            <label for="id_kategori">Kategori</label>
            <select id="id_kategori" name="id_kategori" required><option value="">Pilih kategori</option><?php opts($kategori, $v['id_kategori']); ?></select>
            <?= err('id_kategori') ?>
        </div>
        <div>
            <label for="jumlah">Jumlah (total aset)</label>
            <input id="jumlah" type="number" name="jumlah" value="<?= e($v['jumlah']) ?>" required min="0" max="99999">
            <?= err('jumlah') ?>
        </div>
        <div>
            <label for="kondisi">Kondisi</label>
            <select id="kondisi" name="kondisi" required><?php opts(daftar_kondisi(), $v['kondisi']); ?></select>
            <?= err('kondisi') ?>
        </div>
        <div>
            <label for="tanggal_masuk">Tanggal Masuk</label>
            <input id="tanggal_masuk" type="date" name="tanggal_masuk" value="<?= e($v['tanggal_masuk']) ?>" required max="<?= date('Y-m-d') ?>">
            <?= err('tanggal_masuk') ?>
        </div>
        <div class="full">
            <label for="keterangan">Keterangan</label>
            <textarea id="keterangan" name="keterangan" rows="3" maxlength="500" data-counter><?= e($v['keterangan']) ?></textarea>
            <?= err('keterangan') ?>
        </div>
    </div>
    <div class="actions">
        <button class="btn" data-loading="Menyimpan..."><?= $id ? 'Simpan Perubahan' : 'Simpan' ?></button>
        <a class="btn alt" href="barang.php<?= $v['id_lab'] ? '?lab=' . (int) $v['id_lab'] : '' ?>">Batal</a>
    </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
