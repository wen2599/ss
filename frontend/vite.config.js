import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // This will forward all requests that start with /api to your PHP backend.
    // Adjust the target to your PHP backend's URL (e.g., your serv00.com address).
    proxy: {
      '/api': {
        target: 'https://wenge.cloudns.ch', // Your serv00 PHP backend URL
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, ''),
        secure: true, // Assuming your backend is HTTPS
      },
    },
  },
})
