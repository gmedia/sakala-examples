# Contoh Laravel dengan PostgreSQL

Buku tamu kecil dengan Laravel 13: pengunjung menulis pesan, aplikasi menyimpannya ke PostgreSQL dan menampilkan 20 pesan terbaru. Tabelnya dibuat oleh satu migration yang Sakala jalankan lewat **Release command**, yaitu perintah yang berjalan sekali setiap deployment sebelum versi baru menerima trafik.

Aplikasi ini tetap jalan walau belum ada database. Tanpa variabel database, halamannya menjelaskan cara menghubungkan PostgreSQL, dan `php artisan migrate --force` tetap selesai dengan sukses karena berjalan ke database SQLite sementara di memori yang tidak menyimpan apa pun. Jadi contoh ini bisa di-deploy sebelum kamu punya database.

## Setelan di Sakala

| Setelan         | Isi                           |
| --------------- | ----------------------------- |
| Root directory  | `laravel`                     |
| Build command   | kosongkan                     |
| Start command   | kosongkan                     |
| Release command | `php artisan migrate --force` |

Environment variables:

| Nama           | Wajib                     | Isinya                                                                                                                                                       |
| -------------- | ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `APP_KEY`      | ya                        | Kunci enkripsi Laravel. Buat dengan `php artisan key:generate --show` di laptop, lalu simpan sebagai rahasia. Jangan pernah commit kunci sungguhan.             |
| `DATABASE_URL` | tidak, untuk buku tamu    | Alamat PostgreSQL, misalnya `postgres://user:password@host:5432/nama_db?sslmode=require`. Simpan sebagai rahasia. Tanpa ini aplikasi menampilkan petunjuk koneksi. |

Sebagai ganti `DATABASE_URL`, kamu bisa mengisi `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`. Begitu salah satu dari `DATABASE_URL`, `DB_URL`, atau `DB_HOST` terisi, aplikasi memakai PostgreSQL. Kalau `APP_KEY` kosong, halaman gagal dengan error 500 karena Laravel tidak bisa mengenkripsi cookie session.

`Dockerfile` mengisi `APP_ENV=production` dan `APP_DEBUG=false`, jadi detail error tidak tampil ke pengunjung.

## Kenapa begini

- **Dockerfile, bukan Railpack.** Karena ada `Dockerfile` di folder ini, Sakala memakainya. Image-nya berbasis FrankenPHP di Alpine, sekitar 66 MB, dan hanya punya delapan langkah build. Image PHP bawaan Railpack jauh lebih besar dan langkahnya lebih banyak, sehingga saat dicoba di staging Sakala build-nya melewati batas waktu build 600 detik.
- **Ekstensi PostgreSQL.** `Dockerfile` memasang `pdo_pgsql`. `pdo_sqlite` sudah ada di base image.
- **Migration hanya di perintah rilis.** Container hanya menjalankan `php artisan optimize` lalu FrankenPHP saat mulai, tanpa migration. Migration berjalan sekali per deployment lewat Release command, bukan setiap restart atau di setiap replika. Lihat [Database dan perintah rilis](https://sakala.dev/docs/perintah-rilis).
- **Health check.** `HEALTHCHECK NONE` mematikan health check bawaan image FrankenPHP, yang memeriksa port admin Caddy yang tidak dibuka di sini. Sakala sendiri memeriksa `/` di `PORT`.
- **Session di cookie.** Session disimpan di cookie terenkripsi, dan cache di berkas, supaya aplikasi tidak butuh tabel tambahan dan tetap jalan setelah redeploy.
- **HTTPS di depan aplikasi.** Sakala menerima HTTPS lalu meneruskan request ke aplikasi. `bootstrap/app.php` mempercayai header dari proxy itu supaya URL dan redirect tetap memakai https.
- **Batas kiriman.** Satu pengunjung bisa mengirim 5 pesan per menit.

## Menjalankan di laptop

Butuh PHP 8.4 atau lebih baru dengan ekstensi `pdo_sqlite` dan `pdo_pgsql`, dan Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve            # http://localhost:8000, tanpa database
```

Untuk mencoba buku tamu tanpa PostgreSQL, isi `DB_CONNECTION=sqlite` di `.env`, jalankan `touch database/database.sqlite`, lalu `php artisan migrate`.

Untuk mencoba image-nya seperti di Sakala:

```bash
docker build -t buku-tamu .
docker run --rm -e APP_KEY="$(php artisan key:generate --show)" -e PORT=8080 -p 127.0.0.1:8080:8080 buku-tamu
```

Test memakai SQLite di memori:

```bash
php artisan test
vendor/bin/pint --test       # cek format kode
```

Halaman ada di `resources/views/guestbook.blade.php`, logikanya di `app/Http/Controllers/GuestbookController.php`, dan pemilihan koneksi database di `app/Support/DatabaseConnection.php`.

## English

A small Laravel 13 guestbook backed by PostgreSQL. Sakala builds it from the `Dockerfile` in this folder. Set **Root directory** to `laravel` and **Release command** to `php artisan migrate --force`. `APP_KEY` is required: generate it with `php artisan key:generate --show` and save it as a secret. Set `DATABASE_URL` (or `DB_HOST` and friends) to enable the guestbook; without it the app still starts and explains how to connect a database, and the release command succeeds against a throwaway in-memory SQLite database.
