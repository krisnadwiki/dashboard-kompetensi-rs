# 🏥 Sistem Informasi Kompetensi RS — RSUD Kilisuci

Aplikasi web terpadu untuk monitoring dan analisis kompetensi layanan rumah sakit, terintegrasi dengan **SIRS Online (RS Online Kemenkes)** melalui mekanisme **bookmarklet crawler** otomatis. Dibangun di atas stack **PHP 8.3 + MySQL 8.4 + Nginx**, dikemas dengan **Docker Compose**.

---

## 🚀 Fitur Utama

### 1. Dashboard & Monitoring Kompetensi
- Ringkasan distribusi tingkat kompetensi 24 layanan terstandar Kemenkes (Tidak Kompeten → Dasar → Madya → Utama → Paripurna).
- Analisis gap interaktif: peta pemenuhan per layanan × strata, grafik kekurangan, kekurangan berulang lintas layanan.
- Daftar kekurangan per group persyaratan dengan filter multi-dimensi (layanan, strata, komponen, sifat).
- Seluruh detail komponen RS Online dengan highlight warna: merah (wajib belum terpenuhi), oranye (substitusi belum ada yang terpenuhi), abu (substitusi terpenuhi via opsi lain).
- Tampilan publik tanpa login (`/static-dashboard/`) — data otomatis diambil dari snapshot terbaru.

### 2. Tarik Data SIRS via Bookmarklet Crawler
Alur penarikan data menggunakan **JavaScript Bookmarklet** yang dijalankan langsung di browser saat operator membuka portal SIRS Kemenkes:

**Cara kerja:**
1. Operator login ke SIRS Online (`sirs.kemkes.go.id`) di browser.
2. Klik bookmarklet yang sudah dipasang di bookmark bar browser.
3. Bookmarklet secara otomatis memanggil 48 endpoint SIRS di latar belakang (24 layanan × 2 komponen: SDM & Alkes/Sarana) dengan jeda 250ms per layanan.
4. Floating card notifikasi muncul di pojok kanan atas halaman SIRS, menampilkan progres pengambilan data secara real-time.
5. Setelah semua data terkumpul, bookmarklet mengirimkan seluruh data ke endpoint API SIMRS (`/api/ingest-sirs`) via `fetch POST`.
6. Jika POST berhasil → data tersimpan sebagai snapshot baru dan live-dashboard langsung terupdate.
7. Jika koneksi ke SIMRS gagal (misal beda domain) → data otomatis disalin ke clipboard dalam format JSON untuk diimpor manual.

**Keunggulan dibanding metode copy-paste manual:**
- Tidak perlu membuka 24 halaman satu per satu.
- Tidak perlu menyalin tabel DataTables secara manual.
- Seluruh 24 layanan (SDM + Alkes) ditarik sekaligus dalam satu klik.
- Parsing otomatis mendeteksi format kolom SIRS (baik SDM maupun Alkes/Sarana).

**Metode alternatif (jika bookmarklet tidak bisa):**
- Salin tabel DataTables dari SIRS secara manual, lalu paste di form "Tarik Data dari Clipboard SIRS" pada halaman Sinkronisasi.

### 3. Snapshot & Riwayat Data
- Setiap tarikan data disimpan sebagai **snapshot** dengan nama, periode, dan timestamp.
- Dashboard publik selalu menampilkan snapshot terbaru secara otomatis.
- Admin dapat mengelola dan menghapus snapshot lama.
- Log audit setiap operasi sinkronisasi tersimpan di tabel `sync_log`.

### 4. Autentikasi & Multi-Peran
- Role **Administrator**: Akses penuh ke Master Data, Pengguna, Sinkronisasi, dan Snapshot.
- Role **Operator**: Akses Dashboard, Data Kompetensi, dan Sinkronisasi.
- Proteksi CSRF, session fixation prevention, dan password hashing Bcrypt.

### 5. Antarmuka Modern & Responsif
- Sidebar dengan mode kolaps (desktop) dan drawer overlay (mobile).
- Toggle tema Terang / Gelap.
- Floating badge ICT RSUD Kilisuci.
- Footer sticky di semua halaman.
- Modal konfirmasi logout (tidak pakai `alert()`).
- Meta Open Graph & Twitter Card untuk preview WhatsApp yang profesional.

---

## 🔑 Akun Bawaan

| Role | Username | Password |
| :--- | :--- | :--- |
| **Administrator** | `admin` | `Admin@2026!` |
| **Operator** | `operator` | `Admin@2026!` |

> ⚠️ Ubah password default sebelum deploy ke production.

---

## 🐳 Menjalankan dengan Docker Compose

### 1. Clone & Konfigurasi

```bash
cp .env.example .env
# Edit .env sesuai kebutuhan (port, credentials)
```

### 2. Jalankan Container

```bash
docker compose up -d --build
```

| Container | Service | Port |
|:---|:---|:---|
| `kompetensi_nginx` | Web Server | `http://localhost:8088` |
| `kompetensi_app` | PHP 8.3-FPM | Internal |
| `kompetensi_db` | MySQL 8.4 | `localhost:3309` |

### 3. Akses Aplikasi

| Halaman | URL |
|:---|:---|
| Login | http://localhost:8088/login |
| Dashboard | http://localhost:8088/dashboard |
| Data Kompetensi (publik) | http://localhost:8088/static-dashboard/ |
| Master Profil RS | http://localhost:8088/master/profil-rs |
| Master Pengguna | http://localhost:8088/master/users |
| Sinkronisasi & Bookmarklet | http://localhost:8088/master/sinkronisasi |

### 4. Menghentikan Container

```bash
docker compose down
```

---

## 🔄 Alur Sinkronisasi Data SIRS

```
Browser Operator (saat login di sirs.kemkes.go.id)
    │
    ├─ Klik Bookmarklet
    │      │
    │      ├─ Cek: apakah domain kemkes.go.id? → Jika tidak, buka tab SIRS
    │      │
    │      └─ Fetch 48 endpoint SIRS di background (24 layanan × SDM + Alkes)
    │             ├─ /fo/dashboard/sdm_detil/{no_layanan}
    │             └─ /fo/dashboard/alkessarana_detil/{no_layanan}
    │
    ├─ Parsing otomatis (deteksi kolom: No, Strata, Kode, Nama, Kelompok, Status, Jumlah, Group)
    │
    ├─ POST ke /api/ingest-sirs (SIMRS)
    │      ├─ Sukses → Snapshot tersimpan, log audit dicatat
    │      └─ Gagal  → Data di-copy ke clipboard (fallback)
    │
    └─ Dashboard publik otomatis reload snapshot terbaru
```

---

## 📁 Struktur Direktori

```text
kompetensi-rs/
├── app/
│   ├── assets/
│   │   ├── css/              # app.css, login.css
│   │   └── img/              # logo.svg, badge-ict.svg, og-image.png
│   ├── public/
│   │   └── index.php         # Front Controller & Router
│   ├── src/
│   │   ├── Api/
│   │   │   ├── ingest_sirs_controller.php   # Endpoint POST dari bookmarklet
│   │   │   ├── snapshot_data_controller.php  # Endpoint GET data publik
│   │   │   └── sync_log_controller.php
│   │   ├── Auth/             # Auth.php, login, logout
│   │   ├── Dashboard/        # dashboard_controller, kompetensi_controller
│   │   ├── Database/         # Db.php (PDO singleton)
│   │   ├── Master/
│   │   │   ├── sinkronisasi_controller.php  # Bookmarklet generator & clipboard ingest
│   │   │   ├── snapshots_controller.php
│   │   │   ├── profil_rs_controller.php
│   │   │   └── users_controller.php
│   │   ├── Csrf.php
│   │   ├── Flash.php
│   │   ├── Router.php
│   │   └── helpers.php
│   └── views/
│       ├── auth/             # login.php
│       ├── errors/           # 403.php, 404.php
│       └── layout/           # app.php (sidebar + navbar)
├── assets/                   # Asset untuk static dashboard (app.css, app.js)
├── data/
│   └── seed.js               # Data rekap bawaan (fallback offline)
├── docker/
│   ├── mysql/init.sql        # Skema DB & seed awal
│   ├── nginx/default.conf    # Reverse proxy, cache control, ETag
│   └── php/Dockerfile        # PHP 8.3-FPM Alpine
├── index.html                # Static dashboard publik (/static-dashboard/)
├── docker-compose.yml
└── .env
```

---

## 🔒 Keamanan

| Aspek | Implementasi |
|:---|:---|
| SQL Injection | PDO Prepared Statements di semua query |
| CSRF | Token verifikasi di setiap POST |
| Session | `HttpOnly`, `SameSite=Lax`, `use_strict_mode` |
| Password | Bcrypt hashing |
| Headers | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` |
| API Publik | `Cache-Control: no-cache, must-revalidate` — selalu ambil data terbaru |
| Asset Cache | ETag-based revalidation — efisien, tidak pernah stale |
| Bookmarklet | Validasi domain kemkes.go.id sebelum eksekusi; tidak menyimpan credentials |

---

## 🗄️ Database Schema

Tabel utama:

| Tabel | Deskripsi |
|:---|:---|
| `users` | Akun operator & admin |
| `rs_profile` | Identitas & konfigurasi RS |
| `snapshots` | Hasil tarikan data SIRS (JSON blob) |
| `sync_log` | Audit log setiap operasi sinkronisasi |

---

## 📦 Tech Stack

| Layer | Teknologi |
|:---|:---|
| Backend | PHP 8.3 (tanpa framework) |
| Database | MySQL 8.4 |
| Web Server | Nginx 1.27 |
| Container | Docker + Docker Compose |
| Frontend | Vanilla JS, Font Awesome 6.5, Lucide Icons |
| Chart | SVG custom (donut chart, horizontal bar) |
| Cache Strategy | ETag + `no-cache, must-revalidate` |
