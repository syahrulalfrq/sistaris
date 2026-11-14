<?php
/** Buat Akun Baru oleh Admin */
require __DIR__ . '/includes/bootstrap.php';
auth(true);

$err = [];
$v   = ['organisasi' => '', 'nama' => '', 'username' => '', 'no_hp' => '', 'email' => '', 'role' => 'user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();

    foreach (['organisasi', 'nama', 'username', 'no_hp', 'email', 'role'] as $k) {
        $v[$k] = trim($_POST[$k] ?? '');
    }
    $pw  = $_POST['password'] ?? '';
    $pw2 = $_POST['password2'] ?? '';

    // Validasi Organisasi & Nama
    $o = mb_strlen($v['organisasi']);
    if ($o < 2 || $o > 100) $err['organisasi'] = 'Komunitas/organisasi harus 2-100 karakter.';
    
    $n = mb_strlen($v['nama']);
    if ($n < 3 || $n > 100) $err['nama'] = 'Nama harus 3-100 karakter.';

    // Validasi Username Unik
    if (mb_strlen($v['username']) < 3) {
        $err['username'] = 'Username minimal 3 karakter.';
    } else {
        $sUser = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $sUser->execute([$v['username']]);
        if ($sUser->fetchColumn() > 0) {
            $err['username'] = 'Username sudah digunakan oleh akun lain.';
        }
    }

    // Validasi No. HP Unik
    $hp = '';
    if ($v['role'] === 'user' && $v['no_hp'] === '') {
        $err['no_hp'] = 'Nomor HP/WhatsApp wajib diisi untuk Pengguna biasa.';
    } elseif ($v['no_hp'] !== '') {
        $hp = normal_hp($v['no_hp']);
        if ($hp === '') {
            $err['no_hp'] = 'Nomor HP tidak valid (contoh: 0858xxxxxxxx).';
        } else {
            $sHp = $db->prepare('SELECT COUNT(*) FROM users WHERE no_hp = ?');
            $sHp->execute([$hp]);
            if ($sHp->fetchColumn() > 0) {
                $err['no_hp'] = 'Nomor HP ini sudah terdaftar pada akun lain.';
            }
        }
    }

    // Validasi Email Unik
    if ($v['email'] === '') {
        $err['email'] = 'Email wajib diisi.';
    } elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $err['email'] = 'Format email tidak valid.';
    } else {
        $sEmail = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $sEmail->execute([$v['email']]);
        if ($sEmail->fetchColumn() > 0) {
            $err['email'] = 'Email ini sudah terdaftar pada akun lain.';
        }
    }

    // Validasi Role & Sandi
    // Admin hanya boleh dibuat oleh Super Admin; Super Admin tidak bisa dibuat lewat form ini.
    $role_boleh = is_superadmin() ? ['user', 'admin'] : ['user'];
    if (!in_array($v['role'], $role_boleh, true)) $err['role'] = is_superadmin() ? 'Pilih peran.' : 'Anda hanya dapat membuat akun Pengguna.';
    if (strlen($pw) < 6) $err['password'] = 'Kata sandi minimal 6 karakter.';
    elseif ($pw !== $pw2) $err['password2'] = 'Konfirmasi kata sandi tidak sama.';

    if (!$err) {
        // Disimpan ter-hash (bukan teks biasa)
        sisip_id('users', 'id_user', [
            'username'   => $v['username'], 
            'organisasi' => $v['organisasi'], 
            'password'   => password_hash($pw, PASSWORD_DEFAULT),
            'sandi_enc'  => sandi_enc($pw),
            'nama'       => $v['nama'], 
            'role'       => $v['role'], 
            'no_hp'      => $hp,
            'email'      => $v['email'],
        ]);

        flash('Akun "' . $v['nama'] . '" (' . $v['organisasi'] . ') berhasil dibuat dengan username: ' . $v['username']);
        header('Location: pengguna.php');
        exit;
    }
}

function err($k) { global $err; return isset($err[$k]) ? '<small class="err" style="color:red; display:block; margin-top:2px;">' . e($err[$k]) . '</small>' : ''; }

$title = 'Buat Akun Baru';
$crumb = ['Manajemen Pengguna' => 'pengguna.php', 1 => $title];
require __DIR__ . '/includes/header.php';
?>

<div class="panel pad" style="max-width: 600px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2 style="margin: 0;">Buat Akun Baru</h2>
        <a href="pengguna.php" class="btn sm">&larr; Kembali</a>
    </div>
    <p class="muted">Akun pengguna dibuat oleh admin. Username, No. HP, dan Email tidak boleh sama dengan akun yang sudah ada.</p>

    <form method="post" data-validate novalidate autocomplete="off">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">

        <label for="organisasi">Komunitas / Organisasi *</label>
        <input id="organisasi" name="organisasi" value="<?= e($v['organisasi']) ?>" required minlength="2" maxlength="100" placeholder="Contoh: OSIS, Pramuka, Kelas 8A">
        <?= err('organisasi') ?>

        <label for="nama">Nama Lengkap *</label>
        <input id="nama" name="nama" value="<?= e($v['nama']) ?>" required minlength="3" maxlength="100">
        <?= err('nama') ?>

        <label for="username">Username *</label>
        <input id="username" name="username" value="<?= e($v['username']) ?>" required minlength="3" maxlength="50" placeholder="contoh: roel.juara">
        <?= err('username') ?>

        <label for="role">Peran *</label>
        <select id="role" name="role" required>
            <option value="user"<?= $v['role'] === 'user' ? ' selected' : '' ?>>Pengguna (hanya meminjam)</option>
            <?php if (is_superadmin()): ?>
            <option value="admin"<?= $v['role'] === 'admin' ? ' selected' : '' ?>>Admin (akses semua, kecuali menghapus)</option>
            <?php endif; ?>
        </select>
        <?= err('role') ?>

        <label for="no_hp">No. HP / WhatsApp <small class="muted">(Wajib untuk Pengguna, Opsional untuk Admin)</small></label>
        <input id="no_hp" name="no_hp" value="<?= e($v['no_hp']) ?>" inputmode="tel" maxlength="20" placeholder="0858xxxxxxxx">
        <?= err('no_hp') ?>

        <label for="email">Email *</label>
        <input id="email" type="email" name="email" value="<?= e($v['email']) ?>" required maxlength="100" placeholder="contoh@domain.com">
        <?= err('email') ?>

        <label for="password">Kata Sandi *</label>
        <?= input_sandi('password', 'password', 'required minlength="6" autocomplete="new-password"') ?>
        <?= err('password') ?>

        <label for="password2">Ulangi Kata Sandi *</label>
        <?= input_sandi('password2', 'password2', 'required minlength="6" autocomplete="new-password"') ?>
        <?= err('password2') ?>

        <div class="actions" style="margin-top: 20px;">
            <button class="btn block" data-loading="Menyimpan...">Buat Akun</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>