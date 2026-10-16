# Nikaido — Panduan AI

Baca panduan ini sebagai orientasi awal. Untuk tugas kode, langsung buka modul pemilik dan test yang terkait; jangan memindai seluruh repo atau membaca ulang semua dokumentasi.

## Fakta proyek

- Aplikasi backtesting trading berbasis PHP 8.2 dan Laravel 12; test memakai PHPUnit.
- Kode fitur ada di `app/Feature/`, namespace `App\Feature\`; autoload didefinisikan di `composer.json`.
- `app/Platform/` berisi kemampuan lintas fitur/teknis, termasuk Strategy, Console, dan Infrastructure.
- Modul: `Dashboard`, `MarketTypes`, `MarketPairs`, `Timeframes`, `MarketPrice`, `DataImport`, `MarketExploration`, dan `Backtest`.
- Struktur aktual memakai `app/Feature/` (F besar). Sebagian dokumentasi lama masih menyebut `app/feature/` atau struktur `Engine`/`Workbench`; ikuti kode, autoload Composer, dan panduan ini untuk struktur saat ini.

## Peta modul

| Modul | Area utama |
|---|---|
| `MarketTypes` | `/workbench/market-types` |
| `MarketPairs` | `/workbench/market-pairs` |
| `Timeframes` | `/workbench/timeframes` |
| `MarketPrice` | `/workbench/market-prices` |
| `DataImport` | `/workbench/data-import` |
| `MarketExploration` | `/workbench/market-exploration` |
| `Backtest` | `/workbench/tests/...` dan API `/api/engine/specific-pair-test` |
| `Dashboard` | Dashboard aplikasi |

Route web modul ada di `app/Feature/<Module>/routes.php`; route API ada pada `api.php` modul yang memilikinya. Pendaftaran web/API berada di `routes/web.php` dan `bootstrap/app.php`.

## Batas arsitektur

- Controller menangani HTTP lalu memanggil Action milik modulnya sendiri. **Jangan panggil Action fitur lain dari Controller.**
- Action mengorkestrasi operasi; bila integrasi lintas fitur diperlukan, tempatkan koordinasinya pada Action pemilik use case, bukan Controller.
- Repository menangani query/persistensi; Model Eloquent merepresentasikan penyimpanan dan tetap anemik.
- Process menjalankan algoritma/perhitungan tanpa HTTP atau akses database langsung. Rules memuat kebijakan domain; Data membawa input, state, atau hasil non-persisten.
- Gunakan hanya direktori/komponen yang dibutuhkan modul; jangan menambah abstraksi atau membuat ulang kode yang sudah ada.
- Di `Backtest`, `Rules`, `Process`, dan `Data` dipisahkan menurut tanggung jawab; `Action`, `Model`, dan `Repository` tetap pada kategorinya sendiri.
- Gunakan Exact untuk tipe/koleksi domain dan Gauss untuk komputasi matematis saat sesuai; cek API versi terpasang sebelum memakai kemampuan yang belum dikenal.

## Alur kerja hemat konteks

1. Tentukan modul dari route atau perilaku yang diminta; cari file implementasi dan test terdekat saja.
2. Baca caller dan dependency langsung sebelum mengubah kontrak; pertahankan perilaku serta batas modul.
3. Perbarui atau tambahkan test untuk perilaku yang berubah. Jalankan test terkait terlebih dahulu; gunakan seluruh suite untuk refactor lintas modul atau perubahan yang berisiko luas.
4. Jalankan Pint pada file yang disentuh dan laporkan perintah serta hasil verifikasi sebenarnya.
5. **Evaluasi kebutuhan pembaruan dokumentasi setelah implementasi.** Jika perubahan kode memengaruhi perilaku, API, konfigurasi, alur kerja, struktur modul, arsitektur, atau cara penggunaan, perbarui dokumentasi terkait agar tetap sesuai dengan implementasi.
6. Jika dokumentasi yang ada menjadi usang, bertentangan dengan kode, atau memberikan instruksi yang tidak lagi berlaku akibat tugas tersebut, perbaiki bagian yang relevan dalam lingkup perubahan.
7. Jangan mengubah dokumentasi yang tidak berkaitan, membuat dokumentasi baru tanpa kebutuhan nyata, atau membaca semua dokumentasi hanya untuk menyelesaikan tugas lokal. Baca bagian yang relevan saja.
8. Jika perubahan tidak memerlukan pembaruan dokumentasi, jangan mengubahnya secara paksa.

## Verifikasi

Gunakan perintah yang relevan dengan perubahan:

```sh
php artisan test --compact
vendor/bin/pint --test <file-atau-folder>
npm run build
```

- Jalankan test yang paling relevan terlebih dahulu jika tersedia.
- Jalankan seluruh suite bila cakupan perubahan atau risikonya memerlukan.
- Jalankan `npm run build` jika perubahan memengaruhi frontend.
- Jangan mengklaim verifikasi berhasil jika perintah belum dijalankan atau gagal.
- Laporkan test, pemeriksaan kualitas kode, build, dan pembaruan dokumentasi yang benar-benar dilakukan. Sebutkan pemeriksaan yang dilewati jika relevan.

## Referensi

- `docs/PROJECT.md` — ringkasan tujuan dan prinsip proyek.
- `docs/coding-standar.md` — standar PHP, tipe, arsitektur, Exact/Gauss, dan pengujian; perhatikan bahwa sebagian contoh path di dokumen ini masih memakai casing lama.

Dokumentasi merupakan bagian dari pemeliharaan kode. **Pertahankan keselarasan antara implementasi, test, dan dokumentasi tanpa memperluas cakupan tugas secara tidak perlu.**