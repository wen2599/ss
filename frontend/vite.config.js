import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // Proxy API requests to the local PHP server
    proxy: {
      // String shorthand for simple targets
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        // We don't need to rewrite the path, as the PHP router expects the full path
        // e.g., /api/getLotteryNumber will be proxied to http://localhost:8000/api/getLotteryNumber
        // This is incorrect, the PHP server is routed from backend/public, so it doesn't know /api
        // Let's correct the rewrite.
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
    }
  }
})