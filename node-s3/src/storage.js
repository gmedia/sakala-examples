import {
  DeleteObjectCommand,
  GetObjectCommand,
  ListObjectsV2Command,
  S3Client,
} from '@aws-sdk/client-s3';
import { Upload } from '@aws-sdk/lib-storage';
import { getSignedUrl } from '@aws-sdk/s3-request-presigner';
import mime from 'mime-types';

// How long a download link stays valid. Links are made fresh on every request.
export const LINK_TTL_SECONDS = 15 * 60;

// Upper bound on the objects shown, one ListObjectsV2 page.
const LIST_MAX_KEYS = 1000;

// Keeps the end of long names so the extension survives.
const MAX_NAME_LENGTH = 80;
const FALLBACK_NAME = 'berkas';

/**
 * Reads the variables Sakala sets when a bucket is attached to the project.
 * Returns `{ config }` when everything is there, or `{ missing }` with the
 * names (never the values) of what is absent.
 */
export function readBucketConfig(env) {
  const endpoint = env.AWS_ENDPOINT_URL_S3 || env.AWS_ENDPOINT;
  // AWS SDK for JavaScript v3 reads AWS_REGION, not AWS_DEFAULT_REGION.
  const region = env.AWS_REGION || env.AWS_DEFAULT_REGION;

  const missing = [];
  if (!endpoint) missing.push('AWS_ENDPOINT_URL_S3');
  if (!region) missing.push('AWS_REGION');
  for (const name of ['AWS_BUCKET', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY']) {
    if (!env[name]) missing.push(name);
  }
  if (missing.length > 0) {
    return { missing };
  }

  return {
    config: {
      endpoint,
      region,
      bucket: env.AWS_BUCKET,
      // Sakala's endpoint only serves path-style URLs: https://endpoint/bucket/key.
      forcePathStyle: env.AWS_USE_PATH_STYLE_ENDPOINT === 'true',
      credentials: {
        accessKeyId: env.AWS_ACCESS_KEY_ID,
        secretAccessKey: env.AWS_SECRET_ACCESS_KEY,
      },
    },
  };
}

export function createStorage(config) {
  const client = new S3Client({
    endpoint: config.endpoint,
    region: config.region,
    forcePathStyle: config.forcePathStyle,
    credentials: config.credentials,
  });
  const bucket = config.bucket;

  return {
    // Newest first. Keys start with an ISO timestamp, so reverse order is newest first.
    async list() {
      const result = await client.send(
        new ListObjectsV2Command({ Bucket: bucket, MaxKeys: LIST_MAX_KEYS }),
      );
      const objects = (result.Contents ?? []).toReversed();
      const files = await Promise.all(
        objects.map(async (object) => ({
          key: object.Key,
          size: object.Size ?? 0,
          url: await getSignedUrl(
            client,
            new GetObjectCommand({ Bucket: bucket, Key: object.Key }),
            { expiresIn: LINK_TTL_SECONDS },
          ),
        })),
      );
      return { files, truncated: Boolean(result.IsTruncated) };
    },

    // Body can be a Buffer or a stream. Upload switches to multipart for large bodies.
    upload(key, body) {
      return new Upload({
        client,
        params: {
          Bucket: bucket,
          Key: key,
          Body: body,
          // Guessed from the extension, the same way a restore from backup does it.
          ContentType: mime.lookup(key) || 'application/octet-stream',
        },
      });
    },

    async remove(key) {
      await client.send(new DeleteObjectCommand({ Bucket: bucket, Key: key }));
    },
  };
}

/** Turns a user-supplied file name into a safe object key segment. */
export function safeFileName(originalName) {
  const baseName = String(originalName ?? '').split(/[\\/]/).pop();
  const cleaned = baseName
    .normalize('NFKD')
    .replace(/[^\w.-]+/g, '-')
    .replace(/-{2,}/g, '-')
    .replace(/^[.-]+/, '')
    .slice(-MAX_NAME_LENGTH);
  return cleaned || FALLBACK_NAME;
}

/** Timestamp first, so keys sort by upload time and never collide in practice. */
export function objectKey(originalName, now = new Date()) {
  const timestamp = now.toISOString().replace(/[:.]/g, '-');
  return `${timestamp}-${safeFileName(originalName)}`;
}

/**
 * What the app shows when the bucket answers with an error. `error` is the S3
 * error code (for example NoSuchBucket or a quota error) or the network error
 * code, and `status` is the HTTP status the storage server sent, if any.
 */
export function describeStorageError(error) {
  const isGenericError = !error?.name || error.name === 'Error';
  return {
    error: isGenericError && error?.code ? error.code : (error?.name ?? 'Error'),
    status: error?.$metadata?.httpStatusCode ?? null,
    message: error?.message ?? String(error),
  };
}
