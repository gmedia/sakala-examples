import { createApp } from './app.js';
import { readBucketConfig } from './storage.js';

// Sakala sets PORT. The fallback is for running on your own machine.
const DEFAULT_PORT = 3000;
const port = Number.parseInt(process.env.PORT ?? '', 10) || DEFAULT_PORT;

const server = createApp().listen(port, () => {
  console.log(`Listening on port ${port}`);
  // Only variable names are logged, never their values.
  const { missing } = readBucketConfig(process.env);
  console.log(missing ? `No bucket attached, missing: ${missing.join(', ')}` : 'Bucket attached');
});

// Finish in-flight requests when the container is stopped during a redeploy.
for (const signal of ['SIGTERM', 'SIGINT']) {
  process.on(signal, () => {
    server.close(() => process.exit(0));
  });
}
