<?php
/**
 * Formulir peminjaman barang. Status awal selalu "pending" sampai admin menyetujui.
 * Setelah disimpan, pengguna diarahkan ke WhatsApp admin dengan pesan yang sudah terisi.
 */
require __DIR__ . '/includes/bootstrap.php';
auth();

$id = (int) ($_GET['id'] ?? 0);
$s  = $db->prepare('SELECT b.*, l.nama_lab, ' . SQL_DIPINJAM . ' AS dipinjam
    FROM barang b JOIN lab l ON l.id_lab = b.id_lab WHERE b.id_barang = ?');
$s->execute([$id]);
$b = $s->fetch();
if (!$b) { flash('Barang tidak ditemukan.'); header('Location: barang.php'); exit; }

$tersedia = max(0, (int) $b['jumlah'] - (int) $b['dipinjam']);
if ($b['kondisi'] === 'Rusak Berat' || $tersedia < 1) {
    flash('Barang "' . $b['nama_barang'] . '" sedang tidak tersedia untuk dipinjam.');
    header('Location: barang.php' . ($b['id_lab'] ? '?lab=' . (int) $b['id_lab'] : ''));
    exit;
}

$u = $db->prepare('SELECT no_hp FROM users WHERE id_user = ?');
$u->execute([user_id()]);
$hp_profil = (string) ($u->fetchColumn() ?: '');

$hari = date('Y-m-d');
$v = [
    'nama_peminjam' => '',                       // dikosongkan: peminjam mengisi sendiri
    'no_hp'         => $hp_profil,
    'jumlah'        => 1,
    'tgl_kembali_rencana' => '',                 // batas pengembalian: wajib diisi peminjam
    'keperluan'     => '',
];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    foreach ($v as $k => $_) $v[$k] = trim($_POST[$k] ?? '');

    $n = mb_strlen($v['nama_peminjam']);
    if ($n < 3 || $n > 100)                          $err['nama_peminjam'] = 'Nama peminjam harus 3-100 karakter.';
    $hp = normal_hp($v['no_hp']);
    if ($hp === '')                                  $err['no_hp'] = 'Nomor WhatsApp tidak valid (contoh: 0858xxxxxxxx).';
    $jml = filter_var($v['jumlah'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($jml === false)                              $err['jumlah'] = 'Jumlah minimal 1.';
    elseif ($jml > $tersedia)                        $err['jumlah'] = "Jumlah melebihi stok tersedia ($tersedia {$b['satuan']}).";
    // Tanggal & jam pinjam TIDAK diambil dari formulir: selalu waktu server saat ini (real-time, WIB).
    $sekarang = date('Y-m-d H:i:s');
    $hari     = date('Y-m-d');
    $d2 = DateTime::createFromFormat('Y-m-d', $v['tgl_kembali_rencana']);
    if ($v['tgl_kembali_rencana'] === '')            $err['tgl_kembali_rencana'] = 'Tanggal pengembalian wajib diisi.';
    elseif (!$d2 || $d2->format('Y-m-d') !== $v['tgl_kembali_rencana'])
                                                     $err['tgl_kembali_rencana'] = 'Tanggal pengembalian tidak valid.';
    elseif ($v['tgl_kembali_rencana'] < $hari)       $err['tgl_kembali_rencana'] = 'Tanggal pengembalian tidak boleh sebelum hari ini.';
    $k = mb_strlen($v['keperluan']);
    if ($k < 5 || $k > 300)                          $err['keperluan'] = 'Keperluan harus 5-300 karakter.';

    // Surat resmi (opsional, format bebas)
    $surat = null;
    if (!$err) {
        $surat = simpan_surat($_FILES['surat'] ?? null, $galat_surat);
        if ($surat === false) { $err['surat'] = $galat_surat; }
    }

    if (!$err) {
        try {
            $db->prepare("INSERT INTO peminjaman (id_barang, id_user, nama_peminjam, no_hp, jumlah, keperluan, surat_file, surat_nama, tgl_pinjam, waktu_pinjam, tgl_kembali_rencana, status)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?, 'pending')")
               ->execute([$id, user_id(), $v['nama_peminjam'], $hp, $jml, $v['keperluan'], $surat[0] ?? null, $surat[1] ?? null, $hari, $sekarang, $v['tgl_kembali_rencana']]);
        } catch (Throwable $ex) {
            if ($surat) { hapus_surat($surat[0]); }
            throw $ex;
        }
        $id_baru = (int) $db->lastInsertId();
        flash('Pengajuan peminjaman berhasil dikirim dan menunggu persetujuan admin.', 'sukses');
        header('Location: pinjam_wa.php?id=' . $id_baru);
        exit;
    }
}

function err($k) { global $err; return isset($err[$k]) ? '<small class="err">' . e($err[$k]) . '</small>' : ''; }

$title = 'Ajukan Peminjaman';
$crumb = ['Data Barang', $title];
require __DIR__ . '/includes/header.php';
?>
<div class="two pinjam-grid">
    <form class="panel pad" method="post" enctype="multipart/form-data" data-validate novalidate>
        <p class="muted form-note">Pengajuan berstatus <b>menunggu persetujuan</b> sampai admin menyetujui. Setelah disimpan, Anda akan diarahkan ke WhatsApp admin dengan pesan yang sudah terisi, tinggal kirim.</p>
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <div class="grid">
            <div class="full">
                <label for="nama_peminjam">Nama Peminjam</label>
                <input id="nama_peminjam" name="nama_peminjam" value="<?= e($v['nama_peminjam']) ?>" placeholder="Tulis nama lengkap peminjam" required minlength="3" maxlength="100" autocomplete="name">
                <?= err('nama_peminjam') ?>
            </div>
            <div>
                <label for="no_hp">No. WhatsApp Peminjam</label>
                <input id="no_hp" name="no_hp" value="<?= e($v['no_hp']) ?>" placeholder="0858xxxxxxxx" required inputmode="tel" maxlength="20" autocomplete="tel">
                <?= err('no_hp') ?>
            </div>
            <div>
                <label for="jumlah">Jumlah (maks. <?= $tersedia ?>)</label>
                <input id="jumlah" type="number" name="jumlah" value="<?= e($v['jumlah']) ?>" required min="1" max="<?= $tersedia ?>">
                <?= err('jumlah') ?>
            </div>
            <div>
                <label for="tgl_pinjam_tampil">Tanggal &amp; Jam Pinjam <span class="muted">(otomatis)</span></label>
                <input id="tgl_pinjam_tampil" class="auto live-clock" type="text" value="<?= e(tgl_id() . ', ' . date('H.i.s') . ' WIB') ?>" readonly tabindex="-1" aria-readonly="true" data-server-ms="<?= time() * 1000 ?>">
                <small class="hint-kode">Mengikuti waktu sebenarnya dan tidak dapat diubah.</small>
            </div>
            <div>
                <label for="tgl_kembali_rencana">Tanggal Pengembalian</label>
                <input id="tgl_kembali_rencana" type="date" name="tgl_kembali_rencana" value="<?= e($v['tgl_kembali_rencana']) ?>" required min="<?= $hari ?>">
                <small class="hint-kode">Pilih batas hari barang harus dikembalikan.</small>
                <?= err('tgl_kembali_rencana') ?>
            </div>
            <div class="full">
                <label for="keperluan">Keperluan</label>
                <textarea id="keperluan" name="keperluan" rows="3" required minlength="5" maxlength="300" data-counter placeholder="Contoh: praktikum IPA kelas VIII"><?= e($v['keperluan']) ?></textarea>
                <?= err('keperluan') ?>
            </div>
            <div class="full">
                <label for="surat">Surat Resmi Peminjaman <span class="muted">(opsional)</span></label>
                <input id="surat" type="file" name="surat">
                <small class="muted">Format bebas (PDF, foto, Word, dll.), maks. <?= MAKS_SURAT_MB ?> MB. Boleh dikosongkan.</small>
                <?= err('surat') ?>
            </div>
        </div>
        <div class="actions">
            <button class="btn" data-loading="Menyimpan...">Ajukan &amp; Kirim ke WhatsApp</button>
            <a class="btn alt" href="barang.php<?= $b['id_lab'] ? '?lab=' . (int) $b['id_lab'] : '' ?>">Batal</a>
        </div>
    </form>

    <aside class="panel">
        <h2>Barang yang Dipinjam</h2>
        <dl class="detail">
            <dt>Kode</dt><dd><b><?= e($b['kode_barang']) ?></b></dd>
            <dt>Nama</dt><dd><?= e($b['nama_barang']) ?><?= $b['merk'] ? ' &middot; ' . e($b['merk']) : '' ?></dd>
            <dt>Laboratorium</dt><dd><?= e($b['nama_lab']) ?></dd>
            <dt>Total aset</dt><dd><?= (int) $b['jumlah'] . ' ' . e($b['satuan']) ?></dd>
            <dt>Sedang dipinjam</dt><dd><?= (int) $b['dipinjam'] ?></dd>
            <dt>Tersedia</dt><dd><b><?= $tersedia ?> <?= e($b['satuan']) ?></b></dd>
            <dt>Kondisi</dt><dd><span class="badge b-<?= KONDISI[$b['kondisi']] ?>"><?= e($b['kondisi']) ?></span></dd>
        </dl>
    </aside>
</div>
<script>
// Jam pinjam berjalan real-time mengikuti waktu server (WIB), bukan jam perangkat peminjam.
(() => {
    const el = document.getElementById('tgl_pinjam_tampil');
    if (!el) return;
    const awal = Number(el.dataset.serverMs), t0 = Date.now();
    const fmt = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' });
    const tampil = () => {
        const p = Object.fromEntries(fmt.formatToParts(new Date(awal + Date.now() - t0)).map(x => [x.type, x.value]));
        el.value = `${p.weekday}, ${p.day} ${p.month} ${p.year}, ${p.hour}.${p.minute}.${p.second} WIB`;
    };
    tampil();
    setInterval(tampil, 1000);
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>