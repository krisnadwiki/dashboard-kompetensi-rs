-- ============================================================
-- Kompetensi RS Online - Database Initialization
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(120)    NOT NULL,
  `username`     VARCHAR(60)     NOT NULL UNIQUE,
  `email`        VARCHAR(120)    NOT NULL UNIQUE,
  `password`     VARCHAR(255)    NOT NULL,
  `role`         ENUM('admin','operator') NOT NULL DEFAULT 'operator',
  `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
  `last_login`   DATETIME        NULL,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hospital profile (master)
CREATE TABLE IF NOT EXISTS `rs_profile` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_rs`          VARCHAR(200) NOT NULL DEFAULT 'RSUD Kilisuci',
  `kode_rs`          VARCHAR(30)  NULL COMMENT 'Kode RS dari Kemenkes',
  `jenis_rs`         VARCHAR(60)  NULL,
  `kelas_rs`         ENUM('A','B','C','D','D Pratama') NULL DEFAULT 'C',
  `kepemilikan`      VARCHAR(100) NULL,
  `direktur`         VARCHAR(150) NULL,
  `alamat`           TEXT         NULL,
  `kota_kabupaten`   VARCHAR(100) NULL,
  `provinsi`         VARCHAR(80)  NULL,
  `kode_pos`         VARCHAR(10)  NULL,
  `no_telepon`       VARCHAR(30)  NULL,
  `no_fax`           VARCHAR(30)  NULL,
  `email_rs`         VARCHAR(120) NULL,
  `website`          VARCHAR(200) NULL,
  `no_izin`          VARCHAR(150) NULL COMMENT 'Nomor izin operasional',
  `tgl_izin`         DATE         NULL COMMENT 'Tanggal berlaku izin',
  `rsonline_url`     VARCHAR(300) NOT NULL DEFAULT 'https://sirs.kemkes.go.id/fo/dashboard' COMMENT 'URL deeplink RS Online',
  `logo_path`        VARCHAR(255) NULL,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`       INT UNSIGNED NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sync log: catat setiap aksi sinkronisasi ke RS Online
CREATE TABLE IF NOT EXISTS `sync_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `action`      VARCHAR(100) NOT NULL COMMENT 'e.g. open_rsonline, manual_note, sirs_paste',
  `notes`       TEXT         NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sync_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snapshots data per periode (tarikan data RS Online)
CREATE TABLE IF NOT EXISTS `snapshots` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150) NOT NULL,
  `periode`     VARCHAR(50)  NULL,
  `description` TEXT         NULL,
  `source_type` VARCHAR(50)  NOT NULL DEFAULT 'sirs_copy',
  `total_items` INT UNSIGNED NOT NULL DEFAULT 0,
  `data_json`   LONGTEXT     NOT NULL,
  `created_by`  INT UNSIGNED NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_snap_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- App settings (key-value)
CREATE TABLE IF NOT EXISTS `settings` (
  `key_name`    VARCHAR(80)  NOT NULL,
  `value`       TEXT         NULL,
  `description` VARCHAR(255) NULL,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Seed Data ────────────────────────────────────────────────────────────────

-- Default admin user: admin / Admin@2026!
INSERT INTO `users` (`name`, `username`, `email`, `password`, `role`) VALUES
('Administrator', 'admin', 'admin@rskilisuci.id', '$2y$12$UPttvqlPWp.VwGN2fTM9NeDpB3UgGmtpbOEd4OKth4fR.3h5yI81S', 'admin'),
('Operator RS', 'operator', 'operator@rskilisuci.id', '$2y$12$UPttvqlPWp.VwGN2fTM9NeDpB3UgGmtpbOEd4OKth4fR.3h5yI81S', 'operator');
-- password hash for: Admin@2026!

-- Default RS profile
INSERT INTO `rs_profile` (`id`, `nama_rs`, `kode_rs`, `jenis_rs`, `kelas_rs`, `kepemilikan`, `direktur`,
  `alamat`, `kota_kabupaten`, `provinsi`, `no_telepon`, `rsonline_url`) VALUES
(1, 'RSUD Kilisuci', NULL, 'Rumah Sakit Umum', 'C', 'Pemkot',
 'dr. QOIRIDA VINAHARI, Sp.PK',
 'Jl. KH. Wachid Hasyim No. 64, Kel. Bandar Lor, Kec. Mojoroto',
 'Kota Kediri', 'Jawa Timur', '(0354) 6021640',
 'https://sirs.kemkes.go.id/fo/dashboard');

-- Default settings
INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES
('app_name',    'Kompetensi RS Online',   'Nama aplikasi'),
('app_version', '2.0.0',                  'Versi aplikasi'),
('theme',       'light',                  'Tema default (light/dark)');
