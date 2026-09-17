import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 5173,
    // В режиме разработки фронт работает на 5173, а API — в контейнере на 8080.
    // Прокси избавляет от проблем с CORS: для браузера всё на одном адресе.
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/storage': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
    },
  },
});
