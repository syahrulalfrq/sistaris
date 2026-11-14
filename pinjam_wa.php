<?php
/**
 * Halaman konfirmasi pengajuan: langsung membuka WhatsApp admin
 * dengan pesan berformat yang sudah terisi (tinggal menekan kirim).
 */
require __DIR__ . '/includes/bootstrap.php';
auth();

$p = ambil_pinjam((int) ($_GET['id'] ?? 0));
if (!$p || (!is_admin() && (int) $p['id_user'] !== user_id())) {
    flash('Data peminjaman tidak ditemukan.');
    header('Location: peminjaman.php');
    exit;
}

$pesan = pesan_pinjam($p);
$link  = wa_link(WA_ADMIN, $pesan);
$baru  = isset($_GET['kirim']) ? false : true;

$title = 'Kirim ke WhatsApp';
$crumb = ['Peminjaman', $title];
require __DIR__ . '/includes/header.php';
?>
<section class="panel pad wa-box">
    <div class="wa-ico"><?= ikon('whatsapp') ?></div>
    <h2 class="wa-judul">Pengajuan #<?= (int) $p['id_pinjam'] ?> tersimpan</h2>
    <p>Status: <span class="badge b-<?= STATUS_PINJAM[$p['status']][1] ?>"><?= e(STATUS_PINJAM[$p['status']][0]) ?></span>.
       Hubungi admin lewat WhatsApp (<b><?= TELP ?></b>) agar permintaan segera disetujui.</p>

    <pre class="wa-pesan" id="waPesan"><?= e($pesan) ?></pre>

    <div class="actions">
        <a class="btn gold" id="waKirim" href="<?= e($link) ?>" target="_blank" rel="noopener">Buka WhatsApp &amp; Kirim Pesan</a>
        <a class="btn alt" href="peminjaman.php">Lihat Peminjaman Saya</a>
    </div>
    <p class="muted wa-info" id="waInfo"><?= $baru ? 'Anda akan diarahkan ke WhatsApp dalam <b id="waHitung">4</b> detik. Tekan tombol bila tidak berpindah otomatis.' : '' ?></p>
    <p class="muted wa-info">Catatan: tautan WhatsApp hanya membuka pesan yang sudah terisi; Anda tetap perlu menekan tombol kirim di WhatsApp.</p>
</section>
<?php if ($baru): ?>
<script>
(() => {
    let n = 4;
    const el = document.getElementById('waHitung');
    const t = setInterval(() => {
        n--;
        if (el) el.textContent = n;
        if (n <= 0) { clearInterval(t); location.href = <?= json_encode($link) ?>; }
    }, 1000);
    document.getElementById('waKirim').addEventListener('click', () => clearInterval(t));
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
