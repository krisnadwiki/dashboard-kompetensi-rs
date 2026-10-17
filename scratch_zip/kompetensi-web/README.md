# Kompetensi Layanan RSUD Kilisuci

Aplikasi web untuk merekap pemenuhan komponen kompetensi RS Online (SDM, alat kesehatan, sarana) untuk 24 layanan, lengkap dengan dashboard dan ekspor rekap Excel.

## Menjalankan

Aplikasi ini statis (HTML + JavaScript), tidak butuh database atau bahasa server.

- **Di komputer sendiri:** buka `index.html` dengan Chrome/Edge/Firefox.
- **Di server RS (intranet):** salin seluruh folder `kompetensi-web` ke folder web server (Apache `htdocs`, Nginx `html`, IIS `wwwroot`, atau XAMPP `htdocs`), lalu buka `http://<alamat-server>/kompetensi-web/`.

Semua library (PapaParse, ExcelJS) sudah ada di folder `vendor`, jadi aplikasi tetap jalan tanpa internet. Hanya font Google yang butuh internet; tanpa internet font diganti font bawaan sistem.

## Cara pakai

1. Klik **Unggah CSV**, pilih satu atau banyak file CSV (atau tarik ke kotak unggah).
2. Panel *Kelengkapan 24 layanan* menunjukkan layanan mana yang sudah memakai CSV Anda.
3. Dashboard dan daftar kekurangan diperbarui otomatis.
4. Klik **Unduh rekap Excel** untuk file `.xlsx` (sheet Ringkasan, Kekurangan, Detail, Data mentah, Catatan).

Data yang diunggah diproses di browser dan disimpan di browser itu saja (localStorage); tidak dikirim ke server mana pun.

## Format CSV

Contoh: `template/Template_Kompetensi_RS_Online.csv`. Kolom mengikuti tabel PDF RS Online; urutan bebas; pemisah koma, titik koma, atau tab.

| Kolom | Wajib | Isi |
|---|---|---|
| No Layanan / Layanan | salah satu, atau dari nama file | 1–24 atau nama layanan. Bila kosong diambil dari nama file, mis. `5. Neoplasma - SDM.csv` |
| Strata | ya | Dasar, Madya, Utama, Paripurna |
| Nama | ya | Nama SDM / alat / sarana |
| Kelompok | disarankan | SDM Medis, Alat Kesehatan, Sarana |
| Status | ya | wajib / substitusi |
| Jumlah | ya | Jumlah yang dimiliki RS |
| Group | ya | Kode group, mis. ksdm01, kalkes05, ksarana02 |

File baru menggantikan data layanan + komponen (SDM / Alkes) yang sama.

## Aturan hitung

Satu group terpenuhi bila semua item **wajib** di group itu berjumlah > 0 dan, bila ada item **substitusi**, minimal satu berjumlah > 0.

## Struktur folder

```
index.html            halaman aplikasi
assets/app.css        tampilan
assets/app.js         logika: baca CSV, hitung rekap, dashboard, ekspor Excel
data/seed.js          data bawaan (rekap PDF 17 Okt 2026), dipakai sampai CSV diunggah
vendor/               PapaParse 5.4.1 dan ExcelJS 4.4.0 (lisensi MIT)
template/             contoh CSV
```

Untuk mengganti data bawaan, ubah `data/seed.js` atau kosongkan dengan `window.SEED_DATA = [];`.
