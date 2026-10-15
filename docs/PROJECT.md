# Nikaido — Project Overview

## 1. Tentang Proyek

Nikaido adalah proyek PHP untuk membangun aplikasi yang memiliki domain dan alur bisnis terstruktur, dengan pemisahan tanggung jawab yang jelas antara akses data, operasi domain, orkestrasi proses, dan antarmuka aplikasi.

Proyek ini menggunakan Laravel sebagai fondasi aplikasi dan memanfaatkan library Exact serta Gauss untuk mendukung pemodelan domain yang eksplisit dan operasi matematika yang terstruktur.

Pengembangan Nikaido berorientasi pada correctness, maintainability, composability, dan kesederhanaan arsitektur. Abstraksi hanya ditambahkan ketika memberikan manfaat nyata terhadap kebutuhan proyek.

## 2. Tujuan

- Membangun aplikasi dengan struktur kode yang mudah dipahami dan dipelihara.
- Memisahkan akses database dari business logic dan alur aplikasi.
- Menggunakan tipe domain yang eksplisit untuk mengurangi state ambigu dan kesalahan representasi data.
- Menggunakan operasi matematika yang sesuai dengan kebutuhan domain tanpa mengimplementasikan ulang fungsi yang sudah tersedia.
- Memastikan fitur dan perbaikan bug memiliki test yang relevan.
- Menghindari overengineering, abstraksi prematur, dan dependensi yang tidak diperlukan.