# Sistem Informasi Inventaris Laboratorium - MTs Negeri 1 Klaten

Letakkan folder `inventory_lab` di `htdocs` (XAMPP), lalu buka `http://localhost/inventory_lab/`.
Database dan akun admin lama tetap dipakai (pengaturan di `includes/config.php`).
Tabel/kolom baru dibuat **otomatis** saat aplikasi pertama kali dibuka (migrasi). `migrasi.sql` hanya opsional.

## Peran
| Peran | Akses |
|-------|-------|
| Admin | Semua: dashboard, tambah/ubah/hapus barang, peminjaman (setujui/tolak/kembali), pengguna, laporan |
| Pengguna | Melihat data barang, mengajukan pinjam (status *menunggu* sampai disetujui admin), profil |

Akun pengguna **dibuat oleh admin** di menu *Pengguna*. Akun admin lama otomatis tetap admin.

## Struktur halaman
| File | Fungsi |
|------|--------|
| `index.php` | Beranda publik |
| `login.php` / `logout.php` | Masuk / keluar |
| `dashboard.php` | Dashboard admin (aset, dipinjam, menunggu persetujuan) |
| `barang.php` | Data barang + pencarian langsung (+ tombol Pinjam untuk pengguna) |
| `form.php`, `hapus.php`, `kode_baru.php` | Tambah/ubah/hapus barang, kode otomatis per laboratorium |
| `pinjam.php`, `pinjam_wa.php` | Form peminjaman dan pengalihan ke WhatsApp admin |
| `peminjaman.php`, `peminjaman_aksi.php` | Daftar & proses peminjaman |
| `pengguna.php`, `profil.php` | Manajemen akun (admin) dan profil |
| `laporan.php` | Laporan siap cetak A4 |

## Aturan penting
- **Kode barang otomatis**: awalan dari laboratorium (BHS, KOM, IPA, POD). Nomor kosong terkecil dipakai lebih dulu.
- **Kode barang dirapikan saat dihapus**: bila barang dihapus (mis. KOM-005), kode sesudahnya naik mengisi tempat kosong (KOM-006 menjadi KOM-005, dst.). Berlaku juga saat barang dipindah ke laboratorium lain.
- **Akun pengguna**: admin mengisi *Komunitas/Organisasi* lebih dulu, lalu *Nama Lengkap*. Username untuk masuk dibuat otomatis dari nama lengkap dan ditampilkan setelah akun dibuat.
- **ID kosong terisi ulang**: data baru memakai nomor ID kosong terkecil (barang dan pengguna), bukan selalu nomor terakhir + 1.
- **Dipinjam tetap aset**: kolom *Jumlah* adalah total aset; unit yang dipinjam hanya ditampilkan sebagai keterangan.
- **Nomor WhatsApp admin**: `WA_ADMIN` di `includes/config.php` (saat ini 6285875041080).
- **Media sosial & tautan footer**: ubah `SOSMED` dan `LINK_WEB` di `includes/config.php`.
- Cetak laporan: matikan *Headers and footers* di jendela cetak browser.
