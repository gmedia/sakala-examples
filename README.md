# Contoh aplikasi untuk Sakala

Repository ini berisi tiga aplikasi kecil yang bisa langsung di-deploy di [Sakala](https://sakala.dev). Setiap contoh ada di foldernya sendiri, jadi satu repository ini dipakai untuk tiga project Sakala yang berbeda lewat setelan **Root directory**, yaitu folder yang Sakala pakai sebagai tempat build.

Console Sakala memakai repository ini untuk fitur "Mulai dari repo contoh". Kamu juga bisa membuat project-nya sendiri dari URL repository ini, atau fork dulu supaya bisa mengubah isinya.

| Folder                         | Yang ditunjukkan                                                                     | Root directory | Release command               | Environment variable wajib |
| ------------------------------ | ------------------------------------------------------------------------------------ | -------------- | ----------------------------- | -------------------------- |
| [`static-site/`](static-site/) | Situs statis satu halaman tanpa langkah build, disajikan Caddy                       | `static-site`  | kosongkan                     | tidak ada                  |
| [`node-express/`](node-express/) | Aplikasi Express yang mendengarkan `PORT`, dengan endpoint `/healthz`              | `node-express` | kosongkan                     | tidak ada                  |
| [`laravel/`](laravel/)         | Buku tamu Laravel dengan PostgreSQL dan migration lewat perintah rilis               | `laravel`      | `php artisan migrate --force` | `APP_KEY`                  |

Alamat repository untuk Console: `https://github.com/gmedia/sakala-examples`, branch `main`.

## Cara men-deploy salah satu contoh

1. Di Console Sakala, buat project baru dari repository `https://github.com/gmedia/sakala-examples`.
2. Di langkah **Konfigurasi**, isi **Root directory** dengan nama folder contohnya, misalnya `node-express`. Tanpa setelan ini Sakala membangun root repository, yang bukan aplikasi.
3. Biarkan **Build command** dan **Start command** kosong. Railpack, yang membangun aplikasi tanpa Dockerfile, sudah tahu cara membangun dan menjalankan ketiga contoh ini.
4. Untuk `laravel`, isi juga **Release command** dan `APP_KEY`. Detailnya ada di [`laravel/README.md`](laravel/README.md).
5. Tekan **Buat Proyek**, lalu **Deploy sekarang**.

Contoh Laravel tetap jalan walau belum ada database. Halamannya menjelaskan cara menghubungkan PostgreSQL, karena Sakala belum menyediakan database terkelola.

## Dokumentasi

- [Deploy pertamamu](https://sakala.dev/docs/deploy-pertama)
- [Men-deploy monorepo](https://sakala.dev/docs/monorepo), untuk penjelasan Root directory
- [Database dan perintah rilis](https://sakala.dev/docs/perintah-rilis)
- [Saat deploy gagal](https://sakala.dev/docs/deploy-gagal)

## Menjalankan di laptop

Setiap folder punya README dengan perintah untuk menjalankan dan mengetesnya di laptop. CI di `.github/workflows/ci.yml` menjalankan cek yang sama untuk setiap pull request.

## English

This repository holds three small apps that deploy on [Sakala](https://sakala.dev). Each one lives in its own folder, so set **Root directory** to the folder name when you create the project: `static-site`, `node-express`, or `laravel`. Leave the build and start commands empty; Railpack detects each app.

The Laravel example needs `APP_KEY` (generate it with `php artisan key:generate --show` and save it as a secret) and the release command `php artisan migrate --force`. It starts without a database and shows how to connect PostgreSQL through `DATABASE_URL`. See the [Sakala docs](https://sakala.dev/docs/) for details.

## Lisensi

[Apache License 2.0](LICENSE).
