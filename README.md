# Contoh aplikasi untuk Sakala

Repository ini berisi lima aplikasi kecil yang bisa langsung di-deploy di [Sakala](https://sakala.dev). Setiap contoh ada di foldernya sendiri, jadi satu repository ini dipakai untuk lima project Sakala yang berbeda lewat setelan **Root directory**, yaitu folder yang Sakala pakai sebagai tempat build.

Console Sakala memakai repository ini untuk fitur "Mulai dari repo contoh". Kamu juga bisa membuat project-nya sendiri dari URL repository ini, atau fork dulu supaya bisa mengubah isinya.

| Folder                         | Yang ditunjukkan                                                                     | Root directory | Release command               | Environment variable wajib |
| ------------------------------ | ------------------------------------------------------------------------------------ | -------------- | ----------------------------- | -------------------------- |
| [`static-site/`](static-site/) | Situs statis satu halaman tanpa langkah build, disajikan Caddy                       | `static-site`  | kosongkan                     | tidak ada                  |
| [`node-express/`](node-express/) | Aplikasi Express yang mendengarkan `PORT`, dengan endpoint `/healthz`              | `node-express` | kosongkan                     | tidak ada                  |
| [`node-s3/`](node-s3/)         | Upload berkas ke bucket terkelola lewat API S3, dibuka dengan link sementara         | `node-s3`      | kosongkan                     | tidak ada, diisi Sakala dari bucket |
| [`laravel/`](laravel/)         | Buku tamu Laravel dengan PostgreSQL dan migration lewat perintah rilis               | `laravel`      | `php artisan migrate --force` | `APP_KEY`                  |
| [`laravel-queue/`](laravel-queue/) | Laravel dengan worker antrean, jadwal, dan Valkey opsional                      | `laravel-queue` | `php artisan migrate --force` | `APP_KEY`                  |

Alamat repository untuk Console: `https://github.com/gmedia/sakala-examples`, branch `main`.

## Cara men-deploy salah satu contoh

1. Di Console Sakala, buat project baru dari repository `https://github.com/gmedia/sakala-examples`.
2. Di langkah **Konfigurasi**, isi **Root directory** dengan nama folder contohnya, misalnya `node-express`. Tanpa setelan ini Sakala membangun root repository, yang bukan aplikasi.
3. Biarkan **Build command** dan **Start command** kosong. Untuk `static-site`, `node-express`, dan `node-s3`, Railpack, yang membangun aplikasi tanpa Dockerfile, sudah tahu cara membangun dan menjalankannya. `laravel` dan `laravel-queue` dibangun dari `Dockerfile` di foldernya masing-masing.
4. Untuk `laravel` dan `laravel-queue`, isi juga **Release command** dan `APP_KEY`. Detailnya ada di [`laravel/README.md`](laravel/README.md). `laravel-queue` juga butuh proses worker dan jadwal, lihat [`laravel-queue/README.md`](laravel-queue/README.md).
5. Tekan **Buat Proyek**, lalu **Deploy sekarang**.

Contoh Laravel tetap jalan walau belum ada database. Untuk mengaktifkan buku tamunya, buat database di halaman **Database** Console, pasang ke project itu, lalu redeploy: Sakala mengisi `DATABASE_URL` dan `DB_*` sendiri. PostgreSQL di layanan lain tetap bisa dipakai lewat `DATABASE_URL`.

Contoh `node-s3` juga jalan tanpa bucket. Untuk mencobanya, buat bucket di halaman **Bucket** Console, pasang ke project itu, lalu redeploy: Sakala mengisi variabel `AWS_*` sendiri. Bucket terkelola masih pilot dan butuh kuota workspace, detailnya ada di [`node-s3/README.md`](node-s3/README.md).

Contoh `laravel-queue` menambahkan worker `php artisan queue:work` di bagian **Proses** dan jadwal `php artisan reports:cleanup` di bagian **Jadwal**. Antreannya memakai Valkey kalau Valkey dipasang, dan tabel `jobs` di database kalau tidak.

## Dokumentasi

- [Deploy pertamamu](https://sakala.dev/docs/deploy-pertama)
- [Men-deploy monorepo](https://sakala.dev/docs/monorepo), untuk penjelasan Root directory
- [Database dan perintah rilis](https://sakala.dev/docs/perintah-rilis)
- [Bucket terkelola](https://sakala.dev/docs/bucket-terkelola)
- [Saat deploy gagal](https://sakala.dev/docs/deploy-gagal)

## Menjalankan di laptop

Setiap folder punya README dengan perintah untuk menjalankan dan mengetesnya di laptop. CI di `.github/workflows/ci.yml` menjalankan cek yang sama untuk setiap pull request.

## English

This repository holds five small apps that deploy on [Sakala](https://sakala.dev). Each one lives in its own folder, so set **Root directory** to the folder name when you create the project: `static-site`, `node-express`, `node-s3`, `laravel`, or `laravel-queue`. Leave the build and start commands empty: Railpack detects the static site and the two Node apps, and both Laravel apps build from their own `Dockerfile`.

The Laravel example needs `APP_KEY` (generate it with `php artisan key:generate --show` and save it as a secret) and the release command `php artisan migrate --force`. It starts without a database. To enable the guestbook, create a database on the Console's **Database** page, attach it to the project, and redeploy; Sakala injects `DATABASE_URL` and `DB_*`. An external PostgreSQL still works through `DATABASE_URL`. The `node-s3` example stores uploads in a managed bucket: attach one on the Console's **Bucket** page and redeploy; Sakala injects the `AWS_*` variables. The `laravel-queue` example adds a queue worker (`php artisan queue:work`) under **Processes** and a schedule (`php artisan reports:cleanup`) under **Schedules**; its queue uses an attached Valkey store, or the database's `jobs` table without one. See the [Sakala docs](https://sakala.dev/docs/) for details.

## Lisensi

[Apache License 2.0](LICENSE).
