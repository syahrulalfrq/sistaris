<?php
/**
 * Bootstrap: sesi, koneksi database, migrasi otomatis, dan fungsi bantu.
 * Dimuat di baris pertama setiap halaman.
 */
require_once __DIR__ . '/config.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// Halaman dinamis tidak disimpan cache browser/proxy: data selalu terbaru (juga saat tombol Kembali ditekan).
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

try {
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Samakan zona waktu MySQL dengan PHP (WIB) agar jam pengajuan tampil benar
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Koneksi database gagal. Periksa pengaturan di includes/config.php.');
}

/* =========================================================
   Migrasi otomatis (aman dijalankan berulang kali)
   - lab.kode_lab         : awalan kode barang per laboratorium
   - users.role / no_hp   : admin atau pengguna biasa
   - tabel peminjaman     : permintaan pinjam barang
   ========================================================= */
const MIGRASI_VERSI = 10;

function kolom_ada(string $tabel, string $kolom): bool
{
    global $db;
    $s = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $s->execute([$tabel, $kolom]);
    return (int) $s->fetchColumn() > 0;
}

function awalan_dari_nama(string $nama): string
{
    $h = preg_replace('/[^A-Z]/', '', strtoupper(preg_replace('/^lab(oratorium)?\s+/i', '', $nama)));
    return substr(str_pad($h, 3, 'X'), 0, 3);
}

function migrasi(): void
{
    global $db;
    if (($_SESSION['migrasi'] ?? 0) === MIGRASI_VERSI) {
        return;
    }

    // 1) Kode laboratorium
    if (!kolom_ada('lab', 'kode_lab')) {
        $db->exec('ALTER TABLE lab ADD COLUMN kode_lab VARCHAR(5) NULL AFTER nama_lab');
    }
    $pakai = [];
    foreach ($db->query('SELECT id_lab, nama_lab, kode_lab FROM lab ORDER BY id_lab')->fetchAll() as $l) {
        if ($l['kode_lab'] !== null && $l['kode_lab'] !== '') {
            $pakai[$l['kode_lab']] = true;
        }
    }
    foreach ($db->query('SELECT id_lab, nama_lab FROM lab WHERE kode_lab IS NULL OR kode_lab = \'\' ORDER BY id_lab')->fetchAll() as $l) {
        // Pakai awalan yang sudah dipakai barang di lab tersebut (mis. POD-001 -> POD).
        $s = $db->prepare('SELECT SUBSTRING_INDEX(kode_barang, \'-\', 1) AS a, COUNT(*) AS n FROM barang WHERE id_lab = ? GROUP BY a ORDER BY n DESC LIMIT 1');
        $s->execute([$l['id_lab']]);
        $a = $s->fetchColumn();
        $a = $a && preg_match('/^[A-Z]{2,5}$/', $a) && empty($pakai[$a]) ? $a : awalan_dari_nama($l['nama_lab']);
        $dasar = $a;
        for ($i = 2; !empty($pakai[$a]); $i++) {
            $a = substr($dasar, 0, 4) . $i;
        }
        $pakai[$a] = true;
        $db->prepare('UPDATE lab SET kode_lab = ? WHERE id_lab = ?')->execute([$a, $l['id_lab']]);
    }

    // 2) Peran pengguna
    if (!kolom_ada('users', 'role')) {
        $db->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER nama");
        $db->exec("UPDATE users SET role = 'admin'"); // semua akun lama adalah admin
    }
    if (!kolom_ada('users', 'no_hp')) {
        $db->exec('ALTER TABLE users ADD COLUMN no_hp VARCHAR(20) NULL AFTER role');
    }
    if (!kolom_ada('users', 'organisasi')) {
        $db->exec('ALTER TABLE users ADD COLUMN organisasi VARCHAR(100) NULL AFTER username');
    }

    // 2b) Tiga peran: superadmin (admin utama), admin, user
    $db->exec("ALTER TABLE users MODIFY role ENUM('superadmin','admin','user') NOT NULL DEFAULT 'user'");
    if ((int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn() === 0) {
        // Belum ada superadmin: jadikan akun 'admin' (atau admin dengan ID terkecil) sebagai admin utama.
        $id = $db->query("SELECT id_user FROM users WHERE role = 'admin' ORDER BY (username = 'admin') DESC, id_user LIMIT 1")->fetchColumn();
        if ($id) {
            $db->prepare("UPDATE users SET role = 'superadmin' WHERE id_user = ?")->execute([$id]);
        }
    }

    // 2c) Salinan sandi terenkripsi (hanya dapat dibaca Super Admin)
    if (!kolom_ada('users', 'sandi_enc')) {
        $db->exec('ALTER TABLE users ADD COLUMN sandi_enc VARCHAR(255) NULL');
    }

    // 3) Peminjaman
    $kolom = "
        id_pinjam INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        id_barang INT UNSIGNED NOT NULL,
        id_user INT UNSIGNED NULL,
        nama_peminjam VARCHAR(100) NOT NULL,
        no_hp VARCHAR(20) NOT NULL,
        jumlah INT UNSIGNED NOT NULL DEFAULT 1,
        keperluan VARCHAR(300) NOT NULL,
        tgl_pinjam DATE NOT NULL,
        tgl_kembali_rencana DATE NULL,
        tgl_kembali DATE NULL,
        status ENUM('pending','disetujui','dikembalikan','ditolak','dibatalkan') NOT NULL DEFAULT 'pending',
        diproses_oleh VARCHAR(100) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_pinjam_barang (id_barang),
        KEY idx_pinjam_user (id_user),
        KEY idx_pinjam_status (status)";
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS peminjaman ($kolom,
            CONSTRAINT fk_pinjam_barang FOREIGN KEY (id_barang) REFERENCES barang (id_barang) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_pinjam_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        // Jika kunci asing gagal dibuat (tipe kolom berbeda), buat tanpa kunci asing.
        $db->exec("CREATE TABLE IF NOT EXISTS peminjaman ($kolom) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // 4) Surat resmi peminjaman (opsional, format bebas)
    if (!kolom_ada('peminjaman', 'surat_file')) {
        $db->exec('ALTER TABLE peminjaman ADD COLUMN surat_file VARCHAR(100) NULL AFTER keperluan');
    }
    if (!kolom_ada('peminjaman', 'surat_nama')) {
        $db->exec('ALTER TABLE peminjaman ADD COLUMN surat_nama VARCHAR(255) NULL AFTER surat_file');
    }

    // 5) Rapikan nomor peminjaman agar mulai dari 1 tanpa celah
    rapikan_pinjam();

    // 6) Tanggal rencana kembali (batas pengembalian) boleh kosong untuk data lama; data baru wajib mengisinya lewat formulir
    $db->exec('ALTER TABLE peminjaman MODIFY tgl_kembali_rencana DATE NULL');

    // 7) Waktu (tanggal + jam) pinjam tercatat otomatis oleh server saat pengajuan dikirim
    if (!kolom_ada('peminjaman', 'waktu_pinjam')) {
        $db->exec('ALTER TABLE peminjaman ADD COLUMN waktu_pinjam DATETIME NULL AFTER tgl_pinjam');
    }

    $_SESSION['migrasi'] = MIGRASI_VERSI;
}
migrasi();

// Daftar laboratorium [id => nama], dipakai oleh menu dan filter.
$labs = $db->query('SELECT id_lab, nama_lab FROM lab ORDER BY id_lab')->fetchAll(PDO::FETCH_KEY_PAIR);

/* ---------- Surat resmi peminjaman ---------- */

const DIR_SURAT      = __DIR__ . '/../uploads/surat';
const MAKS_SURAT_MB  = 10;

/**
 * Simpan surat resmi dari $_FILES['surat'] (opsional, format bebas).
 * Mengembalikan [nama_file_di_server, nama_asli], null bila tidak ada file, atau false bila gagal ($galat berisi alasannya).
 * File disimpan dengan nama acak tanpa ekstensi, sehingga tidak bisa dijalankan server meski berisi kode.
 */
function simpan_surat(?array $f, ?string &$galat = null)
{
    $galat = null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > MAKS_SURAT_MB * 1048576) {
        $galat = 'Ukuran surat maksimal ' . MAKS_SURAT_MB . ' MB.';
        return false;
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $galat = 'Surat gagal diunggah, silakan coba lagi.';
        return false;
    }
    if (!is_dir(DIR_SURAT) && !@mkdir(DIR_SURAT, 0775, true)) {
        $galat = 'Folder penyimpanan surat tidak dapat dibuat.';
        return false;
    }
    if (!is_file(DIR_SURAT . '/.htaccess')) {
        @file_put_contents(DIR_SURAT . '/.htaccess', "Require all denied\n");
    }
    $asli = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename((string) $f['name']));
    $asli = mb_substr(trim($asli) !== '' ? trim($asli) : 'surat', 0, 200);
    $nama = bin2hex(random_bytes(16));
    if (!move_uploaded_file($f['tmp_name'], DIR_SURAT . '/' . $nama)) {
        $galat = 'Surat gagal disimpan di server.';
        return false;
    }
    return [$nama, $asli];
}

function hapus_surat(?string $file): void
{
    if ($file && preg_match('/^[a-f0-9]{32}$/', $file) && is_file(DIR_SURAT . '/' . $file)) {
        @unlink(DIR_SURAT . '/' . $file);
    }
}

/* ---------- Umum ---------- */

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function tgl($d): string
{
    return $d ? date('d-m-Y', strtotime($d)) : '-';
}

function tgl_id(?string $d = null): string
{
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $t = $d ? strtotime($d) : time();
    return date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
}

function opts(array $list, $selected = ''): void
{
    foreach ($list as $k => $nama) {
        $sel = (string) $k === (string) $selected ? ' selected' : '';
        echo '<option value="' . e($k) . '"' . $sel . '>' . e($nama) . '</option>';
    }
}

function daftar_kondisi(): array
{
    return array_combine(array_keys(KONDISI), array_keys(KONDISI));
}

function json_keluar(array $data, int $kode = 200): void
{
    http_response_code($kode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- Autentikasi, peran & keamanan ---------- */

function login_sebagai(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid']      = (int) $u['id_user'];
    $_SESSION['user']     = $u['nama'];
    $_SESSION['username'] = $u['username'];
    $_SESSION['role']     = $u['role'];
}

function masuk(): bool
{
    return !empty($_SESSION['user']) && !empty($_SESSION['uid']);
}

function user_id(): int
{
    return (int) ($_SESSION['uid'] ?? 0);
}

/** Admin = superadmin ATAU admin biasa (akses kelola data). */
function is_admin(): bool
{
    return in_array($_SESSION['role'] ?? '', ['superadmin', 'admin'], true);
}

/** Admin utama: satu-satunya yang boleh menghapus data dan membuat/mengelola admin lain. */
function is_superadmin(): bool
{
    return ($_SESSION['role'] ?? '') === 'superadmin';
}

function label_peran(string $role): string
{
    return ['superadmin' => 'Super Admin', 'admin' => 'Admin'][$role] ?? 'Pengguna';
}

/** Peran khusus admin (superadmin & admin) — dipakai untuk memeriksa data akun lain. */
function peran_admin(string $role): bool
{
    return in_array($role, ['superadmin', 'admin'], true);
}

/** Wajib superadmin untuk aksi hapus. Jika bukan, tampilkan pesan lalu kembali ke $tujuan. */
function wajib_superadmin(string $tujuan = 'dashboard.php'): void
{
    if (!is_superadmin()) {
        flash('Hanya Super Admin yang dapat menghapus data.');
        header('Location: ' . $tujuan);
        exit;
    }
}

/** Halaman awal sesuai peran. */
function beranda_peran(): string
{
    return is_admin() ? 'dashboard.php' : 'barang.php';
}

/**
 * Wajib login. $admin = true: halaman khusus admin.
 * Akun yang sudah dihapus admin otomatis dikeluarkan.
 */
function auth(bool $admin = false): void
{
    global $db;
    if (masuk()) {
        $s = $db->prepare('SELECT nama, role FROM users WHERE id_user = ?');
        $s->execute([user_id()]);
        $u = $s->fetch();
        if ($u) {
            $_SESSION['user'] = $u['nama'];
            $_SESSION['role'] = $u['role'];
            if ($admin && !peran_admin($u['role'])) {
                flash('Halaman tersebut khusus admin.');
                header('Location: barang.php');
                exit;
            }
            return;
        }
        // Akun sudah tidak ada
        $_SESSION = [];
        session_regenerate_id(true);
    }

    // Simpan halaman tujuan (hanya halaman internal) agar setelah login langsung ke sana.
    $tujuan = basename($_SERVER['PHP_SELF']) . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['partial']) && preg_match('/^[a-z_]+\.php(\?[^\r\n]*)?$/', $tujuan)) {
        $_SESSION['next'] = $tujuan;
    }
    flash('Silakan masuk terlebih dahulu untuk mengakses halaman tersebut.', 'info');
    header('Location: login.php');
    exit;
}

function csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function cek_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
            json_keluar(['ok' => false, 'pesan' => 'Sesi formulir sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.'], 419);
        }
        flash('Sesi formulir sudah kedaluwarsa. Silakan coba lagi.', 'error');
        $balik = basename($_SERVER['PHP_SELF']) . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
        header('Location: ' . $balik);
        exit;
    }
}

/** Pesan sekali tampil. $tipe: 'sukses' | 'error' | 'info' (kosong = ditebak dari isi pesan). */
function flash(?string $pesan = null, ?string $tipe = null): ?string
{
    if ($pesan !== null) {
        $ok = preg_match('/berhasil|disetujui|ditolak|dibatalkan|ditandai sudah/i', $pesan) && !preg_match('/tidak|hanya/i', $pesan);
        $_SESSION['flash'] = [$pesan, $tipe ?? ($ok ? 'sukses' : 'error')];
        return null;
    }
    [$m, $GLOBALS['flash_tipe']] = $_SESSION['flash'] ?? [null, 'sukses'];
    unset($_SESSION['flash']);
    return $m;
}

/** Skrip kecil yang dibaca ui.js untuk menampilkan pesan flash sebagai toast. */
function flash_js(): string
{
    $m = flash();
    return $m === null ? '' : '<script>window.__flash=' . json_encode([$m, $GLOBALS['flash_tipe']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
}

/** Alamat aset + versi (agar browser selalu memuat berkas terbaru). */
function aset(string $f): string
{
    return $f . '?v=' . filemtime(__DIR__ . '/../' . $f);
}

/* ---------- Nomor otomatis ---------- */

/** Awalan kode barang untuk satu laboratorium (mis. POD). */
function kode_lab(int $id_lab): string
{
    global $db;
    $s = $db->prepare('SELECT nama_lab, kode_lab FROM lab WHERE id_lab = ?');
    $s->execute([$id_lab]);
    $l = $s->fetch();
    if (!$l) {
        return '';
    }
    if ($l['kode_lab'] !== null && $l['kode_lab'] !== '') {
        return $l['kode_lab'];
    }
    $a = awalan_dari_nama($l['nama_lab']);
    $db->prepare('UPDATE lab SET kode_lab = ? WHERE id_lab = ?')->execute([$a, $id_lab]);
    return $a;
}

/**
 * Kode barang berikutnya untuk laboratorium: mengisi nomor kosong terkecil
 * (mis. POD-001, POD-002, POD-004 -> POD-003). $kecuali = id barang yang sedang diedit.
 */
function kode_berikut(int $id_lab, int $kecuali = 0): string
{
    global $db;
    $awalan = kode_lab($id_lab);
    if ($awalan === '') {
        return '';
    }
    $s = $db->prepare('SELECT kode_barang FROM barang WHERE kode_barang LIKE ? AND id_barang <> ?');
    $s->execute([$awalan . '-%', $kecuali]);
    $dipakai = [];
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $k) {
        if (preg_match('/^' . preg_quote($awalan, '/') . '-(\d+)$/', $k, $m)) {
            $dipakai[(int) $m[1]] = true;
        }
    }
    $n = 1;
    while (isset($dipakai[$n])) {
        $n++;
    }
    return sprintf('%s-%03d', $awalan, $n);
}

/**
 * Rapikan kode barang satu laboratorium agar urut tanpa celah.
 * Contoh: KOM-001..KOM-004, KOM-006 (KOM-005 dihapus) -> KOM-006 menjadi KOM-005.
 * Urutan lama dipertahankan; hanya nomornya yang naik mengisi tempat kosong.
 */
function rapikan_kode(int $id_lab): void
{
    global $db;
    $awalan = kode_lab($id_lab);
    if ($awalan === '') {
        return;
    }
    $s = $db->prepare('SELECT id_barang, kode_barang FROM barang WHERE kode_barang LIKE ?');
    $s->execute([$awalan . '-%']);
    $baris = [];
    foreach ($s->fetchAll() as $r) {
        if (preg_match('/^' . preg_quote($awalan, '/') . '-(\d+)$/', $r['kode_barang'], $m)) {
            $baris[] = [(int) $m[1], (int) $r['id_barang'], $r['kode_barang']];
        }
    }
    usort($baris, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

    $ubah = $db->prepare('UPDATE barang SET kode_barang = ? WHERE id_barang = ?');
    $n = 0;
    foreach ($baris as [, $id, $lama]) {
        $n++;
        $baru = sprintf('%s-%03d', $awalan, $n);
        if ($baru !== $lama) {
            $ubah->execute([$baru, $id]);
        }
    }
}

/**
 * Rapikan nomor peminjaman (id_pinjam) agar urut mulai dari 1 tanpa celah.
 * Contoh: #1, #2, #4 (#3 dihapus) -> #4 menjadi #3. Urutan lama dipertahankan.
 * Nomor berikutnya (AUTO_INCREMENT) ikut disesuaikan.
 */
function rapikan_pinjam(): void
{
    global $db;
    $r = $db->query('SELECT COUNT(*) AS n, COALESCE(MIN(id_pinjam), 1) AS mn, COALESCE(MAX(id_pinjam), 0) AS mx FROM peminjaman')->fetch();
    $n = (int) $r['n'];
    if ($n > 0 && ((int) $r['mn'] !== 1 || (int) $r['mx'] !== $n)) {
        $db->exec('SET @n = 0');
        // Urut naik: nomor baru selalu <= nomor lama, sehingga tidak bentrok.
        $db->exec('UPDATE peminjaman SET id_pinjam = (@n := @n + 1) ORDER BY id_pinjam');
    }
    $db->exec('ALTER TABLE peminjaman AUTO_INCREMENT = ' . ($n + 1));
}

/** Username otomatis dari nama lengkap (huruf kecil, titik sebagai pemisah, dijamin unik). */
function buat_username(string $nama): string
{
    global $db;
    $t = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nama) : $nama;
    $t = trim(preg_replace('/[^a-z0-9]+/', '.', strtolower($t)), '.');
    $t = rtrim(substr($t, 0, 24), '.');
    if (strlen($t) < 3) {
        $t = 'user' . $t;
    }
    $dasar = $t;
    $s = $db->prepare('SELECT 1 FROM users WHERE username = ?');
    for ($i = 2; ; $i++) {
        $s->execute([$t]);
        if (!$s->fetch()) {
            return $t;
        }
        $t = $dasar . $i;
    }
}

/** Nomor ID kosong terkecil pada tabel (untuk mengisi celah AUTO_INCREMENT). */
function id_kosong(string $tabel, string $kolom): int
{
    global $db;
    if (!in_array($tabel . '.' . $kolom, ['barang.id_barang', 'users.id_user', 'peminjaman.id_pinjam'], true)) {
        throw new InvalidArgumentException('Tabel tidak diizinkan.');
    }
    $min = $db->query("SELECT MIN($kolom) FROM $tabel")->fetchColumn();
    if ($min === null || $min === false || (int) $min > 1) {
        return 1;
    }
    $r = $db->query("SELECT MIN(t.$kolom + 1) FROM $tabel t LEFT JOIN $tabel n ON n.$kolom = t.$kolom + 1 WHERE n.$kolom IS NULL")->fetchColumn();
    return (int) $r;
}

/** INSERT dengan ID kosong terkecil (bukan selalu angka paling besar + 1). */
function sisip_id(string $tabel, string $kolom, array $data): int
{
    global $db;
    for ($coba = 0; $coba < 5; $coba++) {
        $id   = id_kosong($tabel, $kolom);
        $cols = array_merge([$kolom], array_keys($data));
        $sql  = 'INSERT INTO ' . $tabel . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        try {
            $db->prepare($sql)->execute(array_merge([$id], array_values($data)));
            return $id;
        } catch (PDOException $e) {
            // Bentrok ID (permintaan bersamaan): hitung ulang. Kesalahan lain dilempar kembali.
            if (!(($e->errorInfo[1] ?? 0) === 1062 && stripos($e->getMessage(), 'PRIMARY') !== false)) {
                throw $e;
            }
        }
    }
    throw new RuntimeException('Gagal mendapatkan nomor ID.');
}

/* ---------- Data ---------- */

/** Subquery: unit barang yang sedang dipinjam (disetujui, belum kembali). Tetap aset madrasah. */
const SQL_DIPINJAM = "COALESCE((SELECT SUM(p.jumlah) FROM peminjaman p WHERE p.id_barang = b.id_barang AND p.status = 'disetujui'), 0)";

/** Rekap per laboratorium (jumlah dalam unit). */
function rekap(): array
{
    global $db;
    return $db->query("
        SELECT l.nama_lab,
               COUNT(b.id_barang)                                           AS jenis,
               COALESCE(SUM(b.jumlah), 0)                                   AS stok,
               COALESCE(SUM(IF(b.kondisi = 'Baik', b.jumlah, 0)), 0)         AS baik,
               COALESCE(SUM(IF(b.kondisi = 'Rusak Ringan', b.jumlah, 0)), 0) AS ringan,
               COALESCE(SUM(IF(b.kondisi = 'Rusak Berat', b.jumlah, 0)), 0)  AS berat,
               COALESCE(SUM(" . SQL_DIPINJAM . "), 0)                        AS dipinjam
        FROM lab l
        LEFT JOIN barang b ON b.id_lab = l.id_lab
        GROUP BY l.id_lab, l.nama_lab
        ORDER BY l.id_lab")->fetchAll();
}

function jumlah_menunggu(): int
{
    global $db;
    return (int) $db->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'pending'")->fetchColumn();
}

/** Normalisasi nomor HP Indonesia ke format 62xxxxxxxxxx. Kosong bila tidak valid. */
function normal_hp(string $hp): string
{
    $d = preg_replace('/\D+/', '', $hp);
    if ($d === '') {
        return '';
    }
    if (str_starts_with($d, '0')) {
        $d = '62' . substr($d, 1);
    } elseif (str_starts_with($d, '8')) {
        $d = '62' . $d;
    }
    return preg_match('/^62\d{8,13}$/', $d) ? $d : '';
}

function wa_link(string $nomor, string $teks): string
{
    return 'https://wa.me/' . $nomor . '?text=' . rawurlencode($teks);
}

/** Pesan WhatsApp berformat untuk permintaan peminjaman ($p hasil join peminjaman + barang + lab). */
function pesan_pinjam(array $p): string
{
    return "Assalamu'alaikum Admin Laboratorium " . SEKOLAH . ".\n"
        . "Saya ingin mengajukan peminjaman barang laboratorium:\n\n"
        . 'No. Permintaan : #' . $p['id_pinjam'] . "\n"
        . 'Nama Peminjam : ' . $p['nama_peminjam'] . "\n"
        . 'No. WhatsApp  : ' . $p['no_hp'] . "\n"
        . 'Barang        : ' . $p['nama_barang'] . ' (' . $p['kode_barang'] . ")\n"
        . 'Laboratorium  : ' . $p['nama_lab'] . "\n"
        . 'Jumlah        : ' . $p['jumlah'] . ' ' . $p['satuan'] . "\n"
        . 'Waktu Pinjam  : ' . waktu_pinjam_teks($p) . "\n"
        . 'Batas Kembali : ' . (!empty($p['tgl_kembali_rencana']) ? tgl_id($p['tgl_kembali_rencana']) : '-') . "\n"
        . 'Keperluan     : ' . $p['keperluan'] . "\n"
        . (!empty($p['surat_file']) ? "Surat Resmi    : Sudah diunggah di sistem\n" : '')
        . "\n"
        . 'Mohon persetujuannya. Terima kasih.';
}

/**
 * Pesan WhatsApp admin -> peminjam untuk menindaklanjuti pengembalian barang.
 * Menyebut sejak kapan barang dipinjam dan batas pengembaliannya (bila ada).
 * $p = hasil join peminjaman + barang + lab.
 */
function pesan_tagih(array $p): string
{
    $hari = (int) (new DateTime($p['tgl_pinjam']))->diff(new DateTime(date('Y-m-d')))->format('%r%a');
    $lama = $hari <= 0 ? 'hari ini' : $hari . ' hari';

    return "Assalamu'alaikum Bapak/Ibu " . $p['nama_peminjam'] . ",\n"
        . 'Kami dari Admin Laboratorium ' . SEKOLAH . " ingin menanyakan barang yang sedang dipinjam:\n\n"
        . 'No. Peminjaman : #' . $p['id_pinjam'] . "\n"
        . 'Barang         : ' . $p['nama_barang'] . ' (' . $p['kode_barang'] . ")\n"
        . 'Laboratorium   : ' . $p['nama_lab'] . "\n"
        . 'Jumlah         : ' . $p['jumlah'] . ' ' . $p['satuan'] . "\n"
        . 'Tanggal Pinjam : ' . tgl_id($p['tgl_pinjam']) . ' (' . $lama . ")\n"
        . (!empty($p['tgl_kembali_rencana']) ? 'Batas Kembali : ' . tgl_id($p['tgl_kembali_rencana']) . (pinjam_terlambat($p) ? ' (sudah lewat ' . hari_terlambat($p) . ' hari)' : '') . "\n" : '')
        . "\n"
        . "Apabila barang sudah selesai digunakan, mohon dapat dikembalikan ke laboratorium.\n"
        . "Bila masih diperlukan, silakan balas pesan ini agar kami dapat mencatatnya.\n\n"
        . 'Terima kasih. Wassalamu\'alaikum.';
}

/** Teks waktu pinjam: "19 Oktober 2026, pukul 14.35 WIB" (data lama tanpa jam hanya menampilkan tanggal). */
function waktu_pinjam_teks(array $p): string
{
    if (!empty($p['waktu_pinjam'])) {
        return tgl_id($p['waktu_pinjam']) . ', pukul ' . date('H.i', strtotime($p['waktu_pinjam'])) . ' WIB';
    }
    return tgl_id($p['tgl_pinjam']);
}

/** Barang sedang dipinjam dan batas pengembalian (akhir hari tsb) sudah lewat. */
function pinjam_terlambat(array $p): bool
{
    return ($p['status'] ?? '') === 'disetujui'
        && !empty($p['tgl_kembali_rencana'])
        && $p['tgl_kembali_rencana'] < date('Y-m-d');
}

/** Jumlah hari melewati batas pengembalian (0 bila belum lewat). */
function hari_terlambat(array $p): int
{
    if (empty($p['tgl_kembali_rencana'])) {
        return 0;
    }
    return max(0, (int) (new DateTime($p['tgl_kembali_rencana']))->diff(new DateTime(date('Y-m-d')))->format('%r%a'));
}

/** Peminjaman milik pengguna yang login yang jatuh tempo hari ini atau sudah lewat batas pengembalian, dan belum dikembalikan. */
function pinjam_terlambat_saya(): array
{
    global $db;
    if (!masuk()) {
        return [];
    }
    $s = $db->prepare("SELECT p.id_pinjam, p.jumlah, p.tgl_kembali_rencana, b.nama_barang, b.kode_barang, b.satuan
        FROM peminjaman p JOIN barang b ON b.id_barang = p.id_barang
        WHERE p.id_user = ? AND p.status = 'disetujui' AND p.tgl_kembali_rencana IS NOT NULL AND p.tgl_kembali_rencana <= CURDATE()
        ORDER BY p.tgl_kembali_rencana ASC, p.id_pinjam ASC");
    $s->execute([user_id()]);
    return $s->fetchAll();
}

/** Ambil satu peminjaman lengkap dengan data barang & lab. */
function ambil_pinjam(int $id): ?array
{
    global $db;
    $s = $db->prepare('SELECT p.*, b.nama_barang, b.kode_barang, b.satuan, l.nama_lab
        FROM peminjaman p
        JOIN barang b ON b.id_barang = p.id_barang
        JOIN lab l ON l.id_lab = b.id_lab
        WHERE p.id_pinjam = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/* ---------- Pengunjung ---------- */

/** Pastikan tabel penghitung pengunjung ada (dibuat otomatis, tidak perlu import SQL). */
function pengunjung_siapkan(): void
{
    global $db;
    $db->exec('CREATE TABLE IF NOT EXISTS pengunjung (
        sid CHAR(40) NOT NULL,
        tanggal DATE NOT NULL,
        terakhir DATETIME NOT NULL,
        PRIMARY KEY (sid, tanggal),
        KEY idx_terakhir (terakhir)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

/** Catat kunjungan sesi ini (satu baris per sesi per hari) lalu kembalikan angka statistik. */
function pengunjung(bool $catat = true): array
{
    global $db;
    try {
        pengunjung_siapkan();
        $sekarang = date('Y-m-d H:i:s');
        if ($catat) {
            // ID sesi tidak disimpan mentah, hanya hash-nya.
            $db->prepare('INSERT INTO pengunjung (sid, tanggal, terakhir) VALUES (?, ?, ?)
                          ON DUPLICATE KEY UPDATE terakhir = VALUES(terakhir)')
               ->execute([sha1(session_id()), date('Y-m-d'), $sekarang]);
        }
        $s = $db->prepare('SELECT
                COALESCE(SUM(terakhir >= ?), 0)                          AS online,
                COALESCE(SUM(tanggal = ?), 0)                            AS hari,
                COALESCE(SUM(tanggal >= ?), 0)                           AS bulan,
                COUNT(*)                                                 AS total
            FROM pengunjung');
        $s->execute([date('Y-m-d H:i:s', time() - 300), date('Y-m-d'), date('Y-m-01')]);
        $r = array_map('intval', $s->fetch());
        $r['update'] = date('H:i');
        return $r;
    } catch (PDOException $e) {
        return ['online' => 0, 'hari' => 0, 'bulan' => 0, 'total' => 0, 'update' => date('H:i')];
    }
}

/* ---------- Tampilan ---------- */

/** Tebalkan (mark) kata kunci pada teks hasil pencarian. */
function hl($teks, string $cari): string
{
    $aman = e($teks);
    if ($cari === '') {
        return $aman;
    }
    return preg_replace('/(' . preg_quote(e($cari), '/') . ')/iu', '<mark>$1</mark>', $aman) ?? $aman;
}

/** Tautan halaman dengan parameter GET diganti/ditambah. */
function url_with(array $ganti): string
{
    return '?' . http_build_query(array_merge(array_diff_key($_GET, ['partial' => 1]), $ganti));
}

function pagination(int $page, int $pages): string
{
    if ($pages < 2) {
        return '';
    }
    $item = fn($p, $label, $cls = '') => '<a class="' . $cls . '" href="' . e(url_with(['page' => $p])) . '">' . $label . '</a>';

    $out = '<nav class="pager noprint" aria-label="Halaman">';
    $out .= $page > 1 ? $item($page - 1, '&laquo; Sebelumnya') : '';
    for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
        $out .= $item($i, $i, $i === $page ? 'on' : '');
    }
    $out .= $page < $pages ? $item($page + 1, 'Berikutnya &raquo;') : '';
    return $out . '</nav>';
}

/**
 * Salinan kata sandi terenkripsi (AES-256-GCM) agar Super Admin dapat melihat sandi pengguna.
 * Kolom `password` tetap berisi hash yang aman untuk login.
 */
function sandi_enc(string $plain): ?string
{
    if ($plain === '' || !function_exists('openssl_encrypt')) {
        return null;
    }
    $iv  = random_bytes(12);
    $tag = '';
    $c   = openssl_encrypt($plain, 'aes-256-gcm', hash('sha256', KUNCI_SANDI, true), OPENSSL_RAW_DATA, $iv, $tag);
    return $c === false ? null : base64_encode($iv . $tag . $c);
}

/** Kebalikan sandi_enc(). Null bila belum tersimpan atau tidak bisa dibaca. */
function sandi_dec(?string $enc): ?string
{
    if (!$enc || !function_exists('openssl_decrypt')) {
        return null;
    }
    $raw = base64_decode($enc, true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', KUNCI_SANDI, true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $p === false ? null : $p;
}

/** Ikon mata untuk tombol tampil/sembunyi kata sandi (sama dengan halaman login). */
function ikon_mata(): string
{
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 15c2.5-4.5 5.5-7 9-7s6.5 2.5 9 7"></path><circle cx="12" cy="15" r="2.8"></circle></svg>';
}

/** Kolom kata sandi lengkap dengan tombol mata. */
function input_sandi(string $id, string $name, string $extra = ''): string
{
    return '<div class="pw"><input id="' . $id . '" type="password" name="' . $name . '" ' . $extra . '>'
         . '<button type="button" class="pw-toggle" data-toggle-pw="' . $id . '" aria-label="Tampilkan password">' . ikon_mata() . '</button></div>';
}

/** Ikon SVG garis sederhana (mewarisi warna teks). */
function ikon(string $n): string
{
    $p = [
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'phone'     => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'pin'       => '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>',
        'youtube'   => '<rect x="2" y="5" width="20" height="14" rx="4"/><path d="M10 9l5 3-5 3z" fill="currentColor"/>',
        'facebook'  => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8z"/>',
        'tiktok'    => '<path d="M14 4v10a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 4c.5 2.5 2 4 5 4"/>',
        'whatsapp'  => '<path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9 9c0 3 3 6 6 6l1-1.5-2-1-1 .8c-1-.4-2-1.4-2.4-2.4l.8-1-1-2z"/>',
    ];
    return '<svg class="i" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$n] ?? '') . '</svg>';
}

function copyright(): string
{
    return '&copy; ' . date('Y') . ' ' . SEKOLAH . '. Dikembangkan oleh <b>' . PENGEMBANG[0] . '</b> &amp; <b>' . PENGEMBANG[1] . '</b>';
}