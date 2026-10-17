# 🏥 Sistem Informasi Kompetensi RS & Integrasi SIRS Online

Aplikasi web terpadu untuk monitoring kompetensi rumah sakit, pengelolaan data master fasilitas kesehatan, integrasi deeplink ke **SIRS Online (RS Online Kemenkes)**, serta sistem autentikasi multi-peran berbasis **PHP 8.3 + MySQL 8.4 + Nginx**.

---

## 🚀 Fitur Utama

1. **Sistem Login & Multi-Peran**:
   - Role **Administrator** (Akses penuh ke Master Data & Pengaturan).
   - Role **Operator** (Akses Dashboard, Data Kompetensi, dan Sinkronisasi RS Online).
   - Dilengkapi proteksi CSRF, Session Fixation prevention, dan hashing password Bcrypt.
2. **Pemisahan Master Data & Dashboard**:
   - **Dashboard**: Statistik ringkas, profil faskes, aksi cepat, dan riwayat sinkronisasi.
   - **Data Kompetensi**: Modul analisis interaktif kompetensi dan instrumen SIRS (filter, pivot, ekspor Excel).
   - **Master Profil RS**: Pengelolaan nama RS, kode Kemenkes, perizinan, kontak, direktur, dan URL target RS Online.
   - **Master Pengguna**: Tambah, ubah, nonaktifkan, dan hapus akun pengguna.
   - **Sinkronisasi RS Online**: Deeplink verifikasi login aktif, inspeksi network response dokumen SIRS, serta pencatatan audit sync log.
3. **Navigasi Responsif & Mobile View**:
   - Sidebar navigasi modern dengan toggle kolaps (desktop) dan drawer overlay (mobile).
   - Navbar dengan breadcrumb interaktif, toggle tema (**Terang / Gelap**), dan dropdown profil pengguna dengan tombol **Keluar (Logout)**.
4. **Deeplink & Sinkronisasi SIRS Kemenkes**:
   - Deeplink target URL ke portal RS Online SIRS (`https://sirs.kemkes.go.id/fo/dashboard`).
   - Logging otomatis setiap kali operator atau admin memicu sinkronisasi.

---

## 🔑 Akun Bawaan (Default Login)

| Role | Username | Password | Email |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `Admin@2026!` | `admin@rskilisuci.id` |
| **Operator** | `operator` | `Admin@2026!` | `operator@rskilisuci.id` |

---

## 🐳 Menjalankan dengan Docker Compose

### 1. Jalankan Container
Pastikan Docker Desktop aktif di komputer Anda, lalu jalankan:

```bash
docker compose up -d --build
```

Container yang dijalankan:
- `kompetensi_nginx` (Port: `8088` -> `http://localhost:8088`)
- `kompetensi_app` (PHP 8.3-FPM Alpine)
- `kompetensi_db` (MySQL 8.4 - Port internal `3306`, host port `3309`)

### 2. Buka Aplikasi di Browser
Akses URL berikut:
- **Halaman Login**: [http://localhost:8088/login](http://localhost:8088/login)
- **Dashboard**: [http://localhost:8088/dashboard](http://localhost:8088/dashboard)
- **Data Kompetensi**: [http://localhost:8088/dashboard/kompetensi](http://localhost:8088/dashboard/kompetensi)
- **Master Profil RS**: [http://localhost:8088/master/profil-rs](http://localhost:8088/master/profil-rs)
- **Master Pengguna**: [http://localhost:8088/master/users](http://localhost:8088/master/users)
- **Sinkronisasi RS Online**: [http://localhost:8088/master/sinkronisasi](http://localhost:8088/master/sinkronisasi)

### 3. Menghentikan Container
```bash
docker compose down
```

---

## 📁 Struktur Direktori

```text
kompetensi-rs/
├── app/
│   ├── assets/               # CSS, JS, dan Gambar (logo, favicon)
│   ├── public/
│   │   └── index.php         # Front Controller / Routing
│   ├── src/
│   │   ├── Api/              # REST endpoint async sync-log
│   │   ├── Auth/             # Logika Autentikasi (Auth, login, logout)
│   │   ├── Dashboard/        # Controller Dashboard & Data Kompetensi
│   │   ├── Database/         # Koneksi PDO singleton (Db.php)
│   │   ├── Master/           # Controller Profil RS, Pengguna, & Sinkronisasi
│   │   ├── Csrf.php          # Utilitas Token CSRF
│   │   ├── Flash.php         # Utilitas Notifikasi Flash
│   │   ├── Router.php        # Dispatcher Route URI
│   │   └── helpers.php       # Fungsi global
│   └── views/
│       ├── auth/             # View Halaman Login
│       ├── errors/           # View 404 & 403
│       └── layout/           # Template Layout Navbar + Sidebar
├── docker/
│   ├── mysql/
│   │   └── init.sql          # Skema database & data awal (seed)
│   ├── nginx/
│   │   └── default.conf      # Konfigurasi reverse proxy & fastcgi
│   └── php/
│       └── Dockerfile        # Container PHP 8.3-FPM Alpine
├── docker-compose.yml        # Orkestrasi Nginx + PHP + MySQL
└── .env                      # Konfigurasi environment (Port, DB credentials)
```

---

## 🔒 Keamanan & Praktik Terbaik

- **Security Headers**: Dilengkapi `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection`.
- **Prepared Statements**: Seluruh interaksi database menggunakan PDO Prepared Statements untuk mencegah SQL Injection.
- **CSRF Token**: Dilengkapi token verifikasi pada setiap permintaan `POST`.
- **Session Strictness**: Cookie sesi terlindung dengan flag `HttpOnly`, `SameSite=Lax`, dan `use_strict_mode`.
