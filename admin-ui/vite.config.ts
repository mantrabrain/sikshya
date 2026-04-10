import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  server: {
    fs: {
      allow: [path.resolve(__dirname, '..')],
    },
  },
  build: {
    outDir: path.resolve(__dirname, '../assets/admin/react'),
    emptyOutDir: true,
    sourcemap: false,
    rollupOptions: {
      input: path.resolve(__dirname, 'index.html'),
      output: {
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
