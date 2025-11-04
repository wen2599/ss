import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'dist',
    sourcemap: false
  },
  server: {
    proxy: {
      '/api': {
        target: 'https://wenge.cloudns.ch',
        changeOrigin: true,
        secure: false
      }
    }
  }
})
