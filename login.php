<?php
require __DIR__ . '/includes/bootstrap.php';

$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if (masuk()) {
    if ($ajax) json_keluar(['ok' => true, 'redirect' => beranda_peran()]);
    header('Location: ' . beranda_peran());
    exit;
}

const MAKS_GAGAL = 5;
const DURASI_KUNCI = 60;

$err  = '';
$detik_kunci = 0;
$sisa_coba = null;
$u    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $u    = trim($_POST['username'] ?? '');
    $p    = $_POST['password'] ?? '';
    $sisa = ($_SESSION['kunci'] ?? 0) - time();

    if ($sisa > 0) {
        $err = "Terlalu banyak percobaan. Coba lagi dalam $sisa detik.";
        $detik_kunci = $sisa;
    } elseif ($u === '' || $p === '') {
        $err = 'Username/email dan kata sandi wajib diisi.';
    } else {
        $s = $db->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $s->execute([$u, $u]);
        $r = $s->fetch();

        $hash  = $r && password_verify($p, $r['password']);
        $plain = $r && !$hash && hash_equals($r['password'], $p);

        if ($hash || $plain) {
            if ($plain) {
                $db->prepare('UPDATE users SET password = ? WHERE id_user = ?')
                   ->execute([password_hash($p, PASSWORD_DEFAULT), $r['id_user']]);
            }
            if (sandi_dec($r['sandi_enc'] ?? null) !== $p && ($e = sandi_enc($p)) !== null) {
                $db->prepare('UPDATE users SET sandi_enc = ? WHERE id_user = ?')->execute([$e, $r['id_user']]);
            }
            unset($_SESSION['gagal'], $_SESSION['kunci']);
            $next = $_SESSION['next'] ?? null;
            login_sebagai($r);
            flash('Login berhasil. Selamat datang, ' . $r['nama'] . '!', 'sukses');
            
            $khusus = ['dashboard.php', 'laporan.php', 'pengguna.php', 'form.php'];
            $tujuan = $next && !(!peran_admin($r['role']) && in_array(strtok($next, '?'), $khusus, true)) ? $next : (peran_admin($r['role']) ? 'dashboard.php' : 'barang.php');
            unset($_SESSION['next']);
            if ($ajax) json_keluar(['ok' => true, 'redirect' => $tujuan]);
            header('Location: ' . $tujuan);
            exit;
        }

        $_SESSION['gagal'] = ($_SESSION['gagal'] ?? 0) + 1;
        if ($_SESSION['gagal'] >= MAKS_GAGAL) {
            $_SESSION['kunci'] = time() + DURASI_KUNCI;
            $_SESSION['gagal'] = 0;
            $detik_kunci = DURASI_KUNCI;
            $err = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . DURASI_KUNCI . ' detik.';
        } else {
            $sisa_coba = MAKS_GAGAL - $_SESSION['gagal'];
            $err = 'Username atau password salah, harap login kembali.';
        }
    }
    if ($ajax) json_keluar(['ok' => false, 'pesan' => $err, 'kunci' => $detik_kunci, 'sisa' => $sisa_coba]);
}

// Template pesan WhatsApp permintaan pembuatan akun (user tinggal mengganti isian dalam tanda kurung)
$pesan_akun = "Assalamu'alaikum Admin Lab, saya (Nama Anda) dari organisasi/komunitas (Nama organisasi/kelas/instansi) "
    . "minta tolong untuk dibuatkan akun peminjaman inventaris pak pada platform " . APLIKASI . " " . SEKOLAH . ".\n\n"
    . "Berikut data untuk pembuatan akun:\n"
    . "1. Komunitas / Organisasi : (contoh: OSIS / Pramuka / Kelas 8A)\n"
    . "2. Nama Lengkap : (nama lengkap Anda)\n"
    . "3. Username : (contoh: roel.juara, min. 3 karakter)\n"
    . "4. Peran : Pengguna (hanya meminjam)\n"
    . "5. No. HP / WhatsApp : (contoh: 0858xxxxxxxx)\n"
    . "6. Email : (contoh: nama@gmail.com)\n"
    . "7. Kata Sandi sementara : (min. 6 karakter, akan saya ganti di menu Profil setelah login)\n\n"
    . "Mohon diinformasikan kembali jika akun sudah dibuat. Terima kasih banyak, Pak. \u{1F64F}";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk | <?= SEKOLAH ?></title>
<link rel="icon" type="image/png" href="assets/img/favicon.png">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>">
<style>
/* --- BASE & LAYOUT LAYAR UTAMA --- */
body.login-page {
    margin: 0;
    padding: 0;
    min-height: 100vh;
    background: radial-gradient(circle at 10% 20%, #0d542c 0%, #062b16 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow-x: hidden;
    position: relative;
}

/* Pattern Grid & Glow Ornaments */
body.login-page::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
    background-size: 32px 32px;
    pointer-events: none;
}

.bg-ornament {
    position: fixed;
    border-radius: 50%;
    filter: blur(90px);
    opacity: 0.35;
    pointer-events: none;
    animation: floatGlow 12s ease-in-out infinite alternate;
}
.bg-ornament-1 { top: -10%; left: -10%; width: 500px; height: 500px; background: #20b262; }
.bg-ornament-2 { bottom: -10%; right: -10%; width: 600px; height: 600px; background: #0b4122; }

@keyframes floatGlow {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(40px, 30px) scale(1.1); }
}

/* --- SPLIT CONTAINER --- */
.login-wrapper {
    position: relative;
    z-index: 2;
    display: flex;
    width: 100%;
    max-width: 980px;
    margin: 20px 24px;
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    animation: cardAppear 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes cardAppear {
    from { opacity: 0; transform: translateY(24px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

/* SISI KIRI: BRANDING HERO */
.login-hero {
    flex: 1.1;
    background: linear-gradient(135deg, #10703e 0%, #0b4122 100%);
    padding: 48px;
    color: #ffffff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
}

.login-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top left, rgba(255,255,255,0.15), transparent 70%);
    pointer-events: none;
}

.hero-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}

.hero-header img {
    height: 52px;
    width: auto;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));
    animation: pulseLogo 3s ease-in-out infinite alternate;
}

@keyframes pulseLogo {
    0% { transform: scale(1); }
    100% { transform: scale(1.04); }
}

.hero-body h2 {
    font-size: 1.85rem;
    font-weight: 700;
    margin: 0 0 10px 0;
    line-height: 1.25;
}

.hero-body p {
    font-size: 0.95rem;
    opacity: 0.9;
    line-height: 1.6;
    margin: 0 0 20px 0;
}

/* Animasi Bergerak untuk Daftar Fitur / Keunggulan */
.hero-features {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.hero-features li {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.9rem;
    opacity: 0;
    transform: translateX(-20px);
    animation: slideInFeature 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.hero-features li:nth-child(1) { animation-delay: 0.2s; }
.hero-features li:nth-child(2) { animation-delay: 0.35s; }
.hero-features li:nth-child(3) { animation-delay: 0.5s; }

@keyframes slideInFeature {
    to { opacity: 0.95; transform: translateX(0); }
}

.hero-features svg {
    width: 20px;
    height: 20px;
    color: #4ade80;
    flex-shrink: 0;
    animation: bounceIcon 2s ease-in-out infinite;
}

@keyframes bounceIcon {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-3px); }
}

/* SISI KANAN: FORM LOGIN */
.login-form-side {
    flex: 1;
    padding: 48px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background: #ffffff;
}

.login-form-side h1 {
    font-size: 1.5rem;
    color: #111827;
    margin: 0 0 6px 0;
}

.login-form-side p.subtitle {
    color: #6b7280;
    font-size: 0.9rem;
    margin: 0 0 24px 0;
}

/* --- ANIMASI FADE-IN ELEMEN FORM --- */
.anim { opacity: 0; animation: fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.d1 { animation-delay: 0.1s; } .d2 { animation-delay: 0.2s; }
.d3 { animation-delay: 0.3s; } .d4 { animation-delay: 0.4s; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Alert Error Shake */
.alert.error { animation: shake 0.4s cubic-bezier(0.36, 0.07, 0.19, 0.97) both; }
@keyframes shake {
    20%, 80% { transform: translateX(-4px); }
    40%, 60% { transform: translateX(4px); }
}

/* --- FORM INPUTS & PASSWORD TOGGLE --- */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px; }

.pw { position: relative; display: flex; align-items: center; }
.pw input { width: 100%; padding-right: 44px !important; transition: all 0.25s ease; }
.pw input:focus { box-shadow: 0 0 0 3px rgba(16, 112, 62, 0.2); border-color: #10703e; }

.pw .pw-toggle {
    position: absolute; right: 8px; background: none; border: none; padding: 6px;
    cursor: pointer; color: #6b7280; border-radius: 50%;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.pw .pw-toggle:hover { color: #10703e; background: rgba(16, 112, 62, 0.08); transform: scale(1.08); }
.pw .pw-toggle:active { transform: scale(0.92); }
.pw .pw-toggle svg { width: 20px; height: 20px; display: block; }

/* --- SMOOTH BUTTON SUBMIT --- */
.btn-submit {
    position: relative; display: inline-flex; align-items: center; justify-content: center;
    width: 100%; height: 44px; overflow: hidden; border-radius: 8px;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.btn-submit:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16, 112, 62, 0.3); }
.btn-submit:active:not(:disabled) { transform: translateY(0) scale(0.98); }

.btn-text, .btn-loader {
    display: inline-flex; align-items: center; gap: 8px;
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
}
.btn-loader { position: absolute; opacity: 0; transform: translateY(12px); pointer-events: none; }

.btn-submit.is-submitting { opacity: 0.92; cursor: wait; }
.btn-submit.is-submitting .btn-text { opacity: 0; transform: translateY(-12px); }
.btn-submit.is-submitting .btn-loader { opacity: 1; transform: translateY(0); }

.spinner-icon {
    width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Tautan dan Footer Form */
.akun-info { font-size: 0.85rem; color: #6b7280; margin-top: 18px; text-align: center; }
.back-link { display: inline-block; font-size: 0.875rem; color: #10703e; text-decoration: none; transition: transform 0.2s ease; }
.back-link:hover { transform: translateX(-4px); text-decoration: underline; }

/* --- FOOTER HAK CIPTA DI LUAR KARTU --- */
.page-footer {
    position: relative;
    z-index: 2;
    color: rgba(255, 255, 255, 0.75);
    font-size: 0.825rem;
    text-align: center;
    margin-top: 8px;
    margin-bottom: 20px;
    padding: 0 16px;
    line-height: 1.5;
}

/* --- RESPONSIF KHUSUS HP (TAMPILAN RINGKAS & RAPI) --- */
@media (max-width: 820px) {
    .login-wrapper {
        flex-direction: column;
        margin: 12px;
        max-width: 420px;
    }
    .login-hero {
        padding: 24px 20px;
        text-align: center;
    }
    .hero-header {
        justify-content: center;
        margin-bottom: 14px;
    }
    .hero-header img {
        height: 38px; /* Diperkecil agar pas */
    }
    .hero-body h2 {
        font-size: 1.35rem; /* Judul lebih proporsional di HP */
        margin-bottom: 6px;
    }
    .hero-body p {
        font-size: 0.825rem;
        margin-bottom: 14px;
    }
    .hero-features {
        align-items: flex-start;
        text-align: left;
        margin-top: 10px;
        background: rgba(0, 0, 0, 0.15);
        padding: 10px 12px;
        border-radius: 10px;
        gap: 8px;
    }
    .hero-features li {
        font-size: 0.8rem; /* Teks fitur lebih kecil & rapi */
    }
    .hero-features svg {
        width: 16px;
        height: 16px;
    }
    .login-form-side {
        padding: 28px 20px;
    }
}

/* --- Interaksi --- */
.form-group{transition:transform .2s}.form-group:focus-within{transform:translateY(-2px)}
.form-group input:focus{box-shadow:0 0 0 4px rgba(32,178,98,.18)}
.btn-submit{position:relative;overflow:hidden}
.btn-submit::before{content:"";position:absolute;top:0;left:-70%;width:50%;height:100%;background:linear-gradient(120deg,transparent,rgba(255,255,255,.35),transparent);transform:skewX(-20deg);transition:left .6s}
.btn-submit:hover::before{left:130%}
.login-err{display:block;margin:2px 0 10px;padding:9px 12px;border-radius:8px;background:#fbe3e0;border-left:4px solid #c0392b;color:#8f2418;font-size:13.5px;font-weight:600;animation:shake .4s}
.login-err[hidden]{display:none}
input.salah{border-color:#c0392b!important;box-shadow:0 0 0 4px rgba(192,57,43,.14)!important}
.goyang{animation:shake .4s cubic-bezier(.36,.07,.19,.97)}
.btn-submit.berhasil{background:#1f9d57!important}.btn-submit.berhasil .spinner-icon{display:none}

</style>
<script src="<?= aset('assets/js/app.js') ?>" defer></script>
<script src="<?= aset('assets/js/ui.js') ?>" defer></script>
</head>
<body class="login-page">

<!-- Ornamen Glowing Latar Belakang -->
<div class="bg-ornament bg-ornament-1"></div>
<div class="bg-ornament bg-ornament-2"></div>

<div class="login-wrapper">
    <!-- Sisi Kiri: Branding Madrasah & Keunggulan -->
    <div class="login-hero">
        <div class="hero-header anim d1">
            <img src="assets/img/logo-kemenag.png" alt="Logo Kemenag">
            <img src="assets/img/logo-mtsn1.png" alt="Logo <?= SEKOLAH ?>">
        </div>
        
        <div class="hero-body anim d2">
            <h2><?= SEKOLAH ?></h2>
            <p><?= APLIKASI ?></p>
            <ul class="hero-features">
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Pencatatan inventaris barang laboratorium terpadu</span>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Pengajuan & peminjaman aset lab secara online</span>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Laporan kondisi dan perawatan barang real-time</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Sisi Kanan: Form Login -->
    <div class="login-form-side">
        <h1 class="anim d1">Selamat Datang</h1>
        <p class="subtitle anim d1">Silakan masuk menggunakan akun Anda</p>
        
        <form method="post" id="loginForm" data-validate novalidate>
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            
            <div class="form-group anim d2">
                <label for="username">Username atau Email</label>
                <input id="username" name="username" value="<?= e($u) ?>" required maxlength="100" autocomplete="username" autofocus placeholder="Masukkan username atau email">
            </div>
            
            <div class="form-group anim d3">
                <label for="password">Kata Sandi</label>
                <div class="pw">
                    <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan kata sandi">
                    <button type="button" class="pw-toggle" data-toggle-pw="password" aria-label="Tampilkan password">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 15c2.5-4.5 5.5-7 9-7s6.5 2.5 9 7"></path>
                            <circle cx="12" cy="15" r="2.8"></circle>
                        </svg>
                    </button>
                </div>
            </div>
            
            <small class="login-err" id="loginErr" role="alert" data-kunci="<?= (int) $detik_kunci ?>"<?= $err ? '' : ' hidden' ?>><?= e($err) ?></small>
            <button type="submit" id="btnSubmit" class="btn btn-submit anim d4" style="margin-top: 12px;">
                <span class="btn-text">Masuk</span>
                <span class="btn-loader">
                    <span class="spinner-icon"></span>
                    <span>Memproses...</span>
                </span>
            </button>
            
            <p class="akun-info anim d4">
                Belum punya akun?
                <a href="<?= e(wa_link(WA_ADMIN, $pesan_akun)) ?>" target="_blank" rel="noopener">Hubungi Admin WhatsApp</a>
            </p>
            
            <div class="anim d4" style="text-align: center; margin-top: 16px;">
                <a class="back-link" href="index.php">&larr; Kembali ke beranda</a>
            </div>
        </form>
    </div>
</div>

<!-- Footer Hak Cipta di Bawah Luar Kartu Login -->
<footer class="page-footer anim d4">
    <?= copyright() ?>
</footer>

<div class="toasts" id="toasts" aria-live="polite"></div>
<?= flash_js() ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const $ = id => document.getElementById(id);
    const form = $('loginForm'), btn = $('btnSubmit'), err = $('loginErr'), pw = $('password'), user = $('username');
    let timer;
    const tampil = (teks, anim = true) => {
        err.textContent = teks; err.hidden = false; pw.classList.add('salah');
        if (anim) { form.classList.remove('goyang'); void form.offsetWidth; form.classList.add('goyang'); }
    };
    const reset = () => { err.hidden = true; pw.classList.remove('salah'); };
    const kunci = s => {
        const pesan = () => tampil('Terlalu banyak percobaan. Coba lagi dalam ' + s + ' detik.', false);
        btn.disabled = true; clearInterval(timer); pesan();
        timer = setInterval(() => {
            if (--s <= 0) { clearInterval(timer); btn.disabled = false; return reset(); }
            pesan();
        }, 1000);
    };
    [user, pw].forEach(i => i.addEventListener('input', () => btn.disabled || reset()));
    if (+err.dataset.kunci > 0) kunci(+err.dataset.kunci);

    form.addEventListener('submit', async e => {
        e.preventDefault(); e.stopImmediatePropagation();
        if (btn.disabled || btn.classList.contains('is-submitting')) return;
        if (!user.value.trim() || !pw.value) { tampil('Username dan password wajib diisi.'); return (user.value.trim() ? pw : user).focus(); }
        reset(); btn.classList.add('is-submitting');
        try {
            const r = await fetch('login.php', { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'fetch' } });
            const j = await r.json();
            if (j.ok) {
                btn.classList.add('berhasil');
                btn.querySelector('.btn-loader span:last-child').textContent = '\u2714 Login berhasil';
                return setTimeout(() => { location.href = j.redirect; }, 800);
            }
            btn.classList.remove('is-submitting'); pw.value = ''; pw.focus();
            j.kunci > 0 ? kunci(j.kunci) : tampil(j.pesan + (j.sisa !== null && j.sisa <= 2 ? ' (Sisa percobaan: ' + j.sisa + ')' : ''));
        } catch (x) {
            btn.classList.remove('is-submitting');
            tampil('Tidak dapat terhubung ke server. Harap periksa koneksi lalu coba lagi.');
        }
    }, true);
});
</script>
</body>
</html>