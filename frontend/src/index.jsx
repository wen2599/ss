import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import { AppProvider } from './contexts/AppContext';

const container = document.getElementById('root');
const root = createRoot(container);

root.render(
  <React.StrictMode>
    <AppProvider>
      <App />
    </AppProvider>
  </React.StrictMode>
);