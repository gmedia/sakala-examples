import busboy from 'busboy';
import escapeHtml from 'escape-html';
import express from 'express';

import {
  LINK_TTL_SECONDS,
  createStorage,
  describeStorageError,
  objectKey,
  readBucketConfig,
} from './storage.js';

// Largest file accepted, for both the form and the API.
export const MAX_UPLOAD_BYTES = 20_000_000;
const MAX_UPLOAD_MB = MAX_UPLOAD_BYTES / 1_000_000;
const LINK_TTL_MINUTES = LINK_TTL_SECONDS / 60;

const PAGE_TITLE = 'Bucket terkelola dari Express di Sakala';
const NO_BUCKET_ERROR = 'BUCKET_NOT_CONFIGURED';

const pageStyle = `
    :root { color-scheme: light dark; }
    body { margin: 0; font: 1rem/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    main { max-width: 44rem; margin: 0 auto; padding: 2.5rem 1.25rem; }
    h1 { font-size: 1.75rem; line-height: 1.25; }
    code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .notice { border: 2px solid currentColor; border-radius: 0.5rem; padding: 0.75rem 1rem; }
    table { border-collapse: collapse; width: 100%; }
    th, td { text-align: left; padding: 0.35rem 0.5rem; border-bottom: 1px solid color-mix(in srgb, currentColor 25%, transparent); }
    td.size { white-space: nowrap; text-align: right; }
    td a { word-break: break-all; }
    a:focus-visible, input:focus-visible, button:focus-visible { outline: 3px solid currentColor; outline-offset: 2px; }
`;

// Decimal units, matching the kB and MB labels Intl prints.
function formatBytes(bytes) {
  const inMegabytes = bytes >= 1_000_000;
  return new Intl.NumberFormat('id-ID', {
    style: 'unit',
    unit: inMegabytes ? 'megabyte' : 'kilobyte',
    maximumFractionDigits: 1,
  }).format(inMegabytes ? bytes / 1_000_000 : bytes / 1000);
}

function errorText(failure) {
  const status = failure.status ? `, HTTP ${failure.status}` : '';
  return `<code>${escapeHtml(failure.error)}</code>${escapeHtml(status)}: ${escapeHtml(failure.message)}`;
}

function noBucketSection(missing) {
  const names = missing.map((name) => `<li><code>${escapeHtml(name)}</code></li>`).join('');
  return `
    <section class="notice" aria-labelledby="no-bucket">
      <h2 id="no-bucket">Bucket belum dipasang</h2>
      <p>Aplikasi ini belum menerima variabel bucket. Yang belum ada:</p>
      <ul>${names}</ul>
      <p>Pasang bucket ke project ini dari halaman <strong>Bucket</strong> di Console Sakala, lalu tekan <strong>Redeploy</strong>. Sakala mengisi variabel <code>AWS_*</code> sendiri.</p>
    </section>`;
}

function fileListSection(listing) {
  if (listing.failure) {
    return `
    <section class="notice" aria-labelledby="files">
      <h2 id="files">Isi bucket</h2>
      <p>Bucket tidak bisa dibaca: ${errorText(listing.failure)}</p>
    </section>`;
  }
  if (listing.files.length === 0) {
    return `
    <section aria-labelledby="files">
      <h2 id="files">Isi bucket</h2>
      <p>Bucket masih kosong. Upload satu berkas lewat form di atas.</p>
    </section>`;
  }
  const rows = listing.files
    .map(
      (file) => `
        <tr>
          <td><a href="${escapeHtml(file.url)}">${escapeHtml(file.key)}</a></td>
          <td class="size">${escapeHtml(formatBytes(file.size))}</td>
        </tr>`,
    )
    .join('');
  const truncated = listing.truncated
    ? '<p>Hanya 1.000 berkas pertama yang ditampilkan.</p>'
    : '';
  return `
    <section aria-labelledby="files">
      <h2 id="files">Isi bucket</h2>
      <p>Bucket ini privat. Setiap link di bawah adalah link sementara yang berlaku ${LINK_TTL_MINUTES} menit dan dibuat ulang setiap kali halaman dimuat.</p>
      <table>
        <thead><tr><th scope="col">Berkas</th><th scope="col">Ukuran</th></tr></thead>
        <tbody>${rows}
        </tbody>
      </table>
      ${truncated}
    </section>`;
}

function uploadSection(message) {
  return `
    <section aria-labelledby="upload">
      <h2 id="upload">Upload berkas</h2>
      ${message ?? ''}
      <form method="post" action="/upload" enctype="multipart/form-data">
        <p>
          <label for="file">Berkas, paling besar ${MAX_UPLOAD_MB} MB</label><br>
          <input id="file" name="file" type="file" required>
        </p>
        <p><button type="submit">Upload ke bucket</button></p>
      </form>
    </section>`;
}

function renderPage(body) {
  return `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${PAGE_TITLE}</title>
  <style>${pageStyle}</style>
</head>
<body>
  <main>
    <h1>${PAGE_TITLE}</h1>
    <p>Aplikasi ini menyimpan upload di bucket S3 yang dikelola Sakala, lalu membuka berkasnya lewat link sementara.</p>
${body}
    <p>Endpoint <a href="/healthz"><code>/healthz</code></a> menjawab <code>{"status":"ok"}</code> untuk pengecekan, dan <a href="/api/files"><code>/api/files</code></a> memberi daftar berkas dalam JSON.</p>
    <p>Panduan lengkap ada di <a href="https://sakala.dev/docs/bucket-terkelola">dokumentasi Sakala</a>.</p>
  </main>
</body>
</html>
`;
}

/**
 * Streams the single file of a multipart form into the bucket. Resolves with
 * `{ ok: true, key }` or `{ ok: false, httpStatus, error, status, message }`.
 */
function storeMultipartFile(request, storage) {
  return new Promise((resolve) => {
    let parser;
    try {
      parser = busboy({
        headers: request.headers,
        limits: { files: 1, fileSize: MAX_UPLOAD_BYTES },
      });
    } catch (error) {
      resolve({ ok: false, httpStatus: 400, error: 'BAD_REQUEST', status: 400, message: error.message });
      return;
    }

    let fileSeen = false;

    // A failed or aborted upload can stop reading the file stream, and busboy
    // then never finishes. Detach it and read the rest of the request ourselves,
    // so the response can still be sent.
    function fail(result) {
      request.unpipe(parser);
      request.resume();
      resolve(result);
    }

    parser.on('file', (_field, file, info) => {
      fileSeen = true;
      const key = objectKey(info.filename);
      const upload = storage.upload(key, file);
      let tooLarge = false;

      file.on('limit', () => {
        tooLarge = true;
        upload.abort();
      });

      upload.done().then(
        async () => {
          if (!tooLarge) {
            resolve({ ok: true, key });
            return;
          }
          // The size limit hit after the last write; do not keep a truncated file.
          await storage.remove(key).catch(() => {});
          fail(tooLargeResult());
        },
        (error) => {
          if (tooLarge) {
            fail(tooLargeResult());
            return;
          }
          const failure = describeStorageError(error);
          console.error(`Bucket upload failed: ${failure.error}`);
          fail({ ok: false, httpStatus: 502, ...failure });
        },
      );
    });

    parser.on('close', () => {
      if (!fileSeen) {
        resolve({ ok: false, httpStatus: 400, error: 'NO_FILE', status: 400, message: 'Pilih satu berkas untuk di-upload.' });
      }
    });

    parser.on('error', (error) => {
      fail({ ok: false, httpStatus: 400, error: 'BAD_REQUEST', status: 400, message: error.message });
    });

    request.pipe(parser);
  });
}

function tooLargeResult() {
  return {
    ok: false,
    httpStatus: 413,
    error: 'FILE_TOO_LARGE',
    status: 413,
    message: `Berkas lebih besar dari ${MAX_UPLOAD_MB} MB.`,
  };
}

async function listFiles(storage) {
  try {
    return await storage.list();
  } catch (error) {
    const failure = describeStorageError(error);
    console.error(`Bucket listing failed: ${failure.error}`);
    return { failure };
  }
}

/**
 * `env` defaults to the process environment. Tests pass their own `env`, or a
 * fake `storage` to skip the S3 client.
 */
export function createApp({ env = process.env, storage: injectedStorage } = {}) {
  const { config, missing = [] } = readBucketConfig(env);
  const storage = injectedStorage ?? (config ? createStorage(config) : null);

  const app = express();

  // Do not advertise the framework in every response.
  app.disable('x-powered-by');

  function noBucketJson(response) {
    response.status(503).json({
      ok: false,
      error: NO_BUCKET_ERROR,
      status: 503,
      message: 'Bucket belum dipasang ke project ini. Pasang bucket di Console Sakala, lalu Redeploy.',
      missing,
    });
  }

  app.get('/', async (request, response) => {
    if (!storage) {
      response.type('html').send(renderPage(noBucketSection(missing)));
      return;
    }
    const uploaded = typeof request.query.uploaded === 'string' ? request.query.uploaded : null;
    const message = uploaded
      ? `<p class="notice" role="status">Tersimpan sebagai <code>${escapeHtml(uploaded)}</code>.</p>`
      : null;
    const listing = await listFiles(storage);
    response.type('html').send(renderPage(uploadSection(message) + fileListSection(listing)));
  });

  app.post('/upload', async (request, response) => {
    if (!storage) {
      response.status(503).type('html').send(renderPage(noBucketSection(missing)));
      return;
    }
    const result = await storeMultipartFile(request, storage);
    if (result.ok) {
      // Post/Redirect/Get, so a refresh does not upload the file again.
      response.redirect(303, `/?uploaded=${encodeURIComponent(result.key)}`);
      return;
    }
    const message = `<p class="notice" role="alert">Upload gagal: ${errorText(result)}</p>`;
    const listing = await listFiles(storage);
    response
      .status(result.httpStatus)
      .type('html')
      .send(renderPage(uploadSection(message) + fileListSection(listing)));
  });

  app.get('/api/files', async (_request, response) => {
    if (!storage) {
      noBucketJson(response);
      return;
    }
    const listing = await listFiles(storage);
    if (listing.failure) {
      response.status(502).json({ ok: false, ...listing.failure });
      return;
    }
    response.json({ ok: true, files: listing.files, truncated: listing.truncated });
  });

  app.post(
    '/api/upload',
    (_request, response, next) => (storage ? next() : noBucketJson(response)),
    // Any content type is accepted, so `curl --data-binary @file` works as is.
    express.raw({ type: () => true, limit: MAX_UPLOAD_BYTES }),
    async (request, response) => {
      const name = typeof request.query.name === 'string' ? request.query.name : '';
      if (!name) {
        response.status(400).json({ ok: false, error: 'NAME_REQUIRED', status: 400, message: 'Isi nama berkas lewat ?name=...' });
        return;
      }
      if (!Buffer.isBuffer(request.body) || request.body.length === 0) {
        response.status(400).json({ ok: false, error: 'EMPTY_BODY', status: 400, message: 'Kirim isi berkas sebagai body permintaan.' });
        return;
      }
      const key = objectKey(name);
      try {
        await storage.upload(key, request.body).done();
        response.status(201).json({ ok: true, key });
      } catch (error) {
        const failure = describeStorageError(error);
        console.error(`Bucket upload failed: ${failure.error}`);
        response.status(502).json({ ok: false, ...failure });
      }
    },
  );

  app.get('/healthz', (_request, response) => {
    response.json({ status: 'ok' });
  });

  // Body parser errors, such as a body larger than MAX_UPLOAD_BYTES.
  app.use((error, _request, response, _next) => {
    if (error.type === 'entity.too.large') {
      const { httpStatus, ...body } = tooLargeResult();
      response.status(httpStatus).json(body);
      return;
    }
    const status = error.status ?? error.statusCode ?? 500;
    if (status >= 500) console.error(`Request failed: ${error.name}`);
    response.status(status).json({ ok: false, error: error.name ?? 'Error', status, message: error.expose ? error.message : 'Terjadi kesalahan.' });
  });

  return app;
}
