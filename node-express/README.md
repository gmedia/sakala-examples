# Contoh Node.js dengan Express

Aplikasi Express kecil dengan satu halaman HTML di `/` dan endpoint `/healthz` yang menjawab `{"status":"ok"}`. Aplikasi mendengarkan port dari environment variable `PORT`, yang diisi Sakala.

## Setelan di Sakala

| Setelan               | Isi            |
| --------------------- | -------------- |
| Root directory        | `node-express` |
| Build command         | kosongkan      |
| Start command         | kosongkan      |
| Release command       | kosongkan      |
| Environment variables | tidak perlu    |

Railpack membaca `package.json`: versi Node dari `engines` (22 atau lebih baru), dependency dari `package-lock.json` lewat `npm ci`, lalu menjalankan `npm start`.

## Menjalankan di laptop

Butuh Node.js 22 atau lebih baru.

```bash
npm ci
npm start          # http://localhost:3000
npm test           # test dengan node:test, tanpa dependency tambahan
```

Ubah halamannya di `src/app.js`. `src/server.js` hanya menyalakan server dan menutupnya dengan rapi saat container dihentikan.

## English

A minimal Express app: an HTML page at `/` and `/healthz`. It listens on `PORT`, which Sakala sets. Set **Root directory** to `node-express` and leave the other settings empty. Run it locally with `npm ci && npm start`, and test it with `npm test`.
