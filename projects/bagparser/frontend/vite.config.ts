import { defineConfig, type Plugin } from 'vite';
import react from '@vitejs/plugin-react';
import pkg from './package.json' with { type: 'json' };

/**
 * D-54: pages load on demand, so index.html no longer references every chunk. The service worker reads
 * /asset-list.json and precaches all of them at install, so the parser and the other tools keep working offline.
 */
function assetList(): Plugin {
  return {
    name: 'bagparser-asset-list',
    apply: 'build',
    generateBundle(_opts, bundle) {
      const files = Object.keys(bundle)
        .filter((f) => f.startsWith('assets/') && /\.(js|css)$/.test(f))
        // D-61: big data chunks (equipment, creatures) load on demand and are cached on first use, not at install
        .filter((f) => !/^assets\/(equipment|creatures)-/.test(f))
        .map((f) => `/${f}`)
        .sort();
      this.emitFile({ type: 'asset', fileName: 'asset-list.json', source: JSON.stringify({ version: pkg.version, files }) });
    },
  };
}

export default defineConfig({
  plugins: [react(), assetList()],
  // Shown in the footer (CHANGELOG.md)
  define: { __APP_VERSION__: JSON.stringify(pkg.version) },
  server: {
    // Allow importing ../shared/items.json
    fs: { allow: ['..'] },
    // In dev, forward API calls to a locally running backend
    proxy: { '/api': 'http://localhost:3000' },
  },
});
