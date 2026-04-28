import path from 'path';
import { fileURLToPath } from 'url';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function manualChunkForNodeModule(id: string): string | undefined {
  const n = id.split(path.sep).join('/');
  if (!n.includes('node_modules')) {
    return undefined;
  }
  if (n.includes('react-quill') || n.includes('/quill/')) {
    return 'sikshya-editor';
  }
  if (n.includes('@dnd-kit')) {
    return 'sikshya-dnd';
  }
  if (n.includes('react-datepicker')) {
    return 'sikshya-datepicker';
  }
  if (/node_modules\/(react|react-dom|scheduler)(\/|$)/.test(n)) {
    return 'sikshya-react';
  }
  return 'sikshya-vendor';
}

export default defineConfig({
  root: path.resolve(__dirname, 'client'),
  /**
   * WordPress serves the built files from `assets/admin/react/` inside the plugin.
   * Use a relative base so Vite's preload helper doesn't generate absolute `/<chunk>.js` URLs,
   * which would 404 on most WP installs.
   */
  base: './',
  plugins: [react()],
  server: {
    fs: {
      allow: [path.resolve(__dirname)],
    },
  },
  build: {
    outDir: path.resolve(__dirname, 'assets/admin/react'),
    emptyOutDir: true,
    /** One CSS file so PHP can enqueue `sikshya-admin.css` (matches `AdminAssetsService`). */
    cssCodeSplit: false,
    sourcemap: false,
    /**
     * ES modules + lazy routes + manualChunks keep chunks under the default warning threshold.
     * WordPress loads `sikshya-admin.js` with `type="module"`; Rollup emits shared chunks beside it.
     */
    chunkSizeWarningLimit: 1000,
    rollupOptions: {
      input: path.resolve(__dirname, 'client/index.html'),
      output: {
        format: 'es',
        entryFileNames: 'sikshya-admin.js',
        chunkFileNames: 'sikshya-admin-[name]-[hash].js',
        assetFileNames: (info) => {
          if (info.name?.endsWith('.css')) {
            return 'sikshya-admin.css';
          }
          return 'sikshya-admin-[name][extname]';
        },
        manualChunks: manualChunkForNodeModule,
      },
    },
  },
});
