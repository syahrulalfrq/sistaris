<?php
/** Proses status peminjaman: setujui, tolak, kembali, hapus (admin) dan batal (pemilik). */
require __DIR__ . '/includes/bootstrap.php';
auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: peminjaman.php');
    exit;
}
cek_csrf();

$id    = (int) ($_POST['id'] ?? 0);
$aksi  = $_POST['aksi'] ?? '';
$balik = isset(STATUS_PINJAM[$_POST['status'] ?? '']) ? '?status=' . $_POST['status'] : '';

$db->beginTransaction();
try {
    $s = $db->prepare('SELECT * FROM peminjaman WHERE id_pinjam = ? FOR UPDATE');
    $s->execute([$id]);
    $p = $s->fetch();

    if (!$p) {
        flash('Data peminjaman tidak ditemukan.');
    } elseif ($aksi === 'batal') {
        if ((int) $p['id_user'] !== user_id() && !is_admin()) {
            flash('Anda tidak berhak membatalkan pengajuan ini.');
        } elseif ($p['status'] !== 'pending') {
            flash('Hanya pengajuan yang masih menunggu yang dapat dibatalkan.');
        } else {
            $db->prepare("UPDATE peminjaman SET status = 'dibatalkan' WHERE id_pinjam = ?")->execute([$id]);
            flash('Pengajuan #' . $id . ' dibatalkan.');
        }
    } elseif (!is_admin()) {
        flash('Hanya admin yang dapat memproses peminjaman.');
    } elseif ($aksi === 'setujui') {
        if ($p['status'] !== 'pending') {
            flash('Pengajuan ini sudah diproses sebelumnya.');
        } else {
            $b = $db->prepare('SELECT jumlah, kondisi, nama_barang FROM barang WHERE id_barang = ? FOR UPDATE');
            $b->execute([$p['id_barang']]);
            $brg = $b->fetch();
            $d = $db->prepare("SELECT COALESCE(SUM(jumlah), 0) FROM peminjaman WHERE id_barang = ? AND status = 'disetujui'");
            $d->execute([$p['id_barang']]);
            $tersedia = (int) $brg['jumlah'] - (int) $d->fetchColumn();
            if ($brg['kondisi'] === 'Rusak Berat') {
                flash('Barang berkondisi rusak berat, tidak dapat dipinjamkan.');
            } elseif ((int) $p['jumlah'] > $tersedia) {
                flash('Stok tersedia tidak cukup (tersisa ' . max(0, $tersedia) . '). Pengajuan belum dapat disetujui.');
            } else {
                $db->prepare("UPDATE peminjaman SET status = 'disetujui', diproses_oleh = ? WHERE id_pinjam = ?")->execute([$_SESSION['user'], $id]);
                flash('Pengajuan #' . $id . ' disetujui. Unit tercatat dipinjam dan tetap menjadi aset madrasah.');
            }
        }
    } elseif ($aksi === 'tolak') {
        if ($p['status'] !== 'pending') {
            flash('Pengajuan ini sudah diproses sebelumnya.');
        } else {
            $db->prepare("UPDATE peminjaman SET status = 'ditolak', diproses_oleh = ? WHERE id_pinjam = ?")->execute([$_SESSION['user'], $id]);
            flash('Pengajuan #' . $id . ' ditolak.');
        }
    } elseif ($aksi === 'kembali') {
        if ($p['status'] !== 'disetujui') {
            flash('Hanya barang yang sedang dipinjam yang dapat ditandai kembali.');
        } else {
            $db->prepare("UPDATE peminjaman SET status = 'dikembalikan', tgl_kembali = CURDATE(), diproses_oleh = ? WHERE id_pinjam = ?")->execute([$_SESSION['user'], $id]);
            flash('Barang pada peminjaman #' . $id . ' ditandai sudah dikembalikan.');
        }
    } elseif ($aksi === 'hapus') {
        if (!is_superadmin()) {
            flash('Hanya Super Admin yang dapat menghapus riwayat peminjaman.');
        } elseif (!in_array($p['status'], ['dikembalikan', 'ditolak'], true)) {
            flash('Hanya riwayat peminjaman dengan status dikembalikan atau ditolak yang dapat dihapus.');
        } else {
            $db->prepare("DELETE FROM peminjaman WHERE id_pinjam = ?")->execute([$id]);
            hapus_surat($p['surat_file'] ?? null);
            flash('Riwayat peminjaman #' . $id . ' berhasil dihapus.');
        }
    } else {
        flash('Aksi tidak dikenal.');
    }
    $db->commit();
} catch (Throwable $ex) {
    $db->rollBack();
    flash('Terjadi kesalahan saat memproses data. Silakan coba lagi.');
}

// Setelah riwayat dihapus, nomor peminjaman di bawahnya naik mengisi tempat kosong.
if ($aksi === 'hapus') {
    try {
        rapikan_pinjam();
    } catch (Throwable $ex) {
        // Gagal merapikan nomor tidak boleh menggagalkan penghapusan.
    }
}

header('Location: peminjaman.php' . $balik);