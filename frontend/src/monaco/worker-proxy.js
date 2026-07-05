// Bootstraps a Monaco web worker. Served from /assets/monaco/ — a path nginx
// gives a relaxed CSP (script-src 'unsafe-eval') so the TypeScript language
// worker can run, while the main document keeps its strict, eval-free policy.
// baseUrl is derived from this file's own location so it works under any base href.
self.MonacoEnvironment = {baseUrl: new URL('.', self.location.href).href};
importScripts(new URL('vs/base/worker/workerMain.js', self.location.href).href);
