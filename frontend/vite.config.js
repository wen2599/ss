import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// All known API routes that should be proxied to the backend, mirroring _worker.js
const apiRoutes = [
  '/check_session', '/email_upload', '/get_bills', '/delete_bill',
  '/get_game_data', '/get_lottery_results', '/is_user_registered',
  '/login', '/logout', '/process_text', '/register'
];

// Dynamically create the proxy configuration object from the apiRoutes list.
const proxyConfig = {};
apiRoutes.forEach(route => {
  proxyConfig[route] = {
    // The target is your local PHP server.
    // Make sure this matches the port your PHP server is running on.
    target: 'http://localhost:8000', 
    changeOrigin: true,
    rewrite: (path) => {
      // The original path is, e.g., '/login'.
      // We rewrite it to the format the backend index.php expects.
      const action = path.substring(1); // remove leading '/'
      return `/backend/index.php?action=${action}`;
    }
  };
});


// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: proxyConfig
  }
})
