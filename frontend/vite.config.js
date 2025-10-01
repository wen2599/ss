import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // A list of all backend actions that should be proxied.
      // This ensures that the frontend, running on the Vite dev server,
      // can communicate with the backend PHP server during local development.
      '/login': 'http://localhost:8000',
      '/register': 'http://localhost:8000',
      '/check_session': 'http://localhost:8000',
      '/logout': 'http://localhost:8000',
      '/get_lottery_results': 'http://localhost:8000',
      '/get_game_data': 'http://localhost:8000',
      '/get_bills': 'http://localhost:8000',
      '/delete_bill': 'http://localhost:8000',
      '/update_settlement': 'http://localhost:8000',
      // Adding all other available actions to be safe.
      '/is_user_registered': 'http://localhost:8000',
      '/email_upload': 'http://localhost:8000',
      '/process_text': 'http://localhost:8000',
    },
  },
});
