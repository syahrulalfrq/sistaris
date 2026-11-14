<?php
/** Profil akun: ubah nama, email, dan kata sandi. Berlaku untuk admin maupun pengguna. */
require __DIR__ . '/includes/bootstrap.php';
auth();
require_once __DIR__ . '/includes/foto_akun.php';
akun_foto_siapkan();

$s = $db->prepare('SELECT * FROM users WHERE id_user = ?');
$s->execute([user_id()]);
$u = $s->fetch();

$err = [];
$v   = ['nama' => $u['nama'], 'username' => $u['username'], 'email' => (string) ($u['email'] ?? '')];
$boleh_ganti_username = ($u['role'] === 'superadmin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Foto terlalu besar untuk batas server: PHP mengosongkan seluruh $_POST.
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('Ukuran foto melebihi batas server. Gunakan foto maksimal ' . MAKS_FOTO_AKUN_MB . ' MB.', 'error');
        header('Location: profil.php');
        exit;
    }
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';

    // Ganti foto profil
    if ($aksi === 'foto') {
        $galat = null;
        $up = akun_simpan_foto($_FILES['foto'] ?? null, user_id(), $galat);
        if ($up === false) {
            flash($galat ?: 'Foto gagal diunggah.', 'error');
        } elseif ($up === null) {
            flash('Pilih foto terlebih dahulu.', 'error');
        } else {
            $db->prepare('UPDATE users SET foto = ? WHERE id_user = ?')->execute([$up, user_id()]);
            akun_hapus_foto($u['foto'] ?? null);
            flash('Foto profil berhasil diperbarui.');
        }
        header('Location: profil.php');
        exit;
    }

    // Hapus foto profil
    if ($aksi === 'hapus_foto') {
        $db->prepare('UPDATE users SET foto = NULL WHERE id_user = ?')->execute([user_id()]);
        akun_hapus_foto($u['foto'] ?? null);
        flash('Foto profil dihapus.');
        header('Location: profil.php');
        exit;
    }

    if ($aksi === 'profil') {
        $v['nama']  = trim($_POST['nama'] ?? '');
        $v['email'] = trim($_POST['email'] ?? '');
        $n = mb_strlen($v['nama']);
        if ($n < 3 || $n > 100) $err['nama'] = 'Nama harus 3-100 karakter.';

        // Username hanya dapat diubah oleh Super Admin
        $username = $u['username'];
        if ($boleh_ganti_username) {
            $v['username'] = trim($_POST['username'] ?? '');
            $un = mb_strlen($v['username']);
            if ($un < 3 || $un > 50) {
                $err['username'] = 'Username harus 3-50 karakter.';
            } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $v['username'])) {
                $err['username'] = 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung (tanpa spasi).';
            } else {
                $cu = $db->prepare('SELECT id_user FROM users WHERE username = ? AND id_user != ?');
                $cu->execute([$v['username'], user_id()]);
                if ($cu->fetch()) $err['username'] = 'Username sudah digunakan oleh akun lain.';
                else $username = $v['username'];
            }
        }
        
        $email = '';
        if ($v['email'] !== '') {
            if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
                $err['email'] = 'Format email tidak valid (contoh: user@domain.com).';
            } else {
                // Cek apakah email sudah digunakan oleh user lain
                $chk = $db->prepare('SELECT id_user FROM users WHERE email = ? AND id_user != ?');
                $chk->execute([$v['email'], user_id()]);
                if ($chk->fetch()) {
                    $err['email'] = 'Email ini sudah digunakan oleh akun lain.';
                } else {
                    $email = $v['email'];
                }
            }
        }

        if (!$err) {
            $db->prepare('UPDATE users SET nama = ?, username = ?, email = ? WHERE id_user = ?')->execute([$v['nama'], $username, $email ?: null, user_id()]);
            $_SESSION['user'] = $v['nama'];
            $_SESSION['username'] = $username;
            flash('Profil berhasil diperbarui.');
            header('Location: profil.php');
            exit;
        }
    }

    if ($aksi === 'sandi') {
        $lama = $_POST['lama'] ?? '';
        $baru = $_POST['baru'] ?? '';
        $ulang = $_POST['ulang'] ?? '';
        if (!password_verify($lama, $u['password']) && !hash_equals($u['password'], $lama)) $err['lama'] = 'Kata sandi lama salah.';
        if (strlen($baru) < 6)      $err['baru'] = 'Kata sandi baru minimal 6 karakter.';
        elseif ($baru === $lama)    $err['baru'] = 'Kata sandi baru tidak boleh sama dengan kata sandi lama.';
        elseif ($baru !== $ulang)   $err['ulang'] = 'Konfirmasi kata sandi tidak sama.';
        if (!$err) {
            $db->prepare('UPDATE users SET password = ?, sandi_enc = ? WHERE id_user = ?')->execute([password_hash($baru, PASSWORD_DEFAULT), sandi_enc($baru), user_id()]);
            flash('Kata sandi berhasil diubah.');
            header('Location: profil.php');
            exit;
        }
    }
}

function err($k) { global $err; return isset($err[$k]) ? '<small class="err">' . e($err[$k]) . '</small>' : ''; }

$st = $db->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status='pending'),0) AS pending, COALESCE(SUM(status='disetujui'),0) AS aktif FROM peminjaman WHERE id_user = ?");
$st->execute([user_id()]);
$ring = $st->fetch();

$title = 'Profil Saya';
$crumb = [$title];
require __DIR__ . '/includes/header.php';
?>

<div class="profil-head panel pad">
    <div class="foto-akun">
        <span id="fotoTampil"><?= akun_avatar($u['nama'], $u['foto'] ?? null, 'xl') ?></span>
        <div class="foto-aksi">
            <form method="post" enctype="multipart/form-data" id="fotoForm" class="foto-form">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="aksi" value="foto">
                <input type="file" id="fotoFile" name="foto" accept="image/jpeg,image/png,image/webp" hidden>
                <button type="button" class="btn sm alt foto-btn" id="fotoPilih">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Ubah Foto
                </button>
                <button class="btn sm foto-btn" id="fotoSimpan" data-loading="Menyimpan..." hidden>Simpan Foto</button>
            </form>
            <?php if (akun_foto_url($u['foto'] ?? null) !== ''): ?>
            <form method="post" class="foto-form" data-konfirm="hapus" data-judul="Hapus foto profil?" data-teks="Foto akan diganti huruf inisial nama Anda.">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="aksi" value="hapus_foto">
                <button class="btn sm foto-btn foto-hapus" type="submit">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    Hapus Foto
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div>
        <h2><?= e($u['nama']) ?></h2>
        <p class="muted">@<?= e($u['username']) ?><?= $u['organisasi'] ? ' &middot; ' . e($u['organisasi']) : '' ?> &middot; <span class="badge <?= ['superadmin' => 'b-info', 'admin' => 'b-ok'][$u['role']] ?? 'b-mute' ?>"><?= e(label_peran($u['role'])) ?></span></p>
    </div>
    <div class="profil-stat">
        <div><b><?= (int) $ring['total'] ?></b><span>Peminjaman</span></div>
        <div><b><?= (int) $ring['pending'] ?></b><span>Menunggu</span></div>
        <div><b><?= (int) $ring['aktif'] ?></b><span>Sedang dipinjam</span></div>
    </div>
</div>

<div class="two eq">
    <form class="panel pad" method="post" data-validate novalidate>
        <h3 class="form-judul">Data Akun</h3>
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="aksi" value="profil">
        
        <label for="organisasi">Komunitas / Organisasi</label>
        <input id="organisasi" value="<?= e((string) $u['organisasi']) ?>" readonly class="auto" placeholder="-">
        
        <label for="nama">Nama Lengkap</label>
        <input id="nama" name="nama" value="<?= e($v['nama']) ?>" required minlength="3" maxlength="100">
        <?= err('nama') ?>
        
        <?php if ($boleh_ganti_username): ?>
        <label for="username">Username <small class="muted">(Super Admin dapat mengubahnya kapan saja)</small></label>
        <input id="username" name="username" value="<?= e($v['username']) ?>" required minlength="3" maxlength="50" autocomplete="off">
        <?= err('username') ?>
        <?php else: ?>
        <label for="username">Username</label>
        <input id="username" value="<?= e($u['username']) ?>" readonly class="auto">
        <?php endif; ?>
        
        <label for="email">Email <small class="muted">(Opsional, dapat digunakan untuk login)</small></label>
        <input id="email" type="email" name="email" value="<?= e($v['email']) ?>" maxlength="100" placeholder="contoh@domain.com">
        <?= err('email') ?>
        
        <div class="actions"><button class="btn" data-loading="Menyimpan...">Simpan Profil</button></div>
    </form>

    <form class="panel pad" method="post" data-validate novalidate autocomplete="off">
        <h3 class="form-judul">Ubah Kata Sandi</h3>
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="aksi" value="sandi">
        
        <label for="lama">Kata Sandi Lama</label>
        <?= input_sandi('lama', 'lama', 'required autocomplete="current-password"') ?>
        <?= err('lama') ?>
        
        <label for="baru">Kata Sandi Baru</label>
        <?= input_sandi('baru', 'baru', 'required minlength="6" autocomplete="new-password"') ?>
        <?= err('baru') ?>
        
        <label for="ulang">Ulangi Kata Sandi Baru</label>
        <?= input_sandi('ulang', 'ulang', 'required minlength="6" autocomplete="new-password"') ?>
        <?= err('ulang') ?>
        
        <div class="actions"><button class="btn" data-loading="Menyimpan...">Ubah Kata Sandi</button></div>
    </form>
</div>

<script>
(function () {
    var inp = document.getElementById('fotoFile'), pilih = document.getElementById('fotoPilih'),
        simpan = document.getElementById('fotoSimpan'), tampil = document.getElementById('fotoTampil');
    if (!inp) return;
    var asli = tampil.innerHTML;
    pilih.onclick = function () { inp.click(); };
    inp.onchange = function () {
        var f = inp.files[0];
        if (!f) { tampil.innerHTML = asli; simpan.hidden = true; return; }
        if (!/^image\/(jpeg|png|webp)$/.test(f.type)) {
            alert('Format foto harus JPG, PNG, atau WebP.'); inp.value = ''; return;
        }
        if (f.size > <?= (int) MAKS_FOTO_AKUN_MB ?> * 1048576) {
            alert('Ukuran foto maksimal <?= (int) MAKS_FOTO_AKUN_MB ?> MB. Pilih foto yang lebih kecil.'); inp.value = ''; return;
        }
        var img = new Image();
        img.alt = ''; img.src = URL.createObjectURL(f);
        var av = tampil.querySelector('.avatar');
        av.classList.add('ada-foto'); av.textContent = ''; av.appendChild(img);
        simpan.hidden = false;
        pilih.textContent = 'Pilih Lain';
    };
})();
</script>

<?php if (!is_admin()): ?>
<p class="muted">Ingin meminjam barang? Buka <a href="barang.php"><b>Data Barang</b></a>, lalu tekan tombol <b>Pinjam</b>. Butuh bantuan? <a href="<?= e(wa_link(WA_ADMIN, 'Assalamu\'alaikum Admin, saya butuh bantuan terkait akun ' . APLIKASI . '.')) ?>" target="_blank" rel="noopener"><b>Hubungi admin via WhatsApp</b></a>.</p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>