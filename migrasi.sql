-- =========================================================
-- Migrasi opsional untuk database `inventory_lab` (MariaDB 10.4+).
-- Aplikasi sudah menjalankan langkah yang sama secara otomatis saat dibuka,
-- jadi file ini hanya perlu diimpor bila ingin menyiapkan database secara manual.
-- Aman dijalankan berulang kali.
-- =========================================================

-- 1) Kode laboratorium (awalan kode barang)
ALTER TABLE `lab` ADD COLUMN IF NOT EXISTS `kode_lab` VARCHAR(5) NULL AFTER `nama_lab`;
UPDATE `lab` SET `kode_lab` = 'BHS' WHERE `id_lab` = 1 AND (`kode_lab` IS NULL OR `kode_lab` = '');
UPDATE `lab` SET `kode_lab` = 'KOM' WHERE `id_lab` = 2 AND (`kode_lab` IS NULL OR `kode_lab` = '');
UPDATE `lab` SET `kode_lab` = 'IPA' WHERE `id_lab` = 3 AND (`kode_lab` IS NULL OR `kode_lab` = '');
UPDATE `lab` SET `kode_lab` = 'POD' WHERE `id_lab` = 4 AND (`kode_lab` IS NULL OR `kode_lab` = '');

-- 2) Peran pengguna
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `role` ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER `nama`;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `no_hp` VARCHAR(20) NULL AFTER `role`;
UPDATE `users` SET `role` = 'admin' WHERE `username` = 'admin';

-- 2b) Tiga peran: superadmin (admin utama, akses semua), admin (semua kecuali menghapus), user
ALTER TABLE `users` MODIFY `role` ENUM('superadmin','admin','user') NOT NULL DEFAULT 'user';
UPDATE `users` SET `role` = 'superadmin' WHERE `username` = 'admin';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `organisasi` VARCHAR(100) NULL AFTER `username`;

-- 3) Peminjaman
CREATE TABLE IF NOT EXISTS `peminjaman` (
  `id_pinjam` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_barang` INT UNSIGNED NOT NULL,
  `id_user` INT UNSIGNED NULL,
  `nama_peminjam` VARCHAR(100) NOT NULL,
  `no_hp` VARCHAR(20) NOT NULL,
  `jumlah` INT UNSIGNED NOT NULL DEFAULT 1,
  `keperluan` VARCHAR(300) NOT NULL,
  `tgl_pinjam` DATE NOT NULL,
  `tgl_kembali_rencana` DATE NOT NULL,
  `tgl_kembali` DATE NULL,
  `status` ENUM('pending','disetujui','dikembalikan','ditolak','dibatalkan') NOT NULL DEFAULT 'pending',
  `diproses_oleh` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_pinjam_barang` (`id_barang`),
  KEY `idx_pinjam_user` (`id_user`),
  KEY `idx_pinjam_status` (`status`),
  CONSTRAINT `fk_pinjam_barang` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id_barang`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pinjam_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;