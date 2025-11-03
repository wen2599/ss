import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  /**
   * plugins 数组是 Vite 的核心配置。
   * 我们必须在这里加入 react() 插件，Vite 才能正确处理：
   * 1. JSX 语法转换 (例如把 <div /> 变成 JavaScript)。
   * 2. 在需要的文件中自动注入 React 的运行时代码，解决 "React is not defined" 错误。
   */
  plugins: [
    react()
  ],

  // (可选配置) 如果你想自定义构建行为，可以添加 build 选项。
  // 对于 Cloudflare Pages 部署，通常不需要特别配置。
  build: {
    outDir: 'dist', // 构建输出目录，Cloudflare Pages 默认会识别这个
    sourcemap: false, // 生产环境可以关闭 sourcemap 以减小文件大小
  },

  // (可选配置) 配置开发服务器
  server: {
    port: 3000, // 本地开发服务器端口
    open: true, // 启动时自动在浏览器打开
  }
});