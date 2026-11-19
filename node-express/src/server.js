import { createApp } from './app.js';

// Sakala sets PORT. The fallback is for running on your own machine.
const DEFAULT_PORT = 3000;
const port = Number.parseInt(process.env.PORT ?? '', 10) || DEFAULT_PORT;

const server = createApp().listen(port, () => {
  console.log(`Listening on port ${port}`);
});

// Finish in-flight requests when the container is stopped during a redeploy.
for (const signal of ['SIGTERM', 'SIGINT']) {
  process.on(signal, () => {
    server.close(() => process.exit(0));
  });
}
