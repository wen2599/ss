import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // Proxy all API requests to the local PHP development server
      '/register': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/backend/index.php?action=register`,
      },
      '/login': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/backend/index.php?action=login`,
      },
      '/logout': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/backend/index.php?action=logout`,
      },
      '/check_session': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/backend/index.php?action=check_session`,
      },
      '/process_email': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => `/backend/index.php?action=process_email`,
      },
    },
  },
})