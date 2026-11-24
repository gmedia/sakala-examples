import assert from 'node:assert/strict';
import { after, before, describe, test } from 'node:test';

import { MAX_UPLOAD_BYTES, createApp } from '../src/app.js';
import { describeStorageError, objectKey, readBucketConfig, safeFileName } from '../src/storage.js';

async function listen(app) {
  const server = app.listen(0);
  await new Promise((resolve) => server.once('listening', resolve));
  return { server, baseUrl: `http://127.0.0.1:${server.address().port}` };
}

// Stands in for the S3 client: keeps uploads in memory, or fails like S3 would.
function fakeStorage({ failWith } = {}) {
  const objects = new Map();
  return {
    objects,
    aborted: [],
    async list() {
      if (failWith) throw failWith;
      const files = [...objects.entries()].map(([key, body]) => ({
        key,
        size: body.length,
        url: `https://s3.example.test/bucket/${encodeURIComponent(key)}?X-Amz-Signature=fake`,
      }));
      return { files, truncated: false };
    },
    upload(key, body) {
      const storage = this;
      let aborted = false;
      return {
        abort() {
          aborted = true;
          storage.aborted.push(key);
        },
        async done() {
          const chunks = [];
          if (Buffer.isBuffer(body)) {
            chunks.push(body);
          } else {
            for await (const chunk of body) {
              if (aborted) break;
              chunks.push(chunk);
            }
          }
          if (aborted) throw Object.assign(new Error('Upload aborted.'), { name: 'AbortError' });
          if (failWith) throw failWith;
          objects.set(key, Buffer.concat(chunks));
        },
      };
    },
    async remove(key) {
      objects.delete(key);
    },
  };
}

function quotaError() {
  return Object.assign(new Error('Bucket quota exceeded'), {
    name: 'XMinioAdminBucketQuotaExceeded',
    $metadata: { httpStatusCode: 400 },
  });
}

function multipartForm(name, content) {
  const form = new FormData();
  form.append('file', new Blob([content]), name);
  return form;
}

describe('without a bucket', () => {
  let server;
  let baseUrl;

  before(async () => {
    ({ server, baseUrl } = await listen(createApp({ env: {} })));
  });

  after(() => server.close());

  test('home page explains how to attach a bucket', async () => {
    const response = await fetch(`${baseUrl}/`);
    const body = await response.text();

    assert.equal(response.status, 200);
    assert.match(response.headers.get('content-type'), /text\/html/);
    assert.match(body, /Bucket belum dipasang/);
    assert.match(body, /AWS_BUCKET/);
  });

  test('healthz answers ok', async () => {
    const response = await fetch(`${baseUrl}/healthz`);

    assert.equal(response.status, 200);
    assert.deepEqual(await response.json(), { status: 'ok' });
  });

  test('api/files answers a clear JSON error', async () => {
    const response = await fetch(`${baseUrl}/api/files`);
    const body = await response.json();

    assert.equal(response.status, 503);
    assert.equal(body.ok, false);
    assert.equal(body.error, 'BUCKET_NOT_CONFIGURED');
    assert.ok(body.missing.includes('AWS_ENDPOINT_URL_S3'));
  });

  test('api/upload answers a clear JSON error', async () => {
    const response = await fetch(`${baseUrl}/api/upload?name=a.txt`, { method: 'POST', body: 'halo' });

    assert.equal(response.status, 503);
    assert.equal((await response.json()).error, 'BUCKET_NOT_CONFIGURED');
  });

  test('form upload shows the same message', async () => {
    const response = await fetch(`${baseUrl}/upload`, { method: 'POST', body: multipartForm('a.txt', 'halo') });

    assert.equal(response.status, 503);
    assert.match(await response.text(), /Bucket belum dipasang/);
  });
});

describe('with a bucket', () => {
  let server;
  let baseUrl;
  let storage;

  before(async () => {
    storage = fakeStorage();
    ({ server, baseUrl } = await listen(createApp({ env: {}, storage })));
  });

  after(() => server.close());

  test('form upload stores the file and redirects home', async () => {
    const response = await fetch(`${baseUrl}/upload`, {
      method: 'POST',
      body: multipartForm('Foto Liburan <1>.jpg', 'isi gambar'),
      redirect: 'manual',
    });

    assert.equal(response.status, 303);
    const key = new URL(response.headers.get('location'), baseUrl).searchParams.get('uploaded');
    assert.match(key, /^\d{4}-\d{2}-\d{2}T[\d-]+Z-Foto-Liburan-1-\.jpg$/);
    assert.equal(storage.objects.get(key).toString(), 'isi gambar');
  });

  test('home page lists files with escaped names and signed links', async () => {
    storage.objects.set('<script>x</script>.txt', Buffer.from('x'));
    const body = await (await fetch(`${baseUrl}/`)).text();

    assert.match(body, /Upload ke bucket/);
    assert.match(body, /X-Amz-Signature=fake/);
    assert.doesNotMatch(body, /<script>x/);
    assert.match(body, /&lt;script&gt;x&lt;\/script&gt;\.txt/);
  });

  test('form upload over the limit is rejected and not kept', async () => {
    const before = storage.objects.size;
    const response = await fetch(`${baseUrl}/upload`, {
      method: 'POST',
      body: multipartForm('besar.bin', Buffer.alloc(MAX_UPLOAD_BYTES + 1)),
    });

    assert.equal(response.status, 413);
    assert.match(await response.text(), /FILE_TOO_LARGE/);
    assert.equal(storage.aborted.length, 1);
    assert.equal(storage.objects.size, before);
  });

  test('form without a file is rejected', async () => {
    const form = new FormData();
    form.append('note', 'tanpa berkas');
    const response = await fetch(`${baseUrl}/upload`, { method: 'POST', body: form });

    assert.equal(response.status, 400);
    assert.match(await response.text(), /NO_FILE/);
  });

  test('api upload stores a raw body', async () => {
    const response = await fetch(`${baseUrl}/api/upload?name=catatan.txt`, {
      method: 'POST',
      headers: { 'content-type': 'application/octet-stream' },
      body: 'halo bucket',
    });
    const body = await response.json();

    assert.equal(response.status, 201);
    assert.equal(body.ok, true);
    assert.match(body.key, /-catatan\.txt$/);
    assert.equal(storage.objects.get(body.key).toString(), 'halo bucket');
  });

  test('api upload needs a name and a body', async () => {
    const noName = await fetch(`${baseUrl}/api/upload`, { method: 'POST', body: 'x' });
    const noBody = await fetch(`${baseUrl}/api/upload?name=a.txt`, { method: 'POST' });

    assert.equal(noName.status, 400);
    assert.equal((await noName.json()).error, 'NAME_REQUIRED');
    assert.equal(noBody.status, 400);
    assert.equal((await noBody.json()).error, 'EMPTY_BODY');
  });

  test('api upload over the limit answers 413 JSON', async () => {
    const response = await fetch(`${baseUrl}/api/upload?name=besar.bin`, {
      method: 'POST',
      headers: { 'content-type': 'application/octet-stream' },
      body: Buffer.alloc(MAX_UPLOAD_BYTES + 1),
    });

    assert.equal(response.status, 413);
    assert.deepEqual(await response.json(), {
      ok: false,
      error: 'FILE_TOO_LARGE',
      status: 413,
      message: 'Berkas lebih besar dari 20 MB.',
    });
  });

  test('api files lists the bucket', async () => {
    const body = await (await fetch(`${baseUrl}/api/files`)).json();

    assert.equal(body.ok, true);
    assert.ok(body.files.every((file) => file.key && Number.isInteger(file.size) && file.url));
  });
});

describe('when the bucket answers with an error', () => {
  let server;
  let baseUrl;

  before(async () => {
    ({ server, baseUrl } = await listen(createApp({ env: {}, storage: fakeStorage({ failWith: quotaError() }) })));
  });

  after(() => server.close());

  test('api upload surfaces the S3 error code and status', async () => {
    const response = await fetch(`${baseUrl}/api/upload?name=a.txt`, { method: 'POST', body: 'x' });

    assert.equal(response.status, 502);
    assert.deepEqual(await response.json(), {
      ok: false,
      error: 'XMinioAdminBucketQuotaExceeded',
      status: 400,
      message: 'Bucket quota exceeded',
    });
  });

  test('form upload shows the error instead of crashing', async () => {
    const response = await fetch(`${baseUrl}/upload`, { method: 'POST', body: multipartForm('a.txt', 'x') });
    const body = await response.text();

    assert.equal(response.status, 502);
    assert.match(body, /Upload gagal/);
    assert.match(body, /XMinioAdminBucketQuotaExceeded/);
  });

  test('home page still renders', async () => {
    const response = await fetch(`${baseUrl}/`);

    assert.equal(response.status, 200);
    assert.match(await response.text(), /Bucket tidak bisa dibaca/);
  });
});

describe('helpers', () => {
  test('readBucketConfig prefers AWS_ENDPOINT_URL_S3 and AWS_REGION', () => {
    const { config } = readBucketConfig({
      AWS_ENDPOINT_URL_S3: 'https://s3.example.test',
      AWS_ENDPOINT: 'https://other.example.test',
      AWS_REGION: 'us-east-1',
      AWS_DEFAULT_REGION: 'ap-southeast-1',
      AWS_BUCKET: 'b',
      AWS_ACCESS_KEY_ID: 'id',
      AWS_SECRET_ACCESS_KEY: 'secret',
      AWS_USE_PATH_STYLE_ENDPOINT: 'true',
    });

    assert.equal(config.endpoint, 'https://s3.example.test');
    assert.equal(config.region, 'us-east-1');
    assert.equal(config.forcePathStyle, true);
  });

  test('readBucketConfig falls back to AWS_ENDPOINT and AWS_DEFAULT_REGION', () => {
    const { config } = readBucketConfig({
      AWS_ENDPOINT: 'https://other.example.test',
      AWS_DEFAULT_REGION: 'ap-southeast-1',
      AWS_BUCKET: 'b',
      AWS_ACCESS_KEY_ID: 'id',
      AWS_SECRET_ACCESS_KEY: 'secret',
    });

    assert.equal(config.endpoint, 'https://other.example.test');
    assert.equal(config.region, 'ap-southeast-1');
    assert.equal(config.forcePathStyle, false);
  });

  test('readBucketConfig names what is missing', () => {
    assert.deepEqual(readBucketConfig({ AWS_BUCKET: 'b' }).missing, [
      'AWS_ENDPOINT_URL_S3',
      'AWS_REGION',
      'AWS_ACCESS_KEY_ID',
      'AWS_SECRET_ACCESS_KEY',
    ]);
  });

  test('safeFileName keeps the extension and drops paths', () => {
    assert.equal(safeFileName('../../etc/passwd'), 'passwd');
    assert.equal(safeFileName('C:\\Users\\a\\Laporan Akhir.pdf'), 'Laporan-Akhir.pdf');
    assert.equal(safeFileName('.env'), 'env');
    assert.equal(safeFileName(''), 'berkas');
    assert.ok(safeFileName(`${'a'.repeat(200)}.png`).endsWith('.png'));
  });

  test('objectKey starts with a sortable timestamp', () => {
    assert.equal(objectKey('a b.txt', new Date('2026-11-24T03:04:05.678Z')), '2026-11-24T03-04-05-678Z-a-b.txt');
  });

  test('describeStorageError uses the network error code', () => {
    const error = Object.assign(new Error('connect ECONNREFUSED'), { code: 'ECONNREFUSED' });
    assert.equal(describeStorageError(error).error, 'ECONNREFUSED');
    assert.equal(describeStorageError(error).status, null);
  });
});
