# Contoh situs statis

Satu halaman HTML dan satu berkas CSS, tanpa langkah build. Railpack mengenali folder ini sebagai situs statis lewat berkas `Staticfile`, lalu menyajikan isi `public/` dengan Caddy di port yang Sakala berikan.

## Setelan di Sakala

| Setelan               | Isi           |
| --------------------- | ------------- |
| Root directory        | `static-site` |
| Build command         | kosongkan     |
| Start command         | kosongkan     |
| Release command       | kosongkan     |
| Environment variables | tidak perlu   |

## Mengubah halamannya

Ubah `public/index.html` untuk teks dan `public/style.css` untuk tampilan. Berkas lain di `public/`, misalnya gambar atau halaman kedua, ikut tersaji. `public/404.html` tampil untuk alamat yang tidak ada.

Untuk melihatnya di laptop, buka `public/index.html` di browser, atau jalankan server kecil apa saja dari folder `public/`, misalnya `python3 -m http.server 8000`.

## Cek

CI memvalidasi HTML-nya dengan [html-validate](https://html-validate.org), termasuk aturan aksesibilitas. Dari root repository:

```bash
npx --yes html-validate@11.21.1 static-site/public
```

## English

A single static page with no build step. Set **Root directory** to `static-site` and leave the other settings empty. Railpack reads `Staticfile` and serves `public/` with Caddy.
