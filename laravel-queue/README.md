# Contoh Laravel dengan antrean, worker, dan jadwal

Aplikasi Laravel 13 kecil yang menunjukkan tiga proses dari satu image:

- **Web** menampilkan formulir **Buat laporan**. Laporan disimpan ke PostgreSQL, lalu job `GenerateReport` masuk antrean.
- **Worker** menjalankan `php artisan queue:work`, mengambil job itu, lalu menandai laporan selesai beserta koneksi antrean yang membawanya.
- **Jadwal** menjalankan `php artisan reports:cleanup` di container sekali jalan. Perintah ini menghapus laporan yang lebih tua dari 7 hari dan mencatat waktunya.

Halaman utama menampilkan koneksi antrean yang dipakai, job terakhir yang selesai, dan waktu jadwal terakhir berjalan.

Antrean memakai Valkey kalau Valkey terpasang ke project. Tanpa Valkey, job menunggu di tabel `jobs` di database, jadi contoh ini tetap jalan dengan database saja.

## Setelan di Sakala

| Setelan         | Isi                           |
| --------------- | ----------------------------- |
| Root directory  | `laravel-queue`               |
| Build command   | kosongkan                     |
| Start command   | kosongkan                     |
| Release command | `php artisan migrate --force` |

Environment variables:

| Nama      | Wajib | Isinya                                                                                                                                            |
| --------- | ----- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| `APP_KEY` | ya    | Kunci enkripsi Laravel. Buat dengan `php artisan key:generate --show` di laptop, lalu simpan sebagai rahasia. Jangan pernah commit kunci sungguhan. |

Variabel database dan Valkey tidak perlu diisi sendiri. Sakala mengisinya setiap deploy dari database dan Valkey yang terpasang.

## Langkah memasang

1. Buat project dengan setelan di atas, lalu deploy. Tanpa database, halaman menjelaskan cara memasangnya.
2. **Database.** Buka halaman **Database** di Console, buat database, lalu **Pasang ke proyek** ini. Sakala mengisi `DATABASE_URL` dan `DB_*`.
3. **Valkey, opsional.** Buka halaman **Valkey**, buat Valkey dengan mode **Persisten**, lalu pasang ke project ini. Mode **Cache** boleh membuang data saat memori penuh, termasuk job yang belum jalan, jadi tidak cocok untuk antrean. Sakala mengisi `REDIS_URL`, `REDIS_HOST`, `REDIS_PORT`, dan `REDIS_PASSWORD`. Valkey masih pilot dan butuh kuota workspace.
4. **Worker.** Di **Pengaturan**, bagian **Proses**, tambahkan proses `worker` dengan perintah:

   ```
   php artisan queue:work --sleep=3 --tries=3 --max-time=3600
   ```

   `--max-time=3600` membuat worker keluar dengan rapi setiap jam lalu dinyalakan ulang, supaya memori tidak menumpuk.
5. **Jadwal.** Di **Pengaturan**, bagian **Jadwal**, tambahkan jadwal dengan cron `*/15 * * * *` dan perintah:

   ```
   php artisan reports:cleanup
   ```

   **Jalankan sekarang** di halaman jadwal itu untuk mencobanya tanpa menunggu.
6. Tekan **Redeploy**. Release command membuat tabel, web dan worker menyala dari image yang sama.

Setelah itu, isi judul di formulir dan tekan **Masukkan ke antrean**. Laporan tampil "menunggu worker", lalu berubah jadi "selesai lewat `redis`" (atau `database` tanpa Valkey) setelah sekitar 2 detik.

## Kenapa begini

- **Satu image untuk tiga proses.** Worker dan jadwal memakai image yang sama dengan web, hanya perintahnya yang beda. Kode antrean dan perintah jadwal ikut setiap deploy tanpa build tambahan.
- **Jadwal Sakala, bukan `schedule:run`.** Setiap run jadwal adalah container baru. Interval paling rapat di Sakala 15 menit, jadi jadwal menjalankan satu perintah artisan langsung, bukan `schedule:run` tiap menit. Perintah jadwal harus aman dijalankan ulang, karena run yang terlewat tidak dikejar.
- **Pilihan backend.** Sakala hanya mengisi variabel koneksi, tidak pernah `QUEUE_CONNECTION`, `CACHE_STORE`, atau `SESSION_DRIVER`. Aplikasi ini memilih sendiri di `app/Support/Backends.php`: dengan `REDIS_URL`, antrean, cache, dan session memakai Valkey. Tanpa itu, antrean memakai database, cache memakai berkas, dan session memakai cookie terenkripsi. Isi salah satu dari tiga variabel itu di tab **Variabel** kalau mau memilih sendiri.
- **Nomor database Valkey.** `REDIS_URL` dari Sakala tidak memuat nomor database. Laravel lalu memakai database 0 untuk antrean dan session, dan database 1 untuk cache, jadi `php artisan cache:clear` tidak menghapus job yang sedang mengantre.
- **Predis.** Koneksi ke Valkey memakai Predis dari Composer, jadi image tidak perlu mengompilasi ekstensi `redis`. Kalau kamu memasang ekstensi itu sendiri, isi `REDIS_CLIENT=phpredis`.
- **`pcntl`.** `Dockerfile` memasang `pdo_pgsql` dan `pcntl`. Dengan `pcntl`, worker menyelesaikan job yang sedang berjalan saat Sakala menghentikannya, dan `--timeout` bisa dipaksakan.
- **Tanpa database.** Tanpa variabel database, release command berjalan ke SQLite sementara di memori dan antrean memakai `sync`, jadi deploy tetap berhasil dan worker diam tanpa error.
- **Dockerfile dan health check.** Sama seperti contoh [`laravel/`](../laravel/README.md): Railpack PHP melewati batas build 600 detik, dan `HEALTHCHECK NONE` mematikan health check bawaan FrankenPHP.

## Menjalankan di laptop

Butuh PHP 8.4 atau lebih baru dengan ekstensi `pdo_sqlite` dan `pdo_pgsql`, dan Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
echo "DB_CONNECTION=sqlite" >> .env
touch database/database.sqlite
php artisan migrate
php artisan serve                       # http://localhost:8000
php artisan queue:work                  # di terminal kedua
php artisan reports:cleanup             # jalankan kapan saja
```

Untuk mencoba Valkey atau Redis lokal, tambahkan `REDIS_URL=redis://127.0.0.1:6379` di `.env`, tanpa nomor database di akhir.

Test memakai SQLite di memori:

```bash
php artisan test
vendor/bin/pint --test       # cek format kode
```

Halaman ada di `resources/views/reports.blade.php`, job di `app/Jobs/GenerateReport.php`, perintah jadwal di `app/Console/Commands/CleanupReports.php`, dan pemilihan backend di `app/Support/Backends.php`.

## English

A small Laravel 13 app with a web process, a queue worker, and a scheduled command, all from one image built from the `Dockerfile` in this folder. Set **Root directory** to `laravel-queue`, **Release command** to `php artisan migrate --force`, and `APP_KEY` as a secret. Attach a managed database. Optionally attach a Valkey store in **Persistent** mode; Sakala injects `REDIS_URL` and the app then uses it for the queue, cache, and session, otherwise jobs wait in the database's `jobs` table. Add a worker process with `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` and a schedule `*/15 * * * *` running `php artisan reports:cleanup`, then redeploy. The page shows the queue connection in use, the last finished job, and the last scheduled run.
