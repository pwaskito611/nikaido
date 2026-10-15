# Coding Standards — PHP 8.2+ with Exact and Gauss

## 1. Purpose

Standar ini berlaku untuk seluruh pengembangan proyek PHP.

- **Exact (`pandu/exact`)** — pemodelan tipe domain, representasi hasil, pattern matching, dan koleksi immutable.
- **Gauss (`pandu/gauss`)** — operasi matematika, statistik, probabilitas, analisis numerik, dan komputasi matematis yang relevan.
- **Feature-Based Architecture** — mengorganisasi kode berdasarkan kapabilitas aplikasi di bawah `app/feature/`.
- **Core Architecture** — Controller, Action, Repository, dan Model sebagai fondasi setiap feature.
- **Optional Components** — Data, Config, Process, dan Rules sebagai komponen tambahan berdasarkan kebutuhan.
- **Anemic Model** — menggunakan model sebagai representasi data penyimpanan tanpa business logic substantif.
- **PHPUnit** — menguji perilaku, integrasi, dan regresi.

Prinsip utama:

- Correctness lebih penting daripada kenyamanan implementasi.
- Kontrak tipe harus eksplisit.
- Setiap komponen memiliki tanggung jawab yang jelas.
- Setiap feature harus memiliki struktur internal yang konsisten dan bebas anomali.
- Struktur antar-feature boleh berbeda sesuai kebutuhan dan kompleksitasnya.
- Konsistensi tidak berarti semua feature harus memiliki direktori atau kelas yang identik.
- Abstraksi harus memiliki kebutuhan nyata.
- Hindari God Function, God Method, God Class, duplikasi logika, dan dependensi melingkar.
- Jangan membuat Service atau Use Case terpisah.
- Tidak setiap operasi membutuhkan seluruh jenis komponen.
- Jangan menambahkan lapisan hanya untuk memenuhi pola arsitektur tertentu.

## 2. Feature-Based Architecture

Seluruh kode spesifik feature aplikasi ditempatkan di bawah `app/feature/`.

Feature dikelompokkan berdasarkan kapabilitas aplikasi, bukan berdasarkan jenis file secara global.

Contoh struktur:

```text
app/
└── feature/
    ├── MarketTypes/
    │   ├── Controller/
    │   ├── Action/
    │   ├── Repository/
    │   ├── Model/
    │   └── Tests/
    │       ├── Unit/
    │       └── Integration/
    │
    ├── MarketExploration/
    │   ├── Controller/
    │   ├── Action/
    │   ├── Repository/
    │   ├── Model/
    │   ├── Rules/
    │   ├── Config/
    │   └── Tests/
    │       ├── Unit/
    │       └── Integration/
    │
    └── Backtest/
        ├── Controller/
        ├── Action/
        ├── Repository/
        ├── Model/
        ├── Data/
        ├── Rules/
        ├── Process/
        ├── Config/
        └── Tests/
            ├── Unit/
            └── Integration/
```

Struktur tersebut hanya ilustrasi. Direktori tambahan digunakan apabila ada tanggung jawab nyata yang memerlukannya.

### 2.1 Core Components

Setiap feature mengikuti fondasi arsitektur yang sama:

- **Controller** — menangani batas transport atau entry point eksternal feature.
- **Action** — merepresentasikan dan mengorkestrasi operasi aplikasi.
- **Repository** — menangani akses dan persistensi data.
- **Model** — merepresentasikan data penyimpanan, terutama model Eloquent.

Fondasi tersebut merupakan standar bersama. Tanggung jawab komponen harus konsisten antarf​eature meskipun implementasi dan jumlah kelasnya berbeda.

Keberadaan empat komponen dalam standar tidak mengharuskan setiap operasi melewati semuanya. Contohnya, sebuah Action yang melakukan komputasi tanpa membaca atau menyimpan data tidak perlu memanggil Repository.

### 2.2 Optional Components

Komponen berikut hanya ditambahkan apabila diperlukan:

- **Data** — merepresentasikan kontrak input, output, atau data antara yang membutuhkan struktur eksplisit.
- **Config** — menyimpan parameter dan pengaturan yang memang perlu dipisahkan dari implementasi.
- **Process** — melaksanakan tugas atau kapabilitas yang memiliki tanggung jawab jelas.
- **Rules** — mengisolasi aturan, kriteria, constraint, atau kebijakan domain yang bermakna.

Tidak ada kewajiban untuk membuat keempat direktori tersebut di setiap feature.

Jangan membuat direktori kosong, kelas placeholder, atau abstraksi tanpa fungsi nyata demi menyeragamkan struktur.

### 2.3 Cross-Feature Consistency

Aturan berikut wajib dipertahankan:

- Semua feature menggunakan definisi tanggung jawab Controller, Action, Repository, dan Model yang sama.
- Jika sebuah feature menggunakan komponen tambahan, komponen tersebut harus mengikuti aturan penamaan, lokasi, dan tanggung jawab yang berlaku.
- Kelas dengan tanggung jawab yang sama tidak boleh ditempatkan secara berbeda di dalam feature yang sama tanpa alasan arsitektural yang jelas.
- Jangan mencampurkan Rules dengan Repository, Config dengan algoritma, atau Controller dengan orkestrasi aplikasi.
- Jangan membuat variasi struktur internal yang bersifat insidental, misalnya meletakkan sebagian Process langsung di root feature dan sebagian lainnya di direktori `Process/`.
- Jika satu feature membutuhkan subdirektori tambahan, terapkan pola tersebut secara konsisten untuk seluruh komponen dengan kategori dan tanggung jawab yang sama.
- Perbedaan struktur antar-feature harus dapat dijelaskan oleh perbedaan tanggung jawab atau kompleksitas, bukan preferensi implementasi yang berubah-ubah.

### 2.4 Cross-Feature Dependencies

- Setiap feature bertanggung jawab atas implementasi internalnya sendiri.
- Feature lain tidak boleh bergantung secara sembarangan pada detail internal feature yang bukan miliknya.
- Gunakan entry point atau kontrak publik yang jelas ketika operasi suatu feature diperlukan oleh feature lain.
- Orkestrasi yang melibatkan beberapa kapabilitas ditempatkan pada Action feature pemanggil atau feature orkestrasi yang memang memiliki tanggung jawab tersebut.
- Hindari dependensi melingkar antarf​eature.
- Jangan menduplikasi business logic hanya untuk menghindari dependensi lintas feature.
- Jangan membuat lapisan global untuk Action, Repository, Process, atau Rules apabila tanggung jawabnya khusus milik suatu feature.

Contoh namespace:

```php
App\Feature\MarketTypes\Controller\MarketTypeController
App\Feature\MarketTypes\Action\CreateMarketTypeAction
App\Feature\MarketTypes\Repository\MarketTypeRepository

App\Feature\MarketExploration\Rules\ExplorationRules

App\Feature\Backtest\Data\BacktestInput
App\Feature\Backtest\Process\ExecuteStrategyProcess
```

`app/feature/` merupakan konvensi proyek, bukan struktur wajib bawaan Laravel. Pertahankan kapitalisasi dan konvensi namespace yang ditetapkan proyek.

## 3. Core Architecture

### 3.1 Controller — Transport Boundary

Controller menerima permintaan dari batas transport dan meneruskan operasi ke Action.

Tanggung jawab:

- Menerima request HTTP.
- Menggunakan FormRequest untuk validasi transport-level apabila sesuai.
- Mengambil input yang telah divalidasi.
- Mengonversi input menjadi kontrak input Action apabila diperlukan.
- Memanggil Action yang relevan.
- Membentuk respons HTTP berdasarkan hasil Action.

Larangan:

- Tidak mengimplementasikan business logic substantif.
- Tidak mengorkestrasi Process secara langsung.
- Tidak menjalankan algoritma domain atau perhitungan kompleks.
- Tidak menulis query database.
- Tidak mengakses Repository secara langsung.
- Tidak menangani detail internal proses aplikasi yang semestinya menjadi tanggung jawab Action.

Controller tetap tipis: menerima input, memanggil Action, dan mengembalikan respons.

### 3.2 Action — Application Orchestration

Action merepresentasikan satu operasi aplikasi yang mempunyai tujuan jelas.

Contoh:

- `CreateMarketTypeAction`
- `RunBacktestAction`
- `GeneratePerformanceReportAction`

Tanggung jawab:

- Menjadi entry point operasi aplikasi melalui `execute()`.
- Menentukan urutan pemanggilan komponen yang diperlukan.
- Mengatur alur sukses, percabangan orkestrasi, dan penanganan kegagalan.
- Meneruskan input dan hasil antartahap.
- Menentukan kapan data perlu dibaca atau disimpan.
- Menghasilkan output sesuai kontrak aplikasi.

Aturan:

- Satu Action memiliki satu tujuan aplikasi yang jelas.
- `execute()` memperlihatkan alur utama, bukan seluruh detail implementasi.
- Action boleh memanggil Rules, Process, Repository, atau komponen lain sesuai kebutuhan.
- Action boleh melakukan tugas sederhana secara langsung.
- Action tidak menulis query database secara langsung.
- Action tidak menjadi tempat utama implementasi algoritma domain yang kompleks.
- Action tidak menduplikasi aturan yang sudah dimiliki Rules atau Process.
- Kegagalan pada tahap penting harus ditangani sebelum tahap berikutnya dijalankan.
- Jangan membuat Service atau Use Case terpisah.
- Jangan membuat base Action, interface, atau handler generik tanpa kebutuhan konkret.

Action boleh memiliki metode privat untuk membantu orkestrasi atau pemetaan hasil selama tanggung jawabnya tetap koheren.

### 3.3 Repository — Data Access

Repository menangani akses dan persistensi data.

Tanggung jawab:

- Menjalankan query database.
- Membaca, menyimpan, memperbarui, dan menghapus data.
- Menggunakan Eloquent atau mekanisme penyimpanan proyek.
- Memetakan hasil penyimpanan menjadi tipe yang diperlukan pemanggil.
- Mengonversi representasi internal ke format penyimpanan dan sebaliknya.

Aturan:

- Repository tidak mengorkestrasi keseluruhan operasi aplikasi.
- Repository tidak menjadi tempat business logic substantif atau algoritma domain.
- Repository tidak mengembalikan query builder mentah jika kontraknya seharusnya menghasilkan data.
- Jangan membuat interface Repository secara otomatis.
- Pisahkan Repository berdasarkan tanggung jawab akses data yang nyata, bukan jumlah metode.
- Gunakan tipe hasil yang eksplisit untuk data tunggal, opsional, jamak, atau kegagalan.
- Repository boleh dipanggil Action atau Process sesuai kebutuhan.
- Jangan memindahkan tanggung jawab secara artifisial hanya untuk memenuhi pola pemanggilan tertentu.

### 3.4 Model — Anemic Model

Model merepresentasikan data penyimpanan, terutama model Eloquent.

Contoh:

- `MarketPrice`
- `BacktestRecord`
- `TradingConfiguration`

Aturan:

- Model berfokus pada atribut, casting, relasi, dan perilaku teknis yang diperlukan Eloquent.
- Business logic substantif berada pada Rules atau Process sesuai jenis tanggung jawabnya.
- Orkestrasi aplikasi berada pada Action.
- Akses penyimpanan melalui Repository sesuai konvensi proyek.
- Model tidak mengatur keseluruhan alur aplikasi.
- Model tidak diwajibkan menyimpan atribut dalam tipe Exact.

Jangan menambahkan perilaku kompleks ke Model hanya karena Eloquent menyediakan tempat untuk melakukannya.

## 4. Optional Components

### 4.1 Data — Data Representation

Data merepresentasikan informasi yang digunakan atau dihasilkan operasi.

Contoh:

- `RunBacktestInput`
- `BacktestResult`
- `MarketCandle`
- `PerformanceMetrics`

Tanggung jawab:

- Menyatakan struktur input, output, atau data antara.
- Menetapkan kontrak data yang eksplisit.
- Mengurangi ketergantungan pada array asosiatif yang strukturnya tidak jelas.
- Menggunakan tipe Exact jika sesuai dengan kontrak internal.

Aturan:

- Data tidak mengorkestrasi operasi aplikasi.
- Data tidak menjalankan query database.
- Data tidak menjadi tempat business logic substantif.
- Data tidak harus selalu berupa kelas DTO.
- Jangan membuat kelas Data hanya untuk membungkus nilai sederhana tanpa manfaat yang jelas.
- Data tidak menggantikan Model atau Repository.

Perbedaan tanggung jawab:

- Data menyatakan informasi yang dibutuhkan atau dihasilkan operasi.
- Model merepresentasikan data penyimpanan.
- Repository menangani cara data dibaca dan disimpan.

Komponen Data dapat tidak digunakan apabila tipe PHP atau tipe Exact yang tersedia sudah menyatakan kontrak dengan jelas.

### 4.2 Config — Configuration

Config menyediakan parameter yang mengatur perilaku feature.

Contoh:

- Parameter default eksplorasi pasar.
- Batas dan opsi simulasi backtest.
- Pengaturan sumber data.
- Pengaturan perilaku proses yang dapat diubah tanpa mengubah algoritma.

Aturan:

- Config berisi parameter, opsi, dan nilai konfigurasi.
- Config tidak menjadi tempat business logic.
- Config tidak menjalankan query database.
- Config tidak mengorkestrasi operasi aplikasi.
- Jangan memindahkan seluruh nilai literal ke Config secara mekanis.
- Pisahkan nilai konfigurasi dari konstanta internal yang memang tidak perlu diubah oleh konfigurasi.
- Validasi parameter sesuai batas transport atau aturan domain yang berlaku.

Gunakan Config ketika pengaturan tersebut memiliki kebutuhan konfigurasi nyata. Tidak setiap feature memerlukan direktori Config.

### 4.3 Rules — Domain Rules

Rules memuat aturan, kriteria, constraint, atau kebijakan domain yang mempunyai makna tersendiri.

Contoh:

- Kriteria validasi konfigurasi trading.
- Kriteria seleksi market.
- Batasan validitas parameter simulasi.
- Aturan penerimaan atau penolakan kondisi domain.

Aturan:

- Rules berfokus pada keputusan atau aturan yang diterapkan terhadap input.
- Rules tidak menangani HTTP.
- Rules tidak menulis query database.
- Rules tidak mengorkestrasi seluruh operasi aplikasi.
- Rules tidak menjadi tempat penyimpanan konfigurasi.
- Rules boleh menggunakan Data atau tipe Exact sesuai kebutuhan.
- Rules dapat digunakan oleh Action atau Process apabila penggunaannya sesuai dengan tanggung jawab feature.
- Aturan sederhana boleh tetap berada pada Action atau Process jika pemisahan belum memberikan manfaat yang nyata.

Pisahkan Rules ketika aturan memiliki makna domain yang jelas, kompleksitas tersendiri, kebutuhan pengujian independen, atau digunakan pada beberapa bagian internal feature.

### 4.4 Process — Individual Operation

Process melaksanakan tugas atau kapabilitas tertentu yang menjadi bagian dari operasi aplikasi.

Contoh:

- `LoadMarketDataProcess`
- `ExecuteStrategyProcess`
- `SimulateOrderExecutionProcess`
- `CalculatePerformanceProcess`

Tanggung jawab:

- Melaksanakan tugas spesifik dengan tujuan yang dapat dijelaskan.
- Menjalankan algoritma, transformasi, atau tahapan komputasi yang relevan.
- Menggunakan Rules, Data, Exact, Gauss, atau Repository sesuai kebutuhan.
- Menghasilkan output dengan kontrak eksplisit.
- Memisahkan detail implementasi yang bermakna dari orkestrasi Action.

Aturan:

- Process boleh memiliki metode privat untuk implementasi internal.
- Process boleh menggunakan Repository jika akses data merupakan bagian dari tugasnya.
- Process boleh memanggil Process lain jika dependensinya nyata dan tidak menimbulkan siklus.
- Process tidak menangani HTTP.
- Process tidak mengambil alih orkestrasi keseluruhan operasi aplikasi.
- Jangan membuat Process hanya untuk membungkus satu pemanggilan metode tanpa manfaat nyata.
- Jangan membuat Process untuk setiap langkah mekanis yang terlalu kecil.
- Jangan menggabungkan kapabilitas yang tidak berkaitan ke dalam satu God Class.

**Tidak semua feature membutuhkan Process. Tidak semua Action membutuhkan Process terpisah.** Action boleh melaksanakan operasi sederhana secara langsung. Buat Process ketika tugas mempunyai kompleksitas, tanggung jawab mandiri, atau kebutuhan pengujian dan penggunaan ulang yang membenarkannya.

## 5. Component Selection and Structural Consistency

Pemilihan komponen ditentukan oleh tanggung jawab, bukan oleh kewajiban memiliki seluruh direktori.

| Kebutuhan | Komponen |
|---|---|
| Menangani request dan respons HTTP | Controller |
| Mengatur operasi aplikasi | Action |
| Membaca atau menyimpan data | Repository |
| Merepresentasikan data penyimpanan | Model |
| Membawa kontrak input, output, atau data antara | Data |
| Menyediakan parameter yang dapat dikonfigurasi | Config |
| Menyatakan aturan dan kriteria domain | Rules |
| Melaksanakan tugas atau kapabilitas tertentu | Process |

### 5.1 Consistency Within a Feature

Setiap feature wajib konsisten secara internal.

- Gunakan konvensi direktori dan namespace yang konsisten.
- Tempatkan komponen berdasarkan tanggung jawabnya.
- Jangan mencampur kelas dengan tanggung jawab yang sama di beberapa lokasi tanpa alasan jelas.
- Terapkan pola penamaan yang konsisten untuk kelas dan metode dengan peran serupa.
- Pertahankan batas tanggung jawab yang sama sepanjang implementasi feature.
- Jangan membuat pengecualian lokal tanpa alasan arsitektural yang dapat dijelaskan.
- Jika struktur internal feature berubah, periksa seluruh feature agar tidak meninggalkan pola lama yang bertentangan.
- Pastikan implementasi baru mengikuti konvensi internal feature yang telah ditetapkan.

### 5.2 Variation Between Features

Struktur antar-feature boleh berbeda.

Contoh:

- Market Types dapat menggunakan Controller, Action, Repository, dan Model saja.
- Market Exploration dapat menambahkan Rules dan Config.
- Backtest dapat menggunakan seluruh komponen, termasuk Data dan Process.

Perbedaan tersebut sah selama:

1. Fondasi dan definisi tanggung jawab komponen tetap konsisten.
2. Komponen tambahan mempunyai tujuan nyata.
3. Struktur setiap feature konsisten secara internal.
4. Tidak ada duplikasi business logic yang tidak diperlukan.
5. Dependensi tetap jelas dan tidak melingkar.

Jangan memaksa feature sederhana menggunakan struktur feature kompleks. Sebaliknya, jangan mempertahankan struktur sederhana ketika kompleksitas nyata sudah membutuhkan pemisahan tanggung jawab.

### 5.3 Refactoring Decisions

Saat mengubah struktur:

- Jangan merombak feature lain hanya untuk membuat jumlah direktori identik.
- Jika pola yang sama diperlukan di beberapa feature, pertimbangkan pemisahan komponen bersama hanya ketika memang ada tanggung jawab bersama yang jelas.
- Jika ditemukan anomali internal, perbaiki berdasarkan standar tanggung jawab dan konvensi feature.
- Jangan memindahkan kelas hanya untuk membuat struktur tampak lebih simetris.
- Hindari refactoring lintas feature yang tidak diperlukan oleh perubahan saat ini.
- Periksa dampak terhadap namespace, autoloading, routing, kontrak publik, dan test.

## 6. Execution Flow

Alur HTTP yang umum:

```text
HTTP Request
    |
    v
Controller
    |
    v
Action
    |
    +----> Rules
    |
    +----> Process ----> Data
    |         |
    |         +-------> Repository ----> Database
    |
    +----> Repository (simple orchestration-level access)
    |
    v
Action Output
    |
    v
Controller
    |
    v
HTTP Response
```

Diagram tersebut bersifat konseptual. Rules, Process, dan Data bersifat opsional. Tidak semua operasi harus melewati semua komponen.

Aturan alur:

- Controller meneruskan input ke Action dan membentuk respons.
- Action mengatur urutan operasi aplikasi.
- Rules menyatakan aturan atau kriteria yang relevan.
- Process melaksanakan tugas spesifik.
- Data membawa informasi dengan kontrak yang jelas.
- Repository menangani akses penyimpanan.
- Model merepresentasikan data penyimpanan.
- Action atau Process boleh bekerja tanpa Repository apabila tidak membutuhkan data persisten.
- Config menyediakan parameter jika diperlukan.
- Penanganan kegagalan harus mengikuti kontrak komponen dan mencegah operasi berikutnya berjalan secara keliru.

## 7. Exact — Internal Type and Data Modeling

Exact (`pandu/exact`) digunakan untuk pemodelan tipe dan struktur data internal apabila sesuai kebutuhan.

Aturan:

- Gunakan `Option` untuk nilai opsional yang mempunyai makna domain.
- Gunakan `Either` untuk hasil yang membedakan keberhasilan dan kegagalan secara eksplisit.
- Gunakan `Validated` untuk validasi yang membutuhkan akumulasi kesalahan.
- Gunakan `Variant` dan `PatternMatch` untuk memodelkan dan menangani alternatif state domain.
- Gunakan koleksi immutable seperti `Vec`, `Map`, dan `Set` berdasarkan semantiknya.
- Gunakan koleksi non-empty hanya ketika invariannya memang dijamin.
- Gunakan tipe Exact pada kontrak internal Action, Rules, Process, Data, dan Repository apabila sesuai dengan API serta tanggung jawab komponen.
- Jangan menggunakan Exact secara mekanis untuk setiap skalar atau objek.
- Hindari konversi berulang antara tipe Exact dan tipe PHP.
- Jangan mengubah kegagalan menjadi nilai default secara diam-diam.
- Jangan mengasumsikan API yang tidak tersedia pada versi Exact terpasang.

Konversi antara representasi internal dan eksternal dilakukan pada batas yang sesuai. Repository menangani konversi penyimpanan, sedangkan Action mengatur kontrak output aplikasi. Controller membentuk respons HTTP.

### 7.1 Exact AI Documentation

Library Exact memiliki dokumentasi khusus AI bernama `EXACT.MD` di root repository/library Exact, bukan di root proyek aplikasi.

Ketika menggunakan Exact:

1. Temukan root repository Exact yang benar-benar digunakan.
2. Baca `EXACT.MD` sebelum merancang atau mengubah penggunaan API Exact yang kompleks.
3. Periksa implementasi dan test library jika dokumentasi tidak menjawab kebutuhan.
4. Verifikasi API pada versi dependensi yang terpasang.
5. Jangan menyalin seluruh dokumentasi Exact ke proyek aplikasi.
6. Jangan membuat salinan `EXACT.MD` di root proyek aplikasi.

Jika dokumentasi tidak tersedia, periksa kode library yang dapat diakses. Jangan mengarang API yang belum diverifikasi.

## 8. Gauss — Mathematical Computation

Gauss (`pandu/gauss`) digunakan untuk operasi matematika yang tersedia dan relevan.

Area penggunaan mencakup statistik, probabilitas, aljabar linear, distribusi probabilitas, deret waktu, optimisasi, metode numerik, dan komputasi matematis lainnya yang tersedia dalam library.

Aturan:

- Gunakan Gauss ketika operasi matematisnya tersedia dan sesuai.
- Gunakan aritmetika PHP untuk operasi sederhana jika sudah memadai.
- Pilih floating-point atau perhitungan berpresisi tinggi berdasarkan kebutuhan numerik.
- Jangan mengasumsikan semua operasi Gauss menggunakan presisi tinggi secara otomatis.
- Jangan menduplikasi algoritma yang sudah tersedia dan sesuai tanpa alasan jelas.
- Pisahkan algoritma matematis dari akses penyimpanan.
- Pertahankan asumsi numerik, satuan, toleransi, determinisme, dan perilaku batas.
- Jangan mengasumsikan seluruh operasi Gauss menerima atau mengembalikan tipe Exact.
- Lakukan konversi numerik pada batas yang diperlukan dan hindari kehilangan presisi yang tidak disengaja.

### 8.1 Gauss AI Documentation

Library Gauss memiliki dokumentasi khusus AI bernama `GAUSS.MD` di root repository/library Gauss, bukan di root proyek aplikasi.

Ketika menggunakan Gauss:

1. Temukan root repository Gauss yang benar-benar digunakan.
2. Baca `GAUSS.MD` sebelum merancang atau mengubah penggunaan API Gauss yang kompleks.
3. Periksa implementasi dan test library jika dokumentasi tidak menjawab kebutuhan.
4. Verifikasi kontrak tipe, mode presisi, dan API pada versi dependensi yang terpasang.
5. Jangan menyalin seluruh dokumentasi Gauss ke proyek aplikasi.
6. Jangan membuat salinan `GAUSS.MD` di root proyek aplikasi.

Jika dokumentasi tidak tersedia, gunakan sumber yang dapat diperiksa. Jangan mengasumsikan API atau kemampuan yang belum diverifikasi.

## 9. Strategy Module

Modul Strategy merupakan tempat pengguna mendefinisikan perilaku strategi trading.

Aturan:

- `app/Platform/Strategy/Strategy.php` menjadi entry point strategi yang dipanggil Engine sesuai kontrak framework.
- Implementasi strategi dan indikator berada dalam area Strategy yang ditetapkan proyek.
- Strategy menerima rangkaian candle terurut, termasuk data pemanasan jika diwajibkan oleh kontrak Engine.
- Strategy mengembalikan keputusan sesuai kontrak yang ditetapkan.
- Engine bertanggung jawab atas pengambilan data, simulasi order, SL/TP, dan hasil backtest.
- Strategy berjalan in-process, bukan melalui HTTP/API internal.
- Konfigurasi pair, timeframe, jadwal, dan tanggal merupakan tanggung jawab Engine atau komponen konfigurasi yang sesuai.
- Action mengorkestrasi operasi backtest; Process melaksanakan tugas spesifik yang dibutuhkan.
- Gunakan Exact untuk data internal jika sesuai dengan kontrak Engine dan API yang tersedia.
- Jangan mengubah kontrak Strategy publik secara sepihak hanya demi penggunaan Exact.
- Jangan memindahkan tanggung jawab Engine ke Strategy atau sebaliknya tanpa alasan arsitektural yang jelas.

## 10. Error Handling and Result Contracts

Penanganan kegagalan harus eksplisit dan konsisten.

Aturan:

- Gunakan `Either` atau tipe hasil yang sesuai ketika kontrak perlu membedakan sukses dan gagal.
- Gunakan `Option` untuk ketiadaan nilai yang valid, bukan untuk menyembunyikan kegagalan.
- Gunakan `Validated` ketika beberapa kesalahan validasi perlu dikumpulkan.
- Gunakan exception untuk kondisi luar biasa sesuai kontrak komponen.
- Jangan menangkap exception tanpa menanganinya secara bermakna.
- Jangan mengganti kegagalan dengan hasil kosong, nol, atau default tanpa dasar domain yang jelas.
- Action memastikan kegagalan tahap penting tidak menyebabkan tahap berikutnya dijalankan secara keliru.
- Repository membedakan ketiadaan data, hasil kosong, dan kegagalan penyimpanan apabila penting bagi pemanggil.
- Controller memetakan hasil aplikasi ke status dan respons HTTP sesuai kontrak eksternal.
- Jangan mengekspos detail exception internal atau stack trace melalui respons HTTP.

## 11. Testing Standards

Gunakan PHPUnit untuk unit test, integration test, dan regression test.

### 11.1 Controller Tests

Uji validasi request, konversi input, pemanggilan Action, format respons, dan pemetaan kegagalan ke HTTP.

Jangan menempatkan test algoritma domain di Controller test.

### 11.2 Action Tests

Uji orkestrasi operasi aplikasi, urutan pemanggilan komponen jika relevan, penanganan hasil sukses dan gagal, penghentian alur, serta kontrak output.

### 11.3 Repository Tests

Uji query, pemetaan hasil penyimpanan, konversi tipe, persistensi, perilaku data tidak ditemukan, dan penanganan kegagalan penyimpanan.

Gunakan integration test untuk memverifikasi perilaku database sesungguhnya apabila diperlukan.

### 11.4 Model Tests

Uji casting, relasi, dan perilaku teknis Model apabila perilaku tersebut memerlukan verifikasi tersendiri.

Jangan membuat test hanya untuk membuktikan bahwa kelas Model yang tidak memiliki perilaku tambahan dapat diinstansiasi.

### 11.5 Optional Component Tests

- **Data** — uji validasi, invariants, dan value semantics apabila memiliki perilaku bermakna.
- **Config** — uji validasi atau pemetaan konfigurasi apabila diperlukan.
- **Rules** — uji keputusan, kondisi batas, invariants, dan kombinasi aturan.
- **Process** — uji algoritma, transformasi, percabangan, determinisme, dan penanganan hasil yang relevan.

Tidak perlu membuat test kelas terpisah untuk setiap komponen jika perilakunya sudah teruji secara memadai pada level yang tepat.

### 11.6 Exact and Gauss Tests

- Uji penggunaan Exact berdasarkan kontrak dan semantik API yang benar-benar tersedia.
- Uji perhitungan Gauss terhadap nilai acuan yang dapat dipercaya.
- Gunakan toleransi numerik yang sesuai untuk floating-point.
- Verifikasi presisi dan perilaku batas ketika presisi tinggi dibutuhkan.
- Jangan mengasumsikan kesamaan eksak untuk hasil floating-point tanpa dasar.

### 11.7 Strategy and Backtest Tests

Uji keputusan Strategy, data pemanasan, urutan candle, determinisme, eksekusi order, SL/TP, perhitungan performa, spread, slippage, partial fill, stress test, dan regresi yang relevan dengan perubahan.

### 11.8 General Testing Rules

- Gunakan data deterministik.
- Pisahkan unit test dari integration test sesuai kebutuhan.
- Tambahkan regression test untuk bug yang dapat direproduksi.
- Jangan melemahkan test agar implementasi tampak berhasil.
- Utamakan perilaku dan kontrak publik daripada detail implementasi yang tidak relevan.
- Jalankan test yang relevan dan seluruh test suite jika memungkinkan.
- Laporkan test yang dijalankan, hasil aktual, kegagalan, dan test yang belum dijalankan.

## 12. General PHP Standards

- Gunakan PHP 8.2+ dan `declare(strict_types=1);`.
- Gunakan deklarasi tipe eksplisit.
- Hindari `mixed` jika tipe yang lebih tepat dapat dinyatakan.
- Pilih nama Action berdasarkan tujuan operasi aplikasi.
- Pilih nama Repository berdasarkan tanggung jawab akses data.
- Pilih nama Data berdasarkan informasi yang direpresentasikan.
- Pilih nama Rules berdasarkan aturan atau kebijakan domain.
- Pilih nama Process berdasarkan tugas atau kapabilitas yang dikerjakan.
- Hindari duplikasi aturan domain.
- Jangan membuat Service atau Use Case terpisah.
- Jangan membuat abstraksi hanya demi mengikuti pola desain.
- Pertahankan penanganan error, perubahan state, dan urutan operasi agar mudah ditelusuri.
- Gunakan metode privat untuk memisahkan detail implementasi apabila itu sudah cukup.
- Buat kelas baru ketika pemisahan memberi manfaat nyata bagi tanggung jawab, pengujian, atau pemeliharaan.

### 12.1 Avoid God Function, God Method, and God Class

- Setiap fungsi dan metode memiliki tanggung jawab yang jelas.
- Action tidak mengambil alih algoritma kompleks yang semestinya berada dalam Process.
- Process tidak menggabungkan kapabilitas yang tidak berkaitan.
- Repository tidak menjadi tempat penampungan business logic.
- Controller tidak menjadi tempat logika aplikasi.
- Jangan mengukur kualitas hanya berdasarkan panjang kode, jumlah metode, atau jumlah kelas.
- Algoritma panjang dapat dipertahankan jika koheren dan lebih mudah dipahami sebagai satu unit.
- Pecah metode ketika pemisahan memperjelas tanggung jawab, meningkatkan testability, atau memudahkan pemeliharaan.
- Hindari metode privat terlalu kecil yang tidak memberikan makna tambahan.
- Jangan memindahkan tanggung jawab hanya untuk memperpendek fungsi.
- Pertahankan alur utama agar dapat dipahami tanpa terlalu banyak lapisan delegasi.

### 12.2 Avoid Overengineering

- Gunakan metode privat jika pemisahan hanya dibutuhkan untuk memperjelas implementasi dalam satu kelas.
- Buat Process ketika ada tugas atau kapabilitas bermakna dan mandiri.
- Buat Rules ketika aturan mempunyai tanggung jawab yang layak dipisahkan.
- Buat Data ketika kontrak data memperoleh manfaat dari representasi eksplisit.
- Gunakan Config ketika parameter memang perlu dikonfigurasi.
- Buat Action ketika ada operasi aplikasi dengan tujuan dan kontrak yang jelas.
- Buat Repository ketika ada tanggung jawab akses data yang nyata.
- Jangan membuat kelas untuk setiap baris atau langkah mekanis.
- Jangan membuat interface, base class, atau abstraksi generik tanpa kebutuhan konkret.
- Hindari siklus dependensi antarkomponen dan antar-feature.
- Jika dependensi dua arah muncul, evaluasi kembali batas tanggung jawabnya.
- Jangan memaksakan setiap operasi melewati seluruh lapisan.

### 12.3 Readability

- `execute()` pada Action memperlihatkan alur utama operasi aplikasi.
- Entry point Process memperlihatkan alur utama tugasnya.
- Nama kelas, variabel, dan metode mencerminkan makna domain.
- Hindari nesting berlebihan dan duplikasi aturan.
- Pertahankan kontrak tipe dan batas konversi secara konsisten.
- Pilih implementasi yang mudah dipahami tanpa mengorbankan correctness.

## 13. Development Procedure

1. Periksa struktur proyek dan konvensi yang sudah berlaku.
2. Identifikasi feature yang menjadi tanggung jawab perubahan.
3. Periksa struktur internal feature tersebut sebelum menambah atau memindahkan kelas.
4. Identifikasi kebutuhan, kontrak input-output, dan aturan domain.
5. Tentukan operasi aplikasi yang menjadi tanggung jawab Action.
6. Tentukan apakah Rules, Process, Data, dan Config diperlukan berdasarkan tanggung jawab nyata.
7. Tentukan Repository yang diperlukan untuk akses penyimpanan.
8. Pertahankan Model sesuai tanggung jawab representasi penyimpanan.
9. Periksa `EXACT.MD` pada root library Exact sebelum menggunakan atau mengubah API Exact yang kompleks.
10. Periksa `GAUSS.MD` pada root library Gauss sebelum menggunakan atau mengubah API Gauss yang kompleks.
11. Verifikasi API terhadap kode library dan versi dependensi terpasang.
12. Implementasikan perubahan sesuai struktur dan konvensi internal feature.
13. Tambahkan atau perbarui test pada perilaku yang berubah.
14. Periksa konsistensi internal feature, dependensi lintas feature, dan potensi duplikasi.
15. Jalankan test serta pemeriksaan kualitas yang tersedia.
16. Periksa regresi, kontrak publik, dan dampak lintas feature.
17. Laporkan perubahan, test yang dijalankan, hasil aktual, dan keterbatasan yang diketahui.

Jangan merombak struktur feature lain hanya demi keseragaman visual. Lakukan perubahan lintas feature jika memang diperlukan untuk menjaga kontrak, konsistensi tanggung jawab, atau mencegah dependensi yang keliru.

Jangan menyalin dokumentasi khusus AI Exact atau Gauss ke root proyek aplikasi.

## 14. Decision Rules

| Pertanyaan | Komponen |
|---|---|
| Bagaimana request HTTP diterima dan respons dikirim? | Controller |
| Operasi aplikasi apa yang harus dijalankan? | Action |
| Bagaimana urutan beberapa langkah aplikasi diatur? | Action |
| Bagaimana tugas atau kapabilitas tertentu dilaksanakan? | Process |
| Bagaimana aturan atau kriteria domain diterapkan? | Rules |
| Informasi apa yang harus dibawa atau dihasilkan? | Data |
| Parameter apa yang perlu dikonfigurasi? | Config |
| Bagaimana data dibaca atau disimpan? | Repository |
| Bagaimana data penyimpanan direpresentasikan oleh Eloquent? | Model |
| Bagaimana tipe domain dan koleksi internal dimodelkan? | Exact |
| Bagaimana komputasi matematika dilakukan? | Gauss |
| Bagaimana perilaku diverifikasi dan regresi dicegah? | PHPUnit |

Aturan keputusan:

- Jika kode menangani HTTP, gunakan Controller.
- Jika kode mengatur operasi aplikasi, gunakan Action.
- Jika kode menerapkan aturan domain yang mandiri, pertimbangkan Rules.
- Jika kode melaksanakan tugas atau kapabilitas yang mandiri, pertimbangkan Process.
- Jika kode hanya membawa informasi, gunakan Data atau tipe yang sesuai.
- Jika kode menyediakan parameter yang perlu dikonfigurasi, gunakan Config.
- Jika kode mengakses penyimpanan, gunakan Repository.
- Jika kode merepresentasikan data penyimpanan, gunakan Model.
- Jika proses tidak membutuhkan akses data, jangan menambahkan Repository hanya demi konsistensi struktur.
- Jika Action hanya memiliki satu tugas sederhana, Process terpisah tidak wajib.
- Jika aturan sederhana masih jelas di dalam Action atau Process, Rules terpisah tidak wajib.
- Jika tipe yang tersedia sudah menyatakan kontrak data dengan jelas, Data terpisah tidak wajib.
- Jika konfigurasi tidak mempunyai kebutuhan khusus, Config terpisah tidak wajib.

## 15. Priority Order

Prioritas pengembangan:

1. Correctness dan ketepatan aturan domain.
2. Kontrak tipe eksplisit.
3. Pemisahan tanggung jawab yang jelas.
4. Konsistensi internal feature.
5. Testability dan pencegahan regresi.
6. Composability dan maintainability.
7. Keterbacaan alur serta keterlacakan error.
8. Kesederhanaan implementasi.
9. Efisiensi yang relevan dan dapat dibuktikan.

Konsistensi struktur penting, tetapi tidak boleh dicapai dengan memaksakan komponen yang tidak diperlukan.

## 16. Core Principles Summary

- **Controller** — menangani transport HTTP dan meneruskan operasi ke Action.
- **Action** — mengorkestrasi satu operasi aplikasi.
- **Repository** — mengakses, memetakan, dan mempersistenkan data.
- **Model** — merepresentasikan data penyimpanan dengan pendekatan Anemic Model.
- **Data** — merepresentasikan informasi dengan kontrak eksplisit jika diperlukan.
- **Config** — menyediakan parameter dan pengaturan jika diperlukan.
- **Rules** — mengisolasi aturan atau kriteria domain jika diperlukan.
- **Process** — melaksanakan tugas atau kapabilitas tertentu jika diperlukan.
- **Strategy** — mendefinisikan perilaku strategi trading sesuai kontrak Engine.
- **Exact** — menyediakan pemodelan tipe dan struktur data internal.
- **Gauss** — menyediakan komputasi matematika yang relevan.
- **PHPUnit** — memverifikasi perilaku dan mencegah regresi.

**Fondasi arsitektur harus seragam; struktur internal harus konsisten per feature; kompleksitas antar-feature boleh berbeda.**

Setiap feature harus memiliki struktur yang terprediksi dan bebas anomali. Namun, feature sederhana tidak perlu meniru struktur feature kompleks. Tambahkan komponen hanya ketika tanggung jawabnya nyata, pertahankan batas yang jelas, dan hindari abstraksi yang tidak diperlukan.