<?php
require __DIR__ . '/includes/bootstrap.php';
auth(true); // khusus admin
wajib_superadmin('barang.php'); // hapus data hanya Super Admin

// Parameter yang dipertahankan agar pengguna kembali ke daftar yang sama.
$balik = [];
foreach (['lab', 'cari', 'kondisi', 'page'] as $k) {
    $nilai = trim((string) ($_POST[$k] ?? ''));
    if ($nilai !== '' && $nilai !== '0') {
        $balik[$k] = $nilai;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: barang.php');
    exit;
}

cek_csrf();
$id = (int) ($_POST['id'] ?? 0);

$s = $db->prepare('SELECT kode_barang, nama_barang, id_lab FROM barang WHERE id_barang = ?');
$s->execute([$id]);
$b = $s->fetch();

if (!$b) {
    flash('Barang tidak ditemukan atau sudah dihapus sebelumnya.');
} else {
    $s = $db->prepare("SELECT COUNT(*) FROM peminjaman WHERE id_barang = ? AND status IN ('pending','disetujui')");
    $s->execute([$id]);
    if ((int) $s->fetchColumn() > 0) {
        flash('Barang "' . $b['nama_barang'] . '" tidak dapat dihapus karena masih ada peminjaman yang menunggu/aktif.');
    } else {
        $db->beginTransaction();
        try {
            // Riwayat peminjaman yang sudah selesai ikut dihapus bersama barangnya.
            $db->prepare('DELETE FROM peminjaman WHERE id_barang = ?')->execute([$id]);
            $db->prepare('DELETE FROM barang WHERE id_barang = ?')->execute([$id]);
            // Kode di bawahnya naik mengisi nomor yang kosong (KOM-006 menjadi KOM-005, dst.).
            rapikan_kode((int) $b['id_lab']);
            $db->commit();
            flash('Barang "' . $b['nama_barang'] . '" (' . $b['kode_barang'] . ') berhasil dihapus. Kode barang sesudahnya dirapikan otomatis.');
        } catch (Throwable $ex) {
            if ($db->inTransaction()) $db->rollBack();
            flash('Barang gagal dihapus karena terjadi kesalahan. Silakan coba lagi.', 'error');
        }
    }
}

header('Location: barang.php' . ($balik ? '?' . http_build_query($balik) : ''));