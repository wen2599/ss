import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // Proxy all requests starting with '/api' to the backend server.
      // This is crucial for local development to avoid CORS issues.
      '/api': {
        // Target the PHP development server we started.
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        // Rewrite the path to match the backend's expected URL structure.
        // e.g., a request to '/api/get_bills' will be forwarded to '/backend/get_bills'.
        rewrite: (path) => `/backend${path.replace(/^\/api/, '')}`,
      },
    },
  },
})
