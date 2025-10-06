import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // Define a list of API paths to be proxied, mirroring the Cloudflare Worker setup.
      '/get_numbers': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/index.php?endpoint=${path.substring(1)}`,
      },
      '/check_session': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/index.php?endpoint=${path.substring(1)}`,
      },
      '/login': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/index.php?endpoint=${path.substring(1)}`,
      },
      '/logout': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/index.php?endpoint=${path.substring(1)}`,
      },
      '/register': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/index.php?endpoint=${path.substring(1)}`,
      },
      '/is_user_registered': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/index.php?endpoint=${path.substring(1)}`,
      },
      '/email_upload': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/index.php?endpoint=${path.substring(1)}`,
      },
      '/tg_webhook': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/index.php?endpoint=${path.substring(1)}`,
      },
      // Keep the .php rule as a fallback, just in case.
      '**/*.php': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/index.php?endpoint=${path.substring(1)}`,
      },
    },
  },
})
