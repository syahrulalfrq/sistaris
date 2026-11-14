<?php
/**
 * Kelola Struktur Lab (khusus Super Admin):
 * mengisi nama lengkap dan mengunggah foto tiap pengurus.
 * Tampil di beranda publik pada bagian "Struktur Lab".
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/struktur_fungsi.php';
auth(true);

if (!is_superadmin()) {
    flash('Halaman Struktur Lab hanya dapat dikelola oleh Super Admin.');
    header('Location: dashboard.php');
    exit;
}

$data = struktur_ambil();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Foto terlalu besar untuk batas server: PHP mengosongkan seluruh $_POST.
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('Ukuran foto melebihi batas server. Gunakan foto maksimal ' . MAKS_FOTO_MB . ' MB.', 'error');
        header('Location: struktur_kelola.php');
        exit;
    }
    cek_csrf();

    $kode = (string) ($_POST['kode'] ?? '');
    if (!isset($data[$kode])) {
        flash('Jabatan tidak ditemukan.', 'error');
        header('Location: struktur_kelola.php');
        exit;
    }
    $balik = 'Location: struktur_kelola.php#k-' . $kode;

    $nama = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($_POST['nama'] ?? '')));
    $nama = (string) preg_replace('/\s{2,}/u', ' ', $nama);
    if (mb_strlen($nama) > 100) {
        flash('Nama maksimal 100 karakter.', 'error');
        header($balik);
        exit;
    }

    $lama = $data[$kode]['foto'];
    $baru = $lama;
    $galat = null;
    $up = struktur_simpan_foto($_FILES['foto'] ?? null, $kode, $galat);
    if ($up === false) {
        flash($galat ?: 'Foto gagal diunggah.', 'error');
        header($balik);
        exit;
    }
    if ($up !== null) {
        $baru = $up;
    } elseif (!empty($_POST['hapus_foto'])) {
        $baru = null;
    }

    $db->prepare('UPDATE struktur_lab SET nama = ?, foto = ? WHERE kode = ?')
       ->execute([$nama !== '' ? $nama : null, $baru, $kode]);
    if ($baru !== $lama) {
        struktur_hapus_foto($lama);
    }

    flash('Data ' . $data[$kode]['jabatan'] . ' berhasil disimpan.', 'sukses');
    header($balik);
    exit;
}

$tingkat = [
    1 => 'Pimpinan Madrasah',
    2 => 'Wakil dan Kepala Laboratorium',
    3 => 'Pelaksana',
];

$title = 'Struktur Lab';
$crumb = [$title];
require __DIR__ . '/includes/header.php';
?>

<style>
.sk-bar { display: flex; flex-direction: column; align-items: center; gap: 10px; margin-bottom: 8px; text-align: center; }
.sk-bar h2 { margin: 0; font-size: 18px; color: var(--gd); }
.sk-intro { max-width: 760px; margin: 0 auto; text-align: center; }
.sk-judul { margin: 26px 0 14px; font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--g); display: flex; align-items: center; justify-content: center; gap: 12px; text-align: center; }
.sk-judul::before, .sk-judul::after { content: ""; flex: 1; max-width: 220px; height: 1px; background: var(--line); }
.sk-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 16px; }
.sk-card { flex: 0 0 auto; width: min(100%, 330px); background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 18px 18px 18px; scroll-margin-top: 70px; text-align: center; box-shadow: 0 4px 14px rgba(11, 107, 51, .06); }
.sk-card:target { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201, 162, 39, .25); }
.sk-top { display: flex; flex-direction: column; align-items: center; gap: 10px; margin-bottom: 8px; min-height: 150px; justify-content: flex-start; }
.sk-thumb { flex: none; width: 84px; height: 84px; border-radius: 50%; overflow: hidden; display: grid; place-items: center; background: linear-gradient(145deg, #1f9a52, #0b6b33); border: 3px solid var(--gold); box-shadow: 0 4px 10px rgba(0, 0, 0, .15); }
.sk-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sk-thumb svg { width: 78%; height: 78%; fill: rgba(255, 255, 255, .85); margin-top: 14%; }
.sk-top b { display: block; line-height: 1.3; color: var(--tx); font-size: 16px; }
.sk-top small { display: block; color: var(--mu); font-size: 12.5px; line-height: 1.35; margin-top: 2px; }
.sk-card label { text-align: center; }
.sk-card input[type=text], .sk-card input:not([type]) { text-align: center; }
.sk-card .hint { display: block; margin-top: 6px; font-size: 12px; color: var(--mu); }
.sk-card input[type=file] { padding: 7px 10px; font-size: 14px; }
.sk-cek { display: flex; align-items: center; justify-content: center; gap: 8px; margin: 12px 0 0; font-weight: 500; cursor: pointer; }
.sk-cek input { width: auto; margin: 0; }
.sk-card .btn.block { margin-top: 16px; }
</style>

<div class="panel pad">
    <div class="sk-bar">
        <h2>Struktur Lab</h2>
        <a class="btn sm alt" href="index.php#struktur" target="_blank" rel="noopener">Lihat di beranda &#8599;</a>
    </div>
    <p class="muted sk-intro">Isi nama lengkap dan foto tiap pengurus. Urutan tampilan di beranda mengikuti jabatan tertinggi di atas dan tidak berubah. Kolom nama boleh dikosongkan, beranda akan menampilkan tanda strip. Foto dipotong persegi dan dikecilkan otomatis agar beranda tetap ringan.</p>
</div>

<?php foreach ($tingkat as $t => $label): ?>
    <div class="sk-judul"><?= e($label) ?></div>
    <div class="sk-grid">
    <?php foreach ($data as $kode => $r):
        if ((STRUKTUR_BAKU[$kode][3] ?? 0) !== $t) { continue; }
        $induk = STRUKTUR_BAKU[$kode][1] ?? null;
        $induk_nama = $induk ? ($data[$induk]['jabatan'] ?? STRUKTUR_BAKU[$induk][0]) : null;
        $ada_foto = struktur_foto_url($r['foto']) !== '';
    ?>
        <form class="sk-card" id="k-<?= e($kode) ?>" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="kode" value="<?= e($kode) ?>">

            <div class="sk-top">
                <span class="sk-thumb" data-thumb><?= struktur_avatar($r, 64) ?></span>
                <div>
                    <b><?= e($r['jabatan']) ?></b>
                    <small><?= $induk_nama ? 'Di bawah ' . e($induk_nama) : 'Posisi tertinggi' ?></small>
                </div>
            </div>

            <label for="nama-<?= e($kode) ?>">Nama Lengkap</label>
            <input id="nama-<?= e($kode) ?>" name="nama" value="<?= e($r['nama'] ?? '') ?>" maxlength="100" placeholder="Nama lengkap beserta gelar">

            <label for="foto-<?= e($kode) ?>">Foto</label>
            <input id="foto-<?= e($kode) ?>" type="file" name="foto" accept="image/jpeg,image/png,image/webp" data-foto>
            <small class="hint">JPG, PNG, atau WebP, maksimal <?= MAKS_FOTO_MB ?> MB. Foto setengah badan dengan wajah di bagian atas paling pas.</small>

            <?php if ($ada_foto): ?>
                <label class="sk-cek"><input type="checkbox" name="hapus_foto" value="1"> Hapus foto saat ini</label>
            <?php endif; ?>

            <button class="btn block" data-loading="Menyimpan...">Simpan</button>
        </form>
    <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<script>
// Pratinjau foto sebelum disimpan + pemeriksaan ukuran di browser.
document.querySelectorAll('input[data-foto]').forEach(function (inp) {
    inp.addEventListener('change', function () {
        var f = inp.files && inp.files[0];
        if (!f) { return; }
        if (f.size > <?= (int) MAKS_FOTO_MB ?> * 1048576) {
            alert('Ukuran foto maksimal <?= (int) MAKS_FOTO_MB ?> MB. Pilih foto yang lebih kecil.');
            inp.value = '';
            return;
        }
        var thumb = inp.closest('form').querySelector('[data-thumb]');
        thumb.innerHTML = '';
        var img = new Image();
        img.alt = 'Pratinjau foto';
        img.src = URL.createObjectURL(f);
        thumb.appendChild(img);
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>