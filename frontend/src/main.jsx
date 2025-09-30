import React from 'react'
import ReactDOM from 'react-dom/client'
import {
  createBrowserRouter,
  RouterProvider,
} from "react-router-dom";
import App from './App.jsx'
import BillsPage from './pages/BillsPage.jsx';
import LotteryResultsPage from './pages/LotteryResultsPage.jsx';
import MainLayout from './components/MainLayout.jsx';
import SettingsPage from './components/SettingsPage.jsx';
import { AuthProvider } from './context/AuthContext.jsx';
import './index.css'

const router = createBrowserRouter([
  {
    element: <MainLayout />,
    children: [
      {
        index: true,
        element: <App />,
      },
      {
        path: "bills",
        element: <BillsPage />,
      },
      {
        path: "lottery-results",
        element: <LotteryResultsPage />,
      },
      {
        path: "settings",
        element: <SettingsPage />,
      }
    ]
  }
]);

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <AuthProvider>
      <RouterProvider router={router} />
    </AuthProvider>
  </React.StrictMode>,
)
