<?php
/** Manajemen pengguna (khusus admin): daftar akun, hapus akun, dan cetak data pengguna. */
require __DIR__ . '/includes/bootstrap.php';
auth(true);

/** Permintaan dari fetch() (tanpa muat ulang halaman)? */
function permintaan_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

/** Balas hasil aksi: JSON untuk fetch (daftar diperbarui otomatis), atau pesan flash + kembali untuk form biasa. */
function balas_aksi(string $pesan, bool $ok): void
{
    if (permintaan_ajax()) {
        json_keluar(['ok' => $ok, 'pesan' => $pesan]);
    }
    flash($pesan, $ok ? 'sukses' : 'error');
    header('Location: pengguna.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';

    // Aksi: Hapus Akun
    if ($aksi === 'hapus') {
        if (!is_superadmin()) { // hanya Super Admin yang boleh menghapus akun
            balas_aksi('Hanya Super Admin yang dapat menghapus data.', false);
        }
        $id = (int) ($_POST['id'] ?? 0);
        $s  = $db->prepare('SELECT id_user, nama, role FROM users WHERE id_user = ?');
        $s->execute([$id]);
        $u = $s->fetch();

        if (!$u) {
            balas_aksi('Akun tidak ditemukan atau sudah dihapus.', false);
        } elseif ($u['role'] === 'superadmin') {
            balas_aksi('Akun Super Admin tidak dapat dihapus.', false);
        } elseif ($id === user_id()) {
            balas_aksi('Anda tidak dapat menghapus akun Anda sendiri.', false);
        }
        $db->prepare('DELETE FROM users WHERE id_user = ?')->execute([$id]);
        balas_aksi('Akun "' . $u['nama'] . '" berhasil dihapus.', true);
    }

    // Aksi: Reset/Ubah Kata Sandi
    if ($aksi === 'reset_password') {
        if (!is_superadmin()) {
            balas_aksi('Hanya Super Admin yang dapat mengubah sandi akun lain.', false);
        }
        $id      = (int) ($_POST['id'] ?? 0);
        $pw_baru = $_POST['password_baru'] ?? '';

        $s = $db->prepare('SELECT nama, role FROM users WHERE id_user = ?');
        $s->execute([$id]);
        $u = $s->fetch();

        if (!$u) {
            balas_aksi('Akun tidak ditemukan.', false);
        } elseif ($u['role'] === 'superadmin') {
            balas_aksi('Sandi Super Admin hanya dapat diubah dari halaman Profil Saya.', false);
        } elseif (strlen($pw_baru) < 6) {
            balas_aksi('Kata sandi baru minimal 6 karakter.', false);
        }
        $db->prepare('UPDATE users SET password = ?, sandi_enc = ? WHERE id_user = ?')
           ->execute([password_hash($pw_baru, PASSWORD_DEFAULT), sandi_enc($pw_baru), $id]);
        balas_aksi('Kata sandi untuk "' . $u['nama'] . '" berhasil diubah.', true);
    }
}

/** Sandi yang tersimpan sebagai hash (tidak bisa dibaca) — tidak pernah ditampilkan. */
function sandi_ter_hash(string $p): bool
{
    return (bool) preg_match('/^\$(2y|2a|2b|argon2)/', $p);
}

/** Teks sandi untuk Super Admin: salinan terenkripsi, atau sandi lama yang masih teks biasa. Null bila tidak tersedia. */
function sandi_teks(array $u): ?string
{
    $t = sandi_dec($u['sandi_enc'] ?? null);
    if ($t !== null) {
        return $t;
    }
    return sandi_ter_hash((string) $u['password']) ? null : (string) $u['password'];
}

/** Penanda versi data: berubah bila ada akun yang dibuat, diubah, atau dihapus (dipakai pembaruan otomatis). */
function versi_pengguna(array $users): string
{
    return substr(hash_hmac('sha256', serialize($users) . '|' . ($_SESSION['role'] ?? '') . '|' . user_id(), KUNCI_SANDI), 0, 16);
}

// Ambil data pengguna terbaru langsung dari database
$users = $db->query("SELECT u.* FROM users u ORDER BY FIELD(u.role, 'superadmin', 'admin', 'user'), u.id_user")->fetchAll();

// Pembaruan otomatis: halaman menanyakan versi; HTML baru dikirim hanya bila data berubah.
if (isset($_GET['partial'])) {
    $v = versi_pengguna($users);
    if (($_GET['v'] ?? '') === $v) {
        json_keluar(['ubah' => false, 'v' => $v]);
    }
    ob_start();
    tampil_area($users);
    json_keluar(['ubah' => true, 'v' => $v, 'html' => ob_get_clean()]);
}

/** Isi daftar (tabel layar + dokumen cetak). Dipakai saat halaman dibuka dan saat diperbarui otomatis. */
function tampil_area(array $users): void
{
    $ikon_cetak = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6"/><rect x="6" y="14" width="12" height="7" rx="1"/><path d="M6 17H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/></svg>';
    ?>
<div class="panel pad noprint">
    <div class="panel-bar">
        <h2>Daftar Akun Pengguna</h2>
        <div class="bar-act no-print">
            <button type="button" onclick="bukaModalCetak()" class="btn sm slate ico-l"><?= $ikon_cetak ?><span>Cetak Pengguna</span></button>
            <a href="pengguna_tambah.php" class="btn sm ico-l"><span>+ Buat Akun Baru</span></a>
        </div>
    </div>

    <div class="scroll">
        <table class="r tbl-user">
            <thead>
                <tr>
                    <th class="col-id" style="text-align: center;">No</th>
                    <th class="col-org">Komunitas / Organisasi</th>
                    <th class="col-nama">Nama</th>
                    <th class="col-user">Username</th>
                    <th>Kata Sandi</th>
                    <th class="col-role">Peran</th>
                    <th>No. HP</th>
                    <th>Email</th>
                    <th class="col-act no-print" style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $no_screen = 1;
            foreach ($users as $u):
                // Super Admin boleh melihat semua sandi; Admin hanya sandi akunnya sendiri.
                $lihat_sandi = is_superadmin() || (int) $u['id_user'] === user_id();
                // Ubah sandi akun lain: hanya Super Admin (Super Admin sendiri lewat Profil Saya).
                $bisa_sandi = is_superadmin() && $u['role'] !== 'superadmin';
                // Hapus: hanya superadmin, bukan akun superadmin, bukan diri sendiri.
                $bisa_hapus = is_superadmin() && $u['role'] !== 'superadmin' && (int) $u['id_user'] !== user_id();
            ?>
                <tr>
                    <td data-label="No" class="col-id"><?= $no_screen++ ?></td>
                    <td data-label="Komunitas / Organisasi"><?= $u['organisasi'] ? e($u['organisasi']) : '-' ?></td>
                    <td data-label="Nama">
                        <b><?= e($u['nama']) ?></b>
                        <?= (int) $u['id_user'] === user_id() ? ' <span class="badge b-info no-print">Anda</span>' : '' ?>
                    </td>
                    <td data-label="Username"><code><?= e($u['username']) ?></code></td>

                    <td data-label="Kata Sandi">
                        <?php if (!$lihat_sandi): ?>
                            <span class="sandi-kosong" aria-label="Tidak ditampilkan">-</span>
                        <?php elseif (($teks_sandi = sandi_teks($u)) !== null): ?>
                            <div class="pw-display">
                                <input type="password" value="<?= e($teks_sandi) ?>" readonly class="pw-text" id="pw_val_<?= $u['id_user'] ?>" autocomplete="off">
                                <button type="button" class="pw-toggle" data-toggle-pw="pw_val_<?= $u['id_user'] ?>" aria-label="Tampilkan password" title="Klik untuk melihat sandi"><?= ikon_mata() ?></button>
                            </div>
                        <?php else: ?>
                            <span class="muted" title="Akan tampil setelah sandi diubah atau pengguna login berikutnya">Belum tersimpan</span>
                        <?php endif; ?>
                    </td>

                    <td data-label="Peran">
                        <span class="badge <?= ['superadmin' => 'b-info', 'admin' => 'b-ok'][$u['role']] ?? 'b-mute' ?>">
                            <?= e(label_peran($u['role'])) ?>
                        </span>
                    </td>
                    <td data-label="No. HP"><?= $u['no_hp'] ? e($u['no_hp']) : '<span class="muted">-</span>' ?></td>
                    <td data-label="Email"><?= $u['email'] ? str_replace('@', '@<wbr>', e($u['email'])) : '<span class="muted">-</span>' ?></td>

                    <td data-label="Aksi" class="acts no-print col-aksi<?= ($bisa_sandi || $bisa_hapus) ? '' : ' aksi-kosong' ?>">
                        <?php if ($bisa_sandi || $bisa_hapus): ?>
                            <?php if ($bisa_sandi): ?>
                                <button type="button" class="btn sm edit" onclick="openModalSandi(<?= (int) $u['id_user'] ?>, <?= e(json_encode($u['nama'])) ?>, <?= e(json_encode(sandi_teks($u) ?? '')) ?>)">Ubah Sandi</button>
                            <?php endif; ?>

                            <?php if ($bisa_hapus): ?>
                                <form method="post" class="hapus" data-konfirm="hapus" data-judul="Hapus akun ini?" data-teks="Akun tidak bisa masuk lagi." data-nama="<?= e($u['nama'] . ' (' . $u['username'] . ')') ?>">
                                    <input type="hidden" name="csrf" value="<?= csrf() ?>">
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int) $u['id_user'] ?>">
                                    <button type="submit" class="btn sm del">Hapus</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- ===== Dokumen cetak pengguna (hanya tampil saat dicetak) ===== -->
<article class="doc only-print">
    <?php require __DIR__ . '/includes/kop.php'; ?>

    <h3 class="judul only-print">LAPORAN DATA PENGGUNA SISTEM INVENTARIS</h3>
    <p class="subjudul only-print"><span id="cetakKet">Pengguna Biasa</span> &mdash; Per <?= tgl_id() ?></p>

    <section class="panel sect">
        <h2>Daftar Akun Pengguna</h2>
        <table class="lap lap-user" id="tblCetak">
            <colgroup>
                <col class="u-no"><col class="u-org"><col class="u-nama"><col class="u-user">
                <col class="u-sandi"><col class="u-peran"><col class="u-hp"><col class="u-mail">
            </colgroup>
            <thead><tr>
                <th class="c">No</th><th>Komunitas / Organisasi</th><th>Nama</th><th>Username</th>
                <th>Kata Sandi</th><th class="c">Peran</th><th>No. HP</th><th>Email</th>
            </tr></thead>
            <tbody>
            <?php foreach ($users as $u):
                $adm = peran_admin($u['role']);
                // Sandi hanya untuk Super Admin (semua akun) atau pemilik akun itu sendiri (Admin).
                $lihat_sandi = is_superadmin() || (int) $u['id_user'] === user_id();
                $teks_cetak  = $lihat_sandi ? sandi_teks($u) : null;
                // Tidak boleh dilihat -> tanda "-" saja (tanpa tulisan "terenkripsi")
                $sandi = !$lihat_sandi ? '-' : ($teks_cetak === null ? '-' : null);
            ?>
                <tr data-role="<?= $adm ? 'admin' : 'user' ?>">
                    <td class="c no-cetak"></td>
                    <td class="l"><?= $u['organisasi'] ? e($u['organisasi']) : '-' ?></td>
                    <td class="l"><b><?= e($u['nama']) ?></b></td>
                    <td class="l"><?= e($u['username']) ?></td>
                    <?php if ($sandi === null): ?>
                        <!-- Isi diatur JS saat cetak: sandi asli bila mata di tabel dibuka, selain itu titik-titik -->
                        <td class="c sandi" data-pw-id="pw_val_<?= (int) $u['id_user'] ?>">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</td>
                    <?php else: ?>
                        <td class="c sandi"><?= e($sandi) ?></td>
                    <?php endif; ?>
                    <td class="c"><?= e(label_peran($u['role'])) ?></td>
                    <td class="l hp"><?= $u['no_hp'] ? e($u['no_hp']) : '-' ?></td>
                    <td class="l mail"><?= $u['email'] ? str_replace('@', '@<wbr>', e($u['email'])) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
                <tr id="cetakKosong" class="hid"><td colspan="8" class="empty">Tidak ada data pengguna.</td></tr>
            </tbody>
        </table>
    </section>

    <div class="ttd only-print">
        <div><p>Mengetahui,<br>Kepala Madrasah</p><span>(.....................................)</span><small>NIP. ..............................</small></div>
        <div><p>Klaten, <?= tgl_id() ?><br>Pengelola Laboratorium</p><span>(.....................................)</span><small>NIP. ..............................</small></div>
    </div>
</article>
<?php
}

$title = 'Manajemen Pengguna';
$crumb = [$title];
require __DIR__ . '/includes/header.php';
?>

<style>
/* =========================================================
   Tampilan Khusus Manajemen Pengguna (Aksi & Sandi)
   ========================================================= */

/* Kotak Sandi di Baris Tabel: kapsul rapi, klik ikon mata untuk melihat */
.pw-display {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 4px 3px 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    transition: border-color .2s, background .2s, box-shadow .2s;
}
.pw-display:hover { border-color: #cbd5e1; }
.pw-display:has(input[type="text"]) {
    background: #ecfdf5;
    border-color: #34d399;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
}
.pw-display input.pw-text {
    flex: 1 1 auto;
    min-width: 0;
    width: 104px;
    height: auto !important;
    min-height: 0 !important;
    margin: 0;
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    outline: none !important;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.85rem;
    letter-spacing: .04em;
    color: #1b2620;
    text-overflow: ellipsis;
}
.pw-display .pw-toggle {
    position: static;
    flex: 0 0 auto;
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; padding: 0;
    background: transparent; border: 0; border-radius: 50%;
    color: #64748b; cursor: pointer;
    transition: background .2s, color .2s, transform .15s;
}
.pw-display .pw-toggle:hover { background: #dcfce7; color: #047857; }
.pw-display .pw-toggle:active { transform: scale(.9); }
.pw-display .pw-toggle svg { width: 18px; height: 18px; display: block; }
.pw-display:has(input[type="text"]) .pw-toggle { color: #047857; }

/* Tabel pengguna desktop (>= 1300px): lebar kolom tetap supaya tampilan stabil.
   Di bawah 1300px (laptop kecil, tablet, HP) tiap akun tampil sebagai kartu bertumpuk. */
@media (min-width: 1300px) {
    /* Desktop: lebar kolom ditentukan agar nama dan data tidak saling berhimpitan. */
    .scroll { overflow-x: auto; overflow-y: hidden; }
    .tbl-user { table-layout: fixed; width: 100%; min-width: 0; }
    .tbl-user th, .tbl-user td {
        padding: 10px 8px;
        vertical-align: middle;
        box-sizing: border-box;
    }
    .tbl-user th { white-space: normal; line-height: 1.25; }

    /* Pembagian kolom desktop: beri ruang cukup untuk username dan tombol aksi. */
    .tbl-user th:nth-child(1) { width: 4%; }
    .tbl-user th:nth-child(2) { width: 11%; }
    .tbl-user th:nth-child(3) { width: 14%; }
    .tbl-user th:nth-child(4) { width: 12%; }
    .tbl-user th:nth-child(5) { width: 12%; }
    .tbl-user th:nth-child(6) { width: 9%; }
    .tbl-user th:nth-child(7) { width: 8%; }
    .tbl-user th:nth-child(8) { width: 18%; }
    .tbl-user th:nth-child(9) { width: 12%; }

    .tbl-user td[data-label="Komunitas / Organisasi"],
    .tbl-user td[data-label="Nama"] {
        white-space: normal;
        overflow-wrap: break-word;
        word-break: normal;
        line-height: 1.4;
    }
    .tbl-user td[data-label="Nama"] b { display: inline; overflow-wrap: anywhere; }
    .tbl-user td[data-label="Username"] {
        white-space: nowrap;
        overflow-wrap: normal;
        word-break: keep-all;
    }
    .tbl-user td[data-label="Username"] code {
        display: inline-block;
        white-space: nowrap;
        font-size: .82em;
        letter-spacing: -.025em;
    }
    .tbl-user .pw-display { max-width: 100%; box-sizing: border-box; }
    .tbl-user .pw-display input.pw-text {
        width: 76px;
        max-width: calc(100% - 32px);
    }
    .tbl-user td[data-label="Email"] {
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: normal;
        font-size: .9em;
    }
    .tbl-user td[data-label="No. HP"] { white-space: nowrap; }
    .tbl-user td.col-aksi {
        display: table-cell;
        white-space: nowrap;
        text-align: center;
        vertical-align: middle;
        padding-left: 5px;
        padding-right: 5px;
    }
    .tbl-user td.col-aksi:not(.aksi-kosong) {
        white-space: nowrap;
    }
    .tbl-user td.col-aksi form {
        display: inline-block;
        margin: 0 2px;
        vertical-align: middle;
    }
    .tbl-user td.col-aksi .btn {
        display: inline-flex;
        width: auto;
        white-space: nowrap;
        margin: 2px;
    }
}
/* Kontainer Tombol Aksi */
.act-buttons {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    white-space: nowrap;
}

/* Tombol Aksi Modern (Ubah Sandi & Hapus) */
.btn-act {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    font-size: 0.82rem;
    font-weight: 600;
    border-radius: 6px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    line-height: 1.2;
}

/* Gaya Tombol Ubah Sandi (Soft Orange) */
.btn-act-edit {
    background-color: #fff7ed;
    color: #c2410c;
    border-color: #ffedd5;
}

.btn-act-edit:hover {
    background-color: #ea580c;
    color: #ffffff;
    border-color: #ea580c;
    box-shadow: 0 4px 10px rgba(234, 88, 12, 0.25);
}

/* Gaya Tombol Hapus (Soft Red) */
.btn-act-del {
    background-color: #fef2f2;
    color: #dc2626;
    border-color: #fee2e2;
}

.btn-act-del:hover {
    background-color: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
    box-shadow: 0 4px 10px rgba(220, 38, 38, 0.25);
}

/* Modal Popup Ubah Sandi */
.modal-pw-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    backdrop-filter: blur(4px);
}

.modal-pw-overlay.active {
    display: flex;
}

.modal-pw-card {
    background: #ffffff;
    padding: 24px 28px;
    border-radius: 14px;
    width: 90%;
    max-width: 420px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    animation: popCenter 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-sizing: border-box;
}

@keyframes popCenter {
    from { opacity: 0; transform: scale(0.92) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-pw-card h3 {
    margin: 0 0 16px 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 12px;
}

.modal-pw-card label {
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-top: 14px;
    margin-bottom: 6px;
}

.modal-pw-card .pw input {
    width: 100%;
    padding: 8px 44px 8px 12px;
    font-size: 0.9rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.2s;
}
.modal-pw-card .pw input:focus {
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
}
.pw-input-group {
    display: flex;
    gap: 8px;
    align-items: center;
}

.pw-input-group input {
    flex: 1;
    padding: 8px 12px;
    font-size: 0.9rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    outline: none;
    transition: border-color 0.2s;
    box-sizing: border-box;
}

.pw-input-group input:focus {
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
}

.pw-btn-see {
    padding: 8px 14px !important;
    font-size: 0.82rem !important;
    font-weight: 600 !important;
    border-radius: 8px !important;
    white-space: nowrap;
}

.modal-pw-actions {
    display: flex;
    gap: 10px;
    margin-top: 22px;
    justify-content: flex-end;
}

.btn-modal-cancel {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
    padding: 9px 18px;
    font-size: 0.88rem;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-modal-cancel:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.btn-modal-save {
    background: #059669;
    color: #ffffff;
    border: none;
    padding: 9px 20px;
    font-size: 0.88rem;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);
}

.btn-modal-save:hover {
    background: #047857;
    box-shadow: 0 4px 10px rgba(5, 150, 105, 0.35);
}

/* Pilihan cetak (modal) */
.hid { display: none !important; }
.pilih-cetak { display: flex; flex-direction: column; gap: 8px; margin-top: 8px; }
.pilih-cetak button { text-align: left; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc; cursor: pointer; font-size: .92rem; transition: all .15s; }
.pilih-cetak button small { display: block; color: #64748b; font-size: .78rem; margin-top: 2px; }
.pilih-cetak button:hover { background: #ecfdf5; border-color: #059669; }

/* Kolom sandi di layar: tanda "-" bila tidak boleh dilihat */
.sandi-kosong { color: #94a3b8; }

/* Sinkron otomatis: tanpa animasi masuk ulang saat daftar diperbarui */
#areaPengguna.sinkron .panel, #areaPengguna.sinkron tbody tr { animation: none !important; }

@media print {
    /* Ukuran kolom A4 portrait; total tepat 186mm mengikuti area kop laporan. */
    .u-no { width: 7mm; }
    .u-org { width: 25mm; }
    .u-nama { width: 30mm; }
    .u-user { width: 24mm; }
    .u-sandi { width: 17mm; }
    .u-peran { width: 19mm; }
    .u-hp { width: 25mm; }
    .u-mail { width: 39mm; }

    table.lap-user {
        table-layout: fixed;
        width: 186mm !important;
        max-width: 186mm !important;
        border-collapse: collapse;
        border-spacing: 0;
    }
    .lap-user th, .lap-user td {
        box-sizing: border-box;
        padding: 5pt 4pt;
        font-size: 7.6pt;
        line-height: 1.3;
        text-align: left;
        vertical-align: middle;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: normal;
        hyphens: auto;
    }
    .lap-user thead th {
        padding: 6pt 3pt;
        text-align: center;
        vertical-align: middle;
        line-height: 1.2;
        font-size: 7.8pt;
        overflow-wrap: break-word;
    }
    .lap-user td.c, .lap-user th.c {
        text-align: center;
        padding-left: 2pt;
        padding-right: 2pt;
    }
    .lap-user td.l { text-align: left; }

    /* Data panjang turun baris, bukan melewati batas kolom. */
    .lap-user td.hp { white-space: normal; overflow-wrap: anywhere; }
    .lap-user td.sandi {
        text-align: center;
        font-family: "Courier New", monospace;
        font-size: 7.2pt;
        letter-spacing: 0;
        overflow-wrap: anywhere;
    }
    .lap-user td.mail {
        font-size: 7.2pt;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    .lap-user td:nth-child(3), .lap-user td:nth-child(4) { overflow-wrap: anywhere; }

    /* Baris tidak terbelah antarhalaman dan header tabel diulang. */
    .lap-user tr { break-inside: avoid; page-break-inside: avoid; }
    .lap-user thead { display: table-header-group; }

    .judul { margin-top: 2pt; }
    .subjudul { margin-bottom: 12pt; }
    .doc .panel > h2 { margin: 6pt 0 6pt; }
    .ttd { margin-top: 26pt; }
}
</style>

<div id="areaPengguna" data-v="<?= e(versi_pengguna($users)) ?>"><?php tampil_area($users); ?></div>

<!-- Modal Pilihan Cetak -->
<div id="modalCetak" class="modal-pw-overlay noprint" onclick="if(event.target===this) tutupModalCetak()">
    <div class="modal-pw-card">
        <h3>Cetak Data Pengguna</h3>
        <p style="margin:0 0 6px;font-size:.9rem;color:#475569">Pilih data yang ingin dicetak:</p>
        <div class="pilih-cetak">
            <button type="button" onclick="cetakPengguna('user')"><b>Pengguna biasa saja</b><small>Tanpa akun Admin</small></button>
            <button type="button" onclick="cetakPengguna('admin')"><b>Admin saja</b><small>Hanya akun Administrator</small></button>
            <button type="button" onclick="cetakPengguna('semua')"><b>Semua</b><small>Admin &amp; pengguna biasa</small></button>
        </div>
        <div class="modal-pw-actions">
            <button type="button" class="btn-modal-cancel" onclick="tutupModalCetak()">Batal</button>
        </div>
    </div>
</div>

<!-- Modal Universal Ubah Sandi -->
<div id="modalSandi" class="modal-pw-overlay" onclick="if(event.target===this) closeModalSandi()">
    <div class="modal-pw-card">
        <h3 id="modalSandiTitle">Ubah Sandi</h3>
        <form method="post" autocomplete="off" id="formSandi" data-konfirm="simpan" data-judul="Ubah kata sandi akun ini?" data-teks="Kata sandi lama tidak berlaku lagi dan pengguna harus masuk dengan sandi yang baru.">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="aksi" value="reset_password">
            <input type="hidden" name="id" id="modalSandiUserId">

            <div id="modalSandiLamaWrap">
                <label>Sandi Saat Ini:</label>
                <div class="pw">
                    <input type="password" id="modalSandiOld" readonly autocomplete="new-password">
                    <button type="button" class="pw-toggle" data-toggle-pw="modalSandiOld" aria-label="Tampilkan password"><?= ikon_mata() ?></button>
                </div>
            </div>

            <label>Sandi Baru:</label>
            <?= input_sandi('modalSandiNew', 'password_baru', 'required minlength="6" placeholder="Min. 6 karakter" autocomplete="new-password"') ?>

            <div class="modal-pw-actions">
                <button type="button" class="btn-modal-cancel" onclick="closeModalSandi()">Batal</button>
                <button type="submit" class="btn-modal-save" id="btnSimpanSandi">Simpan Sandi</button>
            </div>
        </form>
    </div>
</div>

<script>
const SVG_MATA = <?= json_encode(ikon_mata()) ?>;

function openModalSandi(id, nama, sandiLama) {
    document.getElementById('modalSandiUserId').value = id;
    document.getElementById('modalSandiTitle').innerText = 'Ubah Sandi: ' + nama;
    document.getElementById('formSandi').dataset.nama = nama;
    delete document.getElementById('formSandi').dataset.ok;
    document.getElementById('modalSandiOld').value = sandiLama;
    document.getElementById('modalSandiNew').value = '';
    // Akun Admin: sandi lama tidak ditampilkan
    document.getElementById('modalSandiLamaWrap').style.display = sandiLama === '' ? 'none' : '';
    // Selalu mulai dalam keadaan tersembunyi (ikon mata biasa)
    ['modalSandiOld', 'modalSandiNew'].forEach(i => {
        const el = document.getElementById(i);
        el.type = 'password';
        const b = document.querySelector('[data-toggle-pw="' + i + '"]');
        if (b) { b.innerHTML = SVG_MATA; b.setAttribute('aria-label', 'Tampilkan password'); }
    });
    document.getElementById('modalSandi').classList.add('active');
}
function closeModalSandi() {
    document.getElementById('modalSandi').classList.remove('active');
}

const KET_CETAK = { user: 'Pengguna Biasa', admin: 'Admin', semua: 'Admin & Pengguna Biasa' };
let modeCetak = 'user';

// Tampilkan hanya baris sesuai pilihan, nomor selalu mulai dari 1
function terapkanCetak(mode) {
    modeCetak = mode;
    let n = 0;
    document.querySelectorAll('#tblCetak tbody tr[data-role]').forEach(tr => {
        const tampil = mode === 'semua' || tr.dataset.role === mode;
        tr.classList.toggle('hid', !tampil);
        if (tampil) { tr.querySelector('.no-cetak').textContent = ++n; }
    });
    document.getElementById('cetakKosong').classList.toggle('hid', n > 0);
    document.getElementById('cetakKet').textContent = KET_CETAK[mode];
}

const TITIK_SANDI = '\u2022'.repeat(8);

// Saat cetak: sandi tampil hanya jika ikon mata baris itu sedang dibuka; jika tidak, titik-titik saja.
function sinkronSandiCetak() {
    document.querySelectorAll('#tblCetak td[data-pw-id]').forEach(td => {
        const i = document.getElementById(td.dataset.pwId);
        td.textContent = (i && i.type === 'text') ? i.value : TITIK_SANDI;
    });
}
// Setelah cetak, kembalikan ke titik-titik agar sandi tidak tertinggal di halaman.
function resetSandiCetak() {
    document.querySelectorAll('#tblCetak td[data-pw-id]').forEach(td => { td.textContent = TITIK_SANDI; });
}
window.addEventListener('beforeprint', sinkronSandiCetak);
window.addEventListener('afterprint', resetSandiCetak);

function bukaModalCetak() { document.getElementById('modalCetak').classList.add('active'); }
function tutupModalCetak() { document.getElementById('modalCetak').classList.remove('active'); }

function cetakPengguna(mode) {
    terapkanCetak(mode);
    tutupModalCetak();
    setTimeout(() => window.print(), 100);
}

terapkanCetak('user');

/* =========================================================
   Aksi tanpa muat ulang: hapus akun & ubah sandi dikirim lewat fetch,
   lalu daftar diperbarui otomatis. Daftar juga disegarkan berkala
   (bila ada perubahan dari admin lain) tanpa perlu menekan refresh.
   ========================================================= */
(() => {
    const area = document.getElementById('areaPengguna');
    const info = (teks, tipe) => (window.notify ? window.notify(teks, tipe) : alert(teks));
    let memeriksa = false, urutan = 0;

    async function segarkan(paksa = false) {
        // Pemeriksaan berkala dilewati bila tab tidak aktif / sedang ada dialog; permintaan terbaru selalu menang.
        if (!paksa && (memeriksa || document.hidden || document.querySelector('dialog[open], .modal-pw-overlay.active'))) return;
        const no = ++urutan;
        memeriksa = true;
        try {
            const u = new URL('pengguna.php', location.href);
            u.searchParams.set('partial', '1');
            if (!paksa) u.searchParams.set('v', area.dataset.v || '');
            const r = await fetch(u, { cache: 'no-store', credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
            if (r.redirected && /login\.php/.test(r.url)) { location.href = 'login.php'; return; }
            const d = await r.json();
            if (no !== urutan || !d.ubah) return;
            // Pertahankan ikon mata yang sedang dibuka
            const terbuka = [...area.querySelectorAll('input.pw-text')].filter(i => i.type === 'text').map(i => i.id);
            area.innerHTML = d.html;
            area.dataset.v = d.v;
            area.classList.add('sinkron');
            terbuka.forEach(id => { const b = document.querySelector('[data-toggle-pw="' + id + '"]'); if (b && document.getElementById(id)) b.click(); });
            terapkanCetak(modeCetak);
        } catch (_) { /* koneksi sesaat terputus: coba lagi pada giliran berikutnya */ }
        finally { if (no === urutan) memeriksa = false; }
    }

    async function kirim(form, tombol, teksProses) {
        const asli = tombol ? tombol.textContent : '';
        if (tombol) { tombol.disabled = true; tombol.textContent = teksProses; }
        try {
            const r = await fetch('pengguna.php', { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
            if (r.redirected && /login\.php/.test(r.url)) { location.href = 'login.php'; return false; }
            const d = await r.json();
            info(d.pesan || 'Selesai.', d.ok ? 'sukses' : 'error');
            await segarkan(true);
            return !!d.ok;
        } catch (_) {
            info('Gagal terhubung ke server. Periksa koneksi lalu coba lagi.', 'error');
            return false;
        } finally {
            if (tombol) { tombol.disabled = false; tombol.textContent = asli; }
        }
    }

    document.addEventListener('submit', async e => {
        const f = e.target;
        if (!f.matches || !f.matches('form[data-aksi-ajax]')) return;
        e.preventDefault();
        if (f.dataset.kirim) return;
        f.dataset.kirim = '1';
        try {
            if (f.dataset.aksiAjax === 'hapus') {
                const ya = window.konfirmasi
                    ? await window.konfirmasi('hapus', f.dataset.nama, { judul: f.dataset.judul, teks: f.dataset.teks })
                    : confirm(f.dataset.judul);
                if (ya) { await kirim(f, f.querySelector('button[type=submit]'), 'Menghapus...'); }
            } else if (f.dataset.aksiAjax === 'sandi') {
                if (await kirim(f, document.getElementById('btnSimpanSandi'), 'Menyimpan...')) { closeModalSandi(); }
            }
        } finally {
            delete f.dataset.kirim;
        }
    }, true);

    // Validasi ubah sandi (sebelum dialog konfirmasi muncul)
    document.addEventListener('submit', e => {
        if (e.target.id !== 'formSandi') return;
        const baru = document.getElementById('modalSandiNew').value;
        const lama = document.getElementById('modalSandiOld').value;
        let pesan = '';
        if (baru.trim() === '') pesan = 'Kata sandi baru wajib diisi.';
        else if (baru.length < 6) pesan = 'Kata sandi baru minimal 6 karakter.';
        else if (lama !== '' && baru === lama) pesan = 'Kata sandi baru tidak boleh sama dengan kata sandi lama.';
        if (pesan) { e.preventDefault(); e.stopImmediatePropagation(); info(pesan, 'error'); document.getElementById('modalSandiNew').focus(); }
    }, true);

    // Pembaruan otomatis berkala + saat tab kembali aktif / halaman dibuka dari riwayat
    setInterval(() => segarkan(), 8000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) segarkan(); });
    window.addEventListener('focus', () => segarkan());
    window.addEventListener('pageshow', e => { if (e.persisted) segarkan(true); });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>