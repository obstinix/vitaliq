# D3 subset bundle

`public/assets/vendor/d3-vitaliq-7.min.js` is built from `entry.js`. It exposes a global `d3` containing only the modules the charts use: about 78 KB instead of about 280 KB for the full D3 v7 build.

Rebuild it (Node 18+), from the repository root:

```bash
npm ci --prefix tools/d3-bundle
```

```bash
tools/d3-bundle/node_modules/.bin/esbuild tools/d3-bundle/entry.js --bundle --minify --format=iife --global-name=d3 --legal-comments=eof --outfile=public/assets/vendor/d3-vitaliq-7.min.js
```

`package-lock.json` pins the D3 modules and esbuild. Add any new D3 function a chart needs to `entry.js` and rebuild.
