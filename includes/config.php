<?php
/**
 * Konfigurasi aplikasi.
 * Ubah nilai di bawah ini sesuai server yang digunakan.
 */

// Database
const DB_HOST = 'sql305.infinityfree.com'; // Sesuaikan host MySQL dari cPanel/Control Panel InfinityFree
const DB_NAME = 'if0_43090480_inventory_lab'; // Nama database di InfinityFree
const DB_USER = 'if0_43090480'; // Username akun hosting
const DB_PASS = 'mtsn1klaten2024';

// Kunci enkripsi salinan kata sandi yang dapat dilihat Super Admin. JANGAN dibagikan dan jangan diubah
// setelah dipakai (kata sandi yang sudah tersimpan tidak akan terbaca lagi).
const KUNCI_SANDI = '4f3a67b245dfa699d12c25b74abd22bd9037528544b561256aa2fd879aaeede0';

// Identitas instansi
const SEKOLAH   = 'MTs Negeri 1 Klaten';
const NAMA_LONG = 'Madrasah Tsanawiyah Negeri 1 Klaten';
const DAERAH    = 'Kabupaten Klaten, Provinsi Jawa Tengah';
// Teks lencana di beranda (di atas judul)
const CHIP_UTAMA = 'Kementerian Agama Republik Indonesia';
const CHIP_UNIT  = 'Kementerian Agama Kabupaten Klaten';
// Kop surat pada dokumen cetak (laporan inventaris & data pengguna)
const KOP_BARIS  = ['KEMENTERIAN AGAMA REPUBLIK INDONESIA', 'KANTOR KEMENTERIAN AGAMA KAB. KLATEN', 'MADRASAH TSANAWIYAH NEGERI 1 KLATEN'];
const KOP_ALAMAT = 'Jl. Ki Ageng Gribig, Gergunung, Telp. (0272) 322780, Klaten Utara. Kode Pos 57434';
const KOP_WEB    = 'mtsn1klaten.sch.id';
const MOTTO     = 'Mulia, Mandiri, Berprestasi, Unggul dan Melayani';
const APLIKASI  = 'Sistem Informasi Inventaris Laboratorium';

// Zona waktu (dipakai untuk tanggal, laporan, dan penghitung pengunjung)
date_default_timezone_set('Asia/Jakarta');

// Kontak & tautan
const WEB_SEKOLAH = 'https://mtsn1klaten.sch.id/';
const WA_ADMIN    = '6285875041080';          // Nomor WhatsApp admin (format internasional, tanpa + / spasi / 0 di depan)
const TELP        = '(0272) 322780';          // Tampilan nomor telepon sebagai teks biasa di footer
// Jam layanan laboratorium (tampil di footer). SESUAIKAN dengan jam pelayanan yang sebenarnya.
const JAM_LAYANAN = [
    ['Senin - Kamis',  '07.00 - 15.00'],
    ['Jumat',          '07.00 - 11.00'],
    ['Sabtu - Minggu', 'Tutup'],
];
const MAPS_QUERY  = 'MTs Negeri 1 Klaten, Jalan Ki Ageng Gribig, Klaten Utara, Klaten';

// Media sosial resmi. GANTI tautan di bawah dengan alamat akun resmi yang benar.
// Instagram memakai akun yang Anda berikan; YouTube/Facebook/TikTok sementara berupa tautan pencarian.
const SOSMED = [
    ['instagram', 'Instagram', '@mtsnegeri1klaten.id', 'https://www.instagram.com/mtsnegeri1klaten.id/'],
    ['youtube',   'YouTube',   'MTs Negeri 1 Klaten Official', 'https://www.youtube.com/@mtsnegeri1klaten'],
    ['facebook',  'Facebook',  'MTsN 1 Klaten',             'https://www.facebook.com/groups/1453386671596003/?locale=id_ID'],
    ['tiktok',    'TikTok',    'MTsN 1 Klaten',             'https://www.tiktok.com/@mtsnegeri1klaten'],
];

// Tautan tambahan di footer
const LINK_WEB = [
    ['Website MTsN 1 Klaten', 'https://mtsn1klaten.sch.id/'],
    ['Kementerian Agama RI',  'https://kemenag.go.id/'],
    ['Kemenag Kab. Klaten',   'https://klaten.kemenag.go.id/'],
    ['Simpatika Kemenag',     'https://simpatika.kemenag.go.id/'],
    ['EMIS Kemenag',          'https://emis.kemenag.go.id/'],
];
const EMAIL       = 'mtsn1klaten@gmail.com';
const ALAMAT      = 'Jalan Ki Ageng Gribig, Kelurahan Gergunung, Kecamatan Klaten Utara. Kode Pos 57434';
const TENTANG     = 'MTs Negeri 1 Klaten adalah mad&shy;ra&shy;sah yang me&shy;na&shy;war&shy;kan pen&shy;di&shy;dik&shy;an yang me&shy;nge&shy;de&shy;pan&shy;kan akhlak dan agama, tanpa me&shy;nge&shy;sam&shy;ping&shy;kan ke&shy;mam&shy;pu&shy;an aka&shy;de&shy;mik dan ke&shy;ma&shy;ju&shy;an tek&shy;no&shy;lo&shy;gi. Ter&shy;le&shy;tak di ling&shy;kung&shy;an kota yang ramah dan ramai, di jalan Ki Ageng Gribig dekat dengan hutan kota Ger&shy;gu&shy;nung, dan ber&shy;ja&shy;rak be&shy;be&shy;ra&shy;pa ki&shy;lo&shy;me&shy;ter dari jalan utama Solo-Klaten-Jogja. Men&shy;ja&shy;di&shy;kan kami se&shy;ba&shy;gai se&shy;ko&shy;lah yang stra&shy;te&shy;gis, di&shy;ma&shy;na akan ber&shy;pe&shy;nga&shy;ruh lang&shy;sung pada fa&shy;si&shy;li&shy;tas yang di&shy;be&shy;ri&shy;kan.';

// Pengembang
const PENGEMBANG = ['SIJA SMKN 2 Klaten', 'PPLG SMK Muhammadiyah 2 Klaten Utara'];

// Aplikasi
const PER_HALAMAN = 15;
const KONDISI     = ['Baik' => 'ok', 'Rusak Ringan' => 'warn', 'Rusak Berat' => 'bad'];

// Status peminjaman => [label, kelas badge]
const STATUS_PINJAM = [
    'pending'     => ['Menunggu Persetujuan', 'warn'],
    'disetujui'   => ['Dipinjam',             'info'],
    'dikembalikan'=> ['Dikembalikan',         'ok'],
    'ditolak'     => ['Ditolak',              'bad'],
    'dibatalkan'  => ['Dibatalkan',           'mute'],
];