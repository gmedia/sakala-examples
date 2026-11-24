# Contoh Node.js dengan bucket terkelola

Aplikasi Express kecil yang menyimpan upload di [bucket terkelola](https://sakala.dev/docs/bucket-terkelola) Sakala lewat API S3. Halaman `/` berisi form upload satu berkas dan daftar isi bucket. Setiap berkas dibuka lewat link sementara, karena bucket terkelola selalu privat.

Aplikasi memakai AWS SDK for JavaScript v3 (`@aws-sdk/client-s3`, `@aws-sdk/lib-storage`, dan `@aws-sdk/s3-request-presigner`) dan mendengarkan port dari environment variable `PORT`, yang diisi Sakala. Sebelum bucket dipasang, aplikasi tetap jalan dan halaman `/` menampilkan cara memasangnya.

## Setelan di Sakala

| Setelan               | Isi                                   |
| --------------------- | ------------------------------------- |
| Root directory        | `node-s3`                             |
| Build command         | kosongkan                             |
| Start command         | kosongkan                             |
| Release command       | kosongkan                             |
| Environment variables | tidak perlu, diisi Sakala dari bucket |

Railpack membaca `package.json`: versi Node dari `engines` (22 atau lebih baru), dependency dari `package-lock.json` lewat `npm ci`, lalu menjalankan `npm start`.

## Memasang bucket

1. Deploy project-nya dulu. Halaman `/` akan bilang bucket belum dipasang.
2. Buka **Bucket** di Console, buat bucket, lalu pasang ke project ini. Bisa juga lewat **Buat bucket untuk proyek ini** di halaman project. Bucket terkelola masih pilot dan butuh kuota workspace, lihat [Meminta kuota](https://sakala.dev/docs/bucket-terkelola#meminta-kuota).
3. Tekan **Redeploy** di project ini. Variabel bucket baru diterima aplikasi di deployment berikutnya, dan redeploy ini biasanya tidak mem-build ulang.

Variabel yang dibaca aplikasi, semuanya diisi Sakala:

| Variabel                                          | Dipakai untuk                                                                               |
| ------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| `AWS_ENDPOINT_URL_S3`, cadangannya `AWS_ENDPOINT` | Alamat endpoint S3                                                                          |
| `AWS_REGION`, cadangannya `AWS_DEFAULT_REGION`    | Region untuk tanda tangan permintaan                                                        |
| `AWS_BUCKET`                                      | Nama bucket                                                                                 |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`      | Kunci bucket                                                                                |
| `AWS_USE_PATH_STYLE_ENDPOINT`                     | `true` menyalakan `forcePathStyle`, karena endpoint Sakala hanya menerima alamat path-style |

Semua opsi S3 diisi langsung di `src/storage.js`, jadi aplikasi tidak bergantung pada variabel yang dibaca SDK sendiri. Aplikasi hanya mencatat nama variabel yang belum ada, tidak pernah nilainya.

## Yang bisa dicoba

| Route                    | Isi                                                                              |
| ------------------------ | -------------------------------------------------------------------------------- |
| `GET /`                  | Form upload dan daftar berkas dengan link sementara 15 menit                     |
| `POST /upload`           | Upload dari form (`multipart/form-data`, satu berkas, paling besar 20 MB)        |
| `GET /api/files`         | `{"ok":true,"files":[{"key","size","url"}],"truncated":false}`                   |
| `POST /api/upload?name=` | Upload isi body apa adanya, menjawab `{"ok":true,"key":"..."}` dengan status 201 |
| `GET /healthz`           | `{"status":"ok"}`                                                                |

Nama berkas di bucket adalah waktu upload ditambah nama asli yang sudah dibersihkan, misalnya `2026-11-24T03-04-05-678Z-Laporan-Akhir.pdf`. `Content-Type` ditebak dari ekstensinya, jadi beri berkas ekstensi yang sesuai.

Kalau gagal, endpoint JSON menjawab `{"ok":false,"error","status","message"}`. Untuk error dari bucket, `error` adalah kode error S3 apa adanya, misalnya `NoSuchBucket`, `SignatureDoesNotMatch`, atau error kuota saat bucket penuh, dan `status` adalah status HTTP dari server penyimpanan. Respons aplikasinya sendiri berstatus 502. Error dari aplikasi memakai kode sendiri: `BUCKET_NOT_CONFIGURED` (503), `FILE_TOO_LARGE` (413), `NAME_REQUIRED` dan `EMPTY_BODY` (400).

Contoh dengan curl, ganti alamatnya dengan domain project-mu:

```bash
APP=https://domain-project-mu

curl --data-binary @foto.jpg -H 'Content-Type: application/octet-stream' "$APP/api/upload?name=foto.jpg"
curl "$APP/api/files"

# Unduh lewat link sementara dari daftar tadi.
curl -o salinan.jpg "$(curl -s "$APP/api/files" | jq -r '.files[0].url')"
```

Link sementara berlaku 15 menit. Alamat berkas tanpa tanda tangan selalu ditolak, karena bucket terkelola privat.

## Menjalankan di laptop

Butuh Node.js 22 atau lebih baru.

```bash
npm ci
npm start          # http://localhost:3000, tanpa bucket
npm test           # test dengan node:test, memakai bucket tiruan di memori
```

Untuk mencoba dengan bucket sungguhan, pemilik workspace bisa menyalin variabel dari blok **Untuk .env di laptop** di halaman bucket, lalu menjalankan `npm start` dengan variabel itu. Jangan commit kunci itu ke repository.

Kode S3 ada di `src/storage.js`, route dan halaman di `src/app.js`. `src/server.js` hanya menyalakan server dan menutupnya dengan rapi saat container dihentikan.

## English

An Express app that stores uploads in a Sakala [managed bucket](https://sakala.dev/docs/bucket-terkelola) over the S3 API with the AWS SDK for JavaScript v3. Set **Root directory** to `node-s3` and leave the other settings empty. Attach a bucket to the project in the Console, then press **Redeploy**: Sakala injects `AWS_ENDPOINT_URL_S3`, `AWS_REGION`, `AWS_BUCKET`, the key pair, and `AWS_USE_PATH_STYLE_ENDPOINT=true`. The bucket is private, so files open through presigned links that last 15 minutes. Without a bucket the app still starts and `/` explains how to attach one. For scripts, use `GET /api/files` and `POST /api/upload?name=...` with a raw body (20 MB max). Run it locally with `npm ci && npm start`, and test it with `npm test`.
