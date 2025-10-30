import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // 将 /api 请求代理到您的后端服务器
      '/api': {
        target: 'https://wenge.cloudns.ch',
        changeOrigin: true,
        // 重写路径，移除 /api 前缀
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
    },
  },
});
