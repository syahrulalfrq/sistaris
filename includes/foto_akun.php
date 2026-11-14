<?php
/**
 * Foto profil akun (admin, super admin, dan pengguna biasa).
 * Kolom `users.foto` dibuat otomatis, tidak perlu import SQL.
 * Foto disimpan di uploads/profil, dipotong persegi, dikecilkan (maks. 320 px), dan disimpan sebagai JPG bila GD tersedia.
 * Dimuat oleh includes/header.php dan profil.php (setelah includes/bootstrap.php).
 */

const DIR_FOTO_AKUN = __DIR__ . '/../uploads/profil';
const URL_FOTO_AKUN = 'uploads/profil/';
const MAKS_FOTO_AKUN_MB = 3;

/** Tambahkan kolom foto bila belum ada (sekali per sesi). */
function akun_foto_siapkan(): void
{
    global $db;
    if (!empty($_SESSION['foto_akun_siap'])) {
        return;
    }
    if (!kolom_ada('users', 'foto')) {
        $db->exec('ALTER TABLE users ADD COLUMN foto VARCHAR(80) NULL');
    }
    $_SESSION['foto_akun_siap'] = 1;
}

/** Nama berkas foto akun yang sedang login (null bila belum ada). Hanya satu query per halaman. */
function akun_foto_nama(bool $segarkan = false): ?string
{
    global $db;
    static $cache = false;
    if ($cache !== false && !$segarkan) {
        return $cache;
    }
    akun_foto_siapkan();
    $s = $db->prepare('SELECT foto FROM users WHERE id_user = ?');
    $s->execute([user_id()]);
    $f = $s->fetchColumn();
    return $cache = ($f !== false && $f !== '' ? (string) $f : null);
}

/** Alamat foto bila berkas ada dan namanya valid; string kosong bila tidak. */
function akun_foto_url(?string $foto): string
{
    if ($foto && preg_match('/^u[0-9]+-[a-f0-9]{12}\.(jpg|png|webp)$/', $foto) && is_file(DIR_FOTO_AKUN . '/' . $foto)) {
        return URL_FOTO_AKUN . $foto;
    }
    return '';
}

/** Lingkaran avatar: foto bila ada, huruf pertama nama bila belum. $kelas: '', 'big', atau 'xl'. */
function akun_avatar(string $nama, ?string $foto, string $kelas = ''): string
{
    $url = akun_foto_url($foto);
    $isi = $url !== ''
        ? '<img src="' . e($url) . '" alt="" draggable="false">'
        : e(mb_strtoupper(mb_substr($nama, 0, 1)));
    return '<span class="avatar' . ($kelas !== '' ? ' ' . e($kelas) : '') . ($url !== '' ? ' ada-foto' : '') . '" aria-hidden="true">' . $isi . '</span>';
}

/**
 * Simpan foto dari $_FILES['foto'].
 * Mengembalikan nama berkas, null bila tidak ada berkas, atau false bila gagal ($galat berisi alasannya).
 */
function akun_simpan_foto(?array $f, int $uid, ?string &$galat = null)
{
    $galat = null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > MAKS_FOTO_AKUN_MB * 1048576) {
        $galat = 'Ukuran foto maksimal ' . MAKS_FOTO_AKUN_MB . ' MB.';
        return false;
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $galat = 'Foto gagal diunggah, silakan coba lagi.';
        return false;
    }

    $info  = @getimagesize($f['tmp_name']);
    $tipe  = $info[2] ?? 0;
    $boleh = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
    $buat  = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng'];
    if (defined('IMAGETYPE_WEBP')) {
        $boleh[IMAGETYPE_WEBP] = 'webp';
        $buat[IMAGETYPE_WEBP]  = 'imagecreatefromwebp';
    }
    if (!$info || !isset($boleh[$tipe])) {
        $galat = 'Format foto harus JPG, PNG, atau WebP.';
        return false;
    }
    if ($info[0] > 6000 || $info[1] > 6000) {
        $galat = 'Dimensi foto terlalu besar (maksimal 6000 piksel).';
        return false;
    }

    if (!is_dir(DIR_FOTO_AKUN) && !@mkdir(DIR_FOTO_AKUN, 0775, true)) {
        $galat = 'Folder penyimpanan foto tidak dapat dibuat.';
        return false;
    }
    // Berkas skrip tidak boleh dijalankan dan isi folder tidak ditampilkan.
    if (!is_file(DIR_FOTO_AKUN . '/.htaccess')) {
        @file_put_contents(DIR_FOTO_AKUN . '/.htaccess', "<FilesMatch \"\\.(php|phtml|phar|php[0-9])\$\">\nRequire all denied\n</FilesMatch>\n");
    }
    if (!is_file(DIR_FOTO_AKUN . '/index.html')) {
        @file_put_contents(DIR_FOTO_AKUN . '/index.html', '');
    }

    $nama   = 'u' . $uid . '-' . bin2hex(random_bytes(6));
    $tujuan = DIR_FOTO_AKUN . '/' . $nama;

    $fn = $buat[$tipe] ?? null;
    if ($fn && function_exists($fn) && function_exists('imagecreatetruecolor') && function_exists('imagejpeg')) {
        $src = @$fn($f['tmp_name']);
        if ($src) {
            // Putar sesuai orientasi kamera (foto dari HP sering tersimpan miring).
            if ($tipe === IMAGETYPE_JPEG && function_exists('exif_read_data') && function_exists('imagerotate')) {
                $ex    = @exif_read_data($f['tmp_name']);
                $sudut = [3 => 180, 6 => -90, 8 => 90][$ex['Orientation'] ?? 1] ?? 0;
                if ($sudut) {
                    $r = @imagerotate($src, $sudut, 0);
                    if ($r) {
                        imagedestroy($src);
                        $src = $r;
                    }
                }
            }
            $w = imagesx($src);
            $h = imagesy($src);
            $sisi = min($w, $h);
            $ox = intdiv($w - $sisi, 2);
            $oy = (int) (($h - $sisi) * 0.2); // foto potret: ambil dari bagian atas agar wajah tidak terpotong
            $ukuran = min($sisi, 320);
            $dst = imagecreatetruecolor($ukuran, $ukuran);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, $ox, $oy, $ukuran, $ukuran, $sisi, $sisi);
            $ok = imagejpeg($dst, $tujuan . '.jpg', 85);
            imagedestroy($src);
            imagedestroy($dst);
            if ($ok) {
                return $nama . '.jpg';
            }
        }
    }

    // Tanpa GD: simpan apa adanya (format sudah divalidasi di atas).
    if (!move_uploaded_file($f['tmp_name'], $tujuan . '.' . $boleh[$tipe])) {
        $galat = 'Foto gagal disimpan di server.';
        return false;
    }
    return $nama . '.' . $boleh[$tipe];
}

function akun_hapus_foto(?string $foto): void
{
    if ($foto && preg_match('/^u[0-9]+-[a-f0-9]{12}\.(jpg|png|webp)$/', $foto) && is_file(DIR_FOTO_AKUN . '/' . $foto)) {
        @unlink(DIR_FOTO_AKUN . '/' . $foto);
    }
}