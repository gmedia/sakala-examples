import express from 'express';

const homePage = `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Halo dari Express di Sakala</title>
  <style>
    :root { color-scheme: light dark; }
    body { margin: 0; font: 1rem/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    main { max-width: 40rem; margin: 0 auto; padding: 2.5rem 1.25rem; }
    h1 { font-size: 1.75rem; line-height: 1.25; }
    code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    a:focus-visible { outline: 3px solid currentColor; outline-offset: 2px; }
  </style>
</head>
<body>
  <main>
    <h1>Halo dari Express di Sakala</h1>
    <p>Aplikasi Node.js ini berjalan di Sakala. Sakala mengisi environment variable <code>PORT</code>, dan aplikasi mendengarkan port itu.</p>
    <h2>Cara mengubahnya</h2>
    <ol>
      <li>Ubah teks halaman ini di <code>src/app.js</code>.</li>
      <li>Commit dan push ke GitHub.</li>
      <li>Tekan <strong>Redeploy</strong> di Console Sakala, atau tunggu auto-deploy kalau sudah menyala.</li>
    </ol>
    <p>Endpoint <a href="/healthz"><code>/healthz</code></a> menjawab <code>{"status":"ok"}</code> untuk pengecekan.</p>
    <p>Panduan lengkap ada di <a href="https://sakala.dev/docs/deploy-pertama">dokumentasi Sakala</a>.</p>
  </main>
</body>
</html>
`;

export function createApp() {
  const app = express();

  // Do not advertise the framework in every response.
  app.disable('x-powered-by');

  app.get('/', (_request, response) => {
    response.type('html').send(homePage);
  });

  app.get('/healthz', (_request, response) => {
    response.json({ status: 'ok' });
  });

  return app;
}
