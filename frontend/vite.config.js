import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    proxy: {
      // 代理所有对 /api.php 的请求到您的后端服务器
      '/api.php': {
        target: 'https://ss.wenxiuxiu.eu.org', // 您的实际后端服务器地址
        changeOrigin: true,
        secure: false, // 如果后端是 HTTPS 但在开发环境遇到 SSL 证书问题，可以设置为 false
      },
    },
  },
});
