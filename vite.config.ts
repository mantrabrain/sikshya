import path from 'path';
import { fileURLToPath } from 'url';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  root: path.resolve(__dirname, 'client'),
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
    rollupOptions: {
      input: path.resolve(__dirname, 'client/index.html'),
      output: {
        format: 'iife',
        entryFileNames: 'sikshya-admin.js',
        chunkFileNames: 'sikshya-admin-[name].js',
        assetFileNames: (info) => {
          if (info.name?.endsWith('.css')) {
            return 'sikshya-admin.css';
          }
          return 'sikshya-admin-[name][extname]';
        },
      },
    },
  },
});
