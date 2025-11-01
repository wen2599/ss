import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api_router.php': {
        target: 'https://wenge.cloudns.ch',
        changeOrigin: true,
      },
    },
  },
})
