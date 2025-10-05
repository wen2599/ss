import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // Proxy all requests ending in .php to the backend server
      '**/*.php': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})