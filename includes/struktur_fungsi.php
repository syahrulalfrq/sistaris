<?php
/**
 * Struktur Lab: tabel, data awal, tampilan kartu, dan pengelolaan foto.
 * Dimuat oleh index.php (tampil publik) dan struktur_kelola.php (kelola Super Admin).
 * Wajib dimuat SETELAH includes/bootstrap.php (file: includes/struktur_fungsi.php).
 *
 * Tabel `struktur_lab` dibuat otomatis, tidak perlu import SQL.
 */

const DIR_STRUKTUR = __DIR__ . '/../uploads/struktur';
const URL_STRUKTUR = 'uploads/struktur/';
const MAKS_FOTO_MB = 3;

/**
 * Jabatan baku, urut dari yang paling tinggi.
 * kode => [jabatan, kode atasan, urutan, tingkat]
 */
const STRUKTUR_BAKU = [
    'kamad'             => ['Kepala Madrasah',               null,           1, 1],
    'waka_sarpras'      => ['Waka Bidang Sarpras',           'kamad',        2, 2],
    'kalab'             => ['Kepala Laboratorium Madrasah',  'kamad',        3, 2],
    'koordinator_media' => ['Koordinator Media dan Podcast', 'waka_sarpras', 4, 3],
    'laboran_ipa'       => ['Laboran IPA',                   'kalab',        5, 3],
    'laboran_komputer'  => ['Laboran Komputer',              'kalab',        6, 3],
    'laboran_bahasa'    => ['Laboran Bahasa',                'kalab',        7, 3],
    'laboran_agama'     => ['Laboran Agama',                 'kalab',        8, 3],
];

/** Buat tabel + isi jabatan baku bila belum ada (aman dijalankan berulang kali). */
function struktur_siapkan(): void
{
    global $db;
    if (!empty($_SESSION['struktur_siap'])) {
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS struktur_lab (
        kode VARCHAR(30) NOT NULL PRIMARY KEY,
        jabatan VARCHAR(100) NOT NULL,
        nama VARCHAR(100) NULL,
        foto VARCHAR(80) NULL,
        urutan TINYINT UNSIGNED NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ada = $db->query('SELECT kode FROM struktur_lab')->fetchAll(PDO::FETCH_COLUMN);
    $ins = $db->prepare('INSERT INTO struktur_lab (kode, jabatan, urutan) VALUES (?, ?, ?)');
    foreach (STRUKTUR_BAKU as $kode => $b) {
        if (!in_array($kode, $ada, true)) {
            $ins->execute([$kode, $b[0], $b[2]]);
        }
    }
    $_SESSION['struktur_siap'] = 1;
}

/** Semua pengurus [kode => baris], urut dari jabatan tertinggi. */
function struktur_ambil(): array
{
    global $db;
    struktur_siapkan();
    $out = [];
    foreach ($db->query('SELECT kode, jabatan, nama, foto, urutan FROM struktur_lab ORDER BY urutan, kode')->fetchAll() as $r) {
        $out[$r['kode']] = $r;
    }
    return $out;
}

/** Alamat foto bila berkas ada dan namanya valid; string kosong bila tidak. */
function struktur_foto_url(?string $foto): string
{
    if ($foto && preg_match('/^[a-z_]+-[a-f0-9]{12}\.(jpg|png|webp)$/', $foto) && is_file(DIR_STRUKTUR . '/' . $foto)) {
        return URL_STRUKTUR . $foto;
    }
    return '';
}

/** Siluet orang (dipakai bila foto belum diunggah). */
function struktur_siluet(): string
{
    return '<svg viewBox="0 0 96 96" aria-hidden="true"><circle cx="48" cy="36" r="17"/><path d="M13 96c2-21 16-32 35-32s33 11 35 32z"/></svg>';
}

/** Foto bundar (atau siluet) untuk satu pengurus. */
function struktur_avatar(array $r, int $px = 96): string
{
    $url = struktur_foto_url($r['foto'] ?? '');
    if ($url === '') {
        return struktur_siluet();
    }
    $alt = trim((string) ($r['nama'] ?? '')) !== '' ? $r['nama'] : $r['jabatan'];
    return '<img src="' . e($url) . '" alt="Foto ' . e($alt) . '" width="' . $px . '" height="' . $px . '" loading="lazy" draggable="false">';
}

/**
 * Nama + gelar untuk ditampilkan: gelar (mis. ", S.Pd.") dilekatkan ke kata nama terakhir
 * dengan white-space: nowrap, sehingga gelar tidak pernah turun sendirian ke baris bawah.
 * Gelar depan (Drs., Dr., H., dst.) juga dilekatkan ke kata sesudahnya.
 */
function struktur_nama_html(string $nama): string
{
    $kata = preg_split('/\s+/u', trim($nama), -1, PREG_SPLIT_NO_EMPTY);
    $n = count($kata);
    if ($n === 0) {
        return '';
    }
    // Gelar belakang dimulai pada kata pertama yang mengandung koma.
    $idxKoma = null;
    foreach ($kata as $i => $k) {
        if ($i > 0 && strpos($k, ',') !== false) {
            $idxKoma = $i;
            break;
        }
    }
    $mulaiBelakang = $idxKoma !== null ? $idxKoma : $n - 1;
    // Bila kata berkoma hanya inisial (mis. "L,."), kata sebelumnya ikut dilekatkan.
    if ($idxKoma !== null && $idxKoma > 1 && mb_strlen(preg_replace('/[^\p{L}]/u', '', $kata[$idxKoma])) <= 1) {
        $mulaiBelakang = $idxKoma - 1;
    }
    // Kata koma itu sendiri sudah memuat nama terakhir (mis. "Kholid,"), jadi grup belakang mulai di sana.
    $grup = [];
    $i = 0;
    while ($i < $n) {
        if ($idxKoma !== null && ($i === $mulaiBelakang || ($i + 1 === $mulaiBelakang && preg_match('/^(Drs|Dra|Dr|Prof|Ir|H|Hj|KH)\.?$/iu', $kata[$i])))) {
            $grup[] = implode(' ', array_slice($kata, $i));
            break;
        }
        // Gelar depan melekat ke kata berikutnya.
        if ($i < $n - 1 && preg_match('/^(Drs|Dra|Dr|Prof|Ir|H|Hj|KH)\.?$/iu', $kata[$i])) {
            $grup[] = $kata[$i] . ' ' . $kata[$i + 1];
            $i += 2;
            continue;
        }
        $grup[] = $kata[$i];
        $i++;
    }
    $html = [];
    foreach ($grup as $g) {
        $html[] = '<span class="nm-satu">' . e($g) . '</span>';
    }
    return implode(' ', $html);
}

/** Kartu pengurus untuk bagan di beranda. $lvl: lvl1 | lvl2 | lvl3. */
function struktur_kartu(array $s, string $kode, string $lvl): string
{
    $r = $s[$kode] ?? ['kode' => $kode, 'jabatan' => STRUKTUR_BAKU[$kode][0], 'nama' => '', 'foto' => ''];
    $nama = trim((string) ($r['nama'] ?? ''));
    return '<div class="org-card ' . $lvl . '">'
        . '<span class="org-foto">' . struktur_avatar($r) . '</span>'
        . '<span class="org-teks">'
        .   '<span class="org-jabatan">' . e($r['jabatan']) . '</span>'
        .   '<b class="org-nama' . ($nama === '' ? ' kosong' : '') . '">' . ($nama !== '' ? struktur_nama_html($nama) : '&mdash;') . '</b>'
        . '</span>'
        . '</div>';
}

/**
 * Simpan foto dari $_FILES['foto'] (opsional).
 * Mengembalikan nama berkas, null bila tidak ada berkas, atau false bila gagal ($galat berisi alasannya).
 * Bila ekstensi GD tersedia, foto dipotong persegi (wajah di bagian atas dipertahankan), dikecilkan
 * maksimal 480 px, dan disimpan sebagai JPG supaya beranda tetap ringan.
 */
function struktur_simpan_foto(?array $f, string $kode, ?string &$galat = null)
{
    $galat = null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > MAKS_FOTO_MB * 1048576) {
        $galat = 'Ukuran foto maksimal ' . MAKS_FOTO_MB . ' MB.';
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

    if (!is_dir(DIR_STRUKTUR) && !@mkdir(DIR_STRUKTUR, 0775, true)) {
        $galat = 'Folder penyimpanan foto tidak dapat dibuat.';
        return false;
    }
    // Foto boleh dilihat publik, tetapi berkas skrip tidak boleh dijalankan dan isi folder tidak ditampilkan.
    if (!is_file(DIR_STRUKTUR . '/.htaccess')) {
        @file_put_contents(DIR_STRUKTUR . '/.htaccess', "<FilesMatch \"\\.(php|phtml|phar|php[0-9])\$\">\nRequire all denied\n</FilesMatch>\n");
    }
    if (!is_file(DIR_STRUKTUR . '/index.html')) {
        @file_put_contents(DIR_STRUKTUR . '/index.html', '');
    }

    $nama   = $kode . '-' . bin2hex(random_bytes(6));
    $tujuan = DIR_STRUKTUR . '/' . $nama;

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
            $ukuran = min($sisi, 480);
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
    $ext = $boleh[$tipe];
    if (!move_uploaded_file($f['tmp_name'], $tujuan . '.' . $ext)) {
        $galat = 'Foto gagal disimpan di server.';
        return false;
    }
    return $nama . '.' . $ext;
}

function struktur_hapus_foto(?string $foto): void
{
    if ($foto && preg_match('/^[a-z_]+-[a-f0-9]{12}\.(jpg|png|webp)$/', $foto) && is_file(DIR_STRUKTUR . '/' . $foto)) {
        @unlink(DIR_STRUKTUR . '/' . $foto);
    }
}