# 🚀 Backend BeritaIn API

**Tulang Punggung Andal untuk Aplikasi Agregator Berita Modern "BeritaIn"**

-----

Selamat datang di dokumentasi resmi untuk **Backend BeritaIn API**. Proyek ini merupakan tulang punggung dari aplikasi berita "BeritaIn", yang menyediakan layanan API (Application Programming Interface) yang andal dan terstruktur. Backend ini dirancang untuk mengelola seluruh aspek operasional aplikasi, mulai dari otentikasi pengguna yang aman, agregasi konten dari berbagai sumber berita nasional, hingga fitur interaksi pengguna yang kaya dan panel administrasi yang komprehensif.

Dibangun dengan **Laravel 11**, proyek ini memanfaatkan kekuatan dan keanggunan salah satu framework PHP paling populer untuk menghadirkan aplikasi yang cepat, aman, dan mudah dikelola.

## 🎯 Filosofi Proyek

Tujuan utama dari **BeritaIn** adalah menyediakan platform berita yang terpusat, di mana pengguna dapat dengan mudah mengakses berita dari berbagai sumber terpercaya di Indonesia tanpa harus berpindah-pindah aplikasi. Backend ini dirancang untuk menjadi *scalable* dan efisien, memastikan pengalaman pengguna yang lancar dan responsif.

-----

## ✨ Fitur Utama

  - 🔐 **Otentikasi Pengguna yang Aman**: Menggunakan Laravel Sanctum untuk sistem otentikasi berbasis token yang modern dan aman, mencakup registrasi, login, dan logout.

  - 👤 **Manajemen Profil Pengguna**: Memberikan keleluasaan bagi pengguna untuk melihat dan memperbarui informasi profil mereka, termasuk nama dan email.

  - 📰 **Agregator Berita RSS Cerdas**: Secara otomatis mengambil dan memproses berita terbaru dari berbagai sumber media nasional terkemuka melalui RSS Feed. Fitur ini memastikan konten selalu *up-to-date*.

  - 🗂️ **Pengorganisasian Konten**:

      - **Kategori Berita**: Semua artikel secara otomatis dikelompokkan ke dalam kategori yang relevan (misalnya, Politik, Ekonomi, Olahraga) untuk navigasi yang mudah.
      - **Penyimpanan Terstruktur**: Artikel yang diambil disimpan secara sistematis di dalam database untuk akses cepat dan efisien.

  - 💬 **Fitur Interaksi Pengguna**:

      - **Suka (Like)**: Pengguna dapat memberikan apresiasi pada artikel yang mereka nikmati.
      - **Komentar**: Ruang diskusi yang memungkinkan pengguna untuk berbagi pendapat tentang sebuah artikel.
      - **Bookmark**: Menyimpan artikel favorit untuk dibaca kembali di lain waktu.
      - **Sistem Voting Komentar**: Pengguna dapat memberikan *upvote* atau *downvote* pada komentar lain, mendorong diskusi yang berkualitas.

  - 🛠️ **Panel Administrasi Komprehensif**:

      - **Dashboard Analitik**: Menyajikan ringkasan data penting seperti total pengguna, jumlah artikel, dan aktivitas lainnya dalam satu tampilan.
      - **Manajemen Pengguna**: Admin dapat melihat, mencari, dan menghapus pengguna terdaftar.
      - **Moderasi Komentar**: Sistem untuk meninjau, menyetujui, atau menolak komentar yang masuk untuk menjaga lingkungan diskusi yang sehat.
      - **Broadcast Notifikasi Real-time**: Admin dapat mengirimkan pengumuman atau notifikasi penting ke semua pengguna secara massal dan *real-time* menggunakan Pusher.

-----

## 🛠️ Arsitektur & Teknologi

Proyek ini mengadopsi tumpukan teknologi modern untuk memastikan performa dan keamanan yang optimal.

  - **Framework**: Laravel 11
  - **Bahasa Pemrograman**: PHP 8.2+
  - **Database**: PostgreSQL (Direkomendasikan), namun dapat juga menggunakan MySQL.
  - **Otentikasi API**: Laravel Sanctum
  - **Real-time Events**: Pusher
  - **Package Manager**: Composer & NPM

-----

## ⚙️ Panduan Instalasi & Konfigurasi Lokal

Ikuti langkah-langkah detail berikut untuk menginstal, mengonfigurasi, dan menjalankan proyek ini di lingkungan pengembangan lokal Anda.

### 1\. Prasyarat

Sebelum memulai, pastikan sistem Anda telah memenuhi persyaratan berikut:

  - ✅ PHP: Versi 8.2 atau yang lebih baru.
  - ✅ Composer: Manajer paket untuk PHP.
  - ✅ Node.js & NPM: Untuk manajemen aset frontend.
  - ✅ Server Database: PostgreSQL sangat direkomendasikan.
  - ✅ Git: Untuk meng-clone repository.

### 2\. Instalasi Proyek

**a. Clone Repository**
Buka terminal atau command prompt, navigasikan ke direktori kerja Anda, dan jalankan perintah berikut:

```bash
git clone https://github.com/rafifpermana/backend-beritain.git
cd backend-beritain
```

**b. Instal Dependensi**
Instal semua pustaka PHP yang diperlukan melalui Composer.

```bash
composer install
```

Kemudian, instal dependensi JavaScript menggunakan NPM.

```bash
npm install
```

**c. Konfigurasi Environment**
Langkah ini krusial. Salin file konfigurasi contoh `.env.example` menjadi file `.env` yang akan digunakan oleh aplikasi.

```bash
cp .env.example .env
```

> **Penting:** File `.env` bersifat sensitif dan tidak boleh dimasukkan ke dalam sistem kontrol versi (sudah diatur di `.gitignore`).

**d. Generate Application Key**
Laravel menggunakan kunci ini untuk mengenkripsi data. Pastikan untuk men-generate kunci unik untuk aplikasi Anda.

```bash
php artisan key:generate
```

### 3\. Konfigurasi File `.env`

Buka file `.env` dengan editor teks favorit Anda dan sesuaikan variabel-variabel di bawah ini.

**a. Konfigurasi Aplikasi Utama**

```ini
APP_NAME="BeritaIn"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
```

  - `APP_ENV=local`: Mengaktifkan mode pengembangan, yang akan menampilkan pesan error secara detail.
  - `APP_DEBUG=true`: Sangat membantu untuk debugging. **Jangan pernah set `true` di lingkungan produksi\!**

**b. Konfigurasi Koneksi Database (PostgreSQL)**
Buat sebuah database baru di server PostgreSQL Anda, lalu isikan kredensialnya di sini.

```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=beritain_db
DB_USERNAME=postgres
DB_PASSWORD=password
```

> Ganti `beritain_db`, `postgres`, dan `password` dengan konfigurasi yang sesuai dengan setup PostgreSQL Anda.

**c. Konfigurasi Layanan Broadcast (Pusher)**
Untuk fitur notifikasi real-time, Anda memerlukan akun Pusher. Dapatkan kredensial dari dashboard [pusher.com](https://pusher.com).

```ini
BROADCAST_DRIVER=pusher

PUSHER_APP_ID=isi_dengan_app_id_pusher
PUSHER_APP_KEY=isi_dengan_app_key_pusher
PUSHER_APP_SECRET=isi_dengan_app_secret_pusher
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=ap1
```

> Kredensial ini sangat penting agar fitur broadcast notifikasi dari panel admin dapat berfungsi.

**d. Konfigurasi Sumber Berita (RSS Feed)**
Bagian ini mendefinisikan URL RSS Feed yang menjadi sumber konten aplikasi.

```ini
# CNN Indonesia
RSS_CNN_NAME="CNN Indonesia"
RSS_CNN_ALL="https://www.cnnindonesia.com/rss"
RSS_CNN_NASIONAL="https://www.cnnindonesia.com/nasional/rss"
# ... (dan seterusnya untuk CNN)

# CNBC Indonesia
RSS_CNBC_NAME="CNBC Indonesia"
RSS_CNBC_ALL="https://www.cnbcindonesia.com/news/rss"
# ... (dan seterusnya untuk CNBC)

# Antara News
RSS_ANTARA_NAME="Antara News"
RSS_ANTARA_ALL="https://www.antaranews.com/rss/terkini.xml"
# ... (dan seterusnya untuk Antara)

# Tempo.co
RSS_TEMPO_NAME="Tempo.co"
RSS_TEMPO_ALL="https://rss.tempo.co/"
# ... (dan seterusnya untuk Tempo)
```

### 4\. Migrasi dan Seeding Database

Setelah semua konfigurasi benar, saatnya mempersiapkan database.

**a. Jalankan Migrasi**
Perintah ini akan membuat semua tabel (Users, Articles, Comments, dll.) sesuai dengan skema yang didefinisikan.

```bash
php artisan migrate
```

**b. Jalankan Seeder**
Perintah ini akan mengisi database dengan data awal yang penting, seperti kategori berita dan akun admin default.

```bash
php artisan db:seed
```

> **Akun Admin Default:**
>
>   - **Email**: `admin@beritain.com`
>   - **Password**: `password`

### 5\. Menjalankan Server Lokal

Sekarang aplikasi Anda siap dijalankan.

**a. Jalankan Server API**
Gunakan perintah `serve` dari Artisan untuk memulai server pengembangan PHP.

```bash
php artisan serve
```

Secara default, API Anda akan dapat diakses di `http://localhost:8000`.

**b. Jalankan Vite (Untuk Frontend)**
Jika ada pengembangan frontend, jalankan server Vite di terminal terpisah.

```bash
npm run dev
```

-----

## 🗺️ Dokumentasi Endpoint API

API ini mengikuti standar RESTful. Semua respons dikembalikan dalam format JSON.

**Prefix Global**: `/api`

### Otentikasi

  - `POST /register`: Mendaftarkan pengguna baru.
  - `POST /login`: Mengotentikasi pengguna dan mengembalikan token API.
  - `POST /logout`: Menghapus token otentikasi pengguna (memerlukan token).

### Pengguna (Terotentikasi)

  - `GET /user`: Mendapatkan detail profil pengguna yang sedang login.
  - `PUT /user`: Memperbarui detail profil pengguna.

### Konten & Artikel

  - `GET /articles`: Mendapatkan daftar semua artikel dengan paginasi.
  - `GET /articles/{id}`: Mendapatkan detail spesifik dari satu artikel.
  - `GET /categories`: Mendapatkan daftar semua kategori berita yang tersedia.
  - `GET /categories/{id}`: Mendapatkan semua artikel dalam kategori tertentu.
  - `GET /rss-news`: Endpoint untuk mengambil berita terbaru dari semua sumber RSS.

### Interaksi (Terotentikasi)

  - `POST /articles/{id}/like`: Memberikan atau menarik suka pada sebuah artikel.
  - `POST /articles/{id}/bookmark`: Menambah atau menghapus artikel dari daftar bookmark.
  - `GET /bookmarks`: Mendapatkan daftar artikel yang telah di-bookmark oleh pengguna.
  - `POST /articles/{id}/comments`: Mengirim komentar baru pada sebuah artikel.
  - `GET /articles/{id}/comments`: Melihat semua komentar pada sebuah artikel.
  - `POST /comments/{id}/vote`: Memberikan upvote/downvote pada sebuah komentar.

### Admin (Memerlukan Role Admin)

  - `GET /admin/dashboard`: Mendapatkan data agregat untuk dashboard.
  - `GET /admin/users`: Mendapatkan daftar semua pengguna terdaftar.
  - `DELETE /admin/users/{id}`: Menghapus pengguna dari sistem.
  - `GET /admin/comments`: Melihat daftar komentar yang menunggu moderasi.
  - `POST /admin/comments/{id}/approve`: Menyetujui sebuah komentar.
  - `POST /admin/comments/{id}/reject`: Menolak dan menghapus sebuah komentar.
  - `POST /admin/broadcast`: Mengirim notifikasi ke semua pengguna.
