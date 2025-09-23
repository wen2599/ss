import React from 'react'
import ReactDOM from 'react-dom/client'
import {
  createBrowserRouter,
  RouterProvider,
} from "react-router-dom";
import App from './App.jsx'
// LoginPage and RegisterPage are no longer used
import BillsPage from './pages/BillsPage.jsx';
import LotteryResultsPage from './pages/LotteryResultsPage.jsx';
import ProtectedRoute from './components/ProtectedRoute.jsx';
import MainLayout from './components/MainLayout.jsx';
import { AuthProvider } from './context/AuthContext.jsx';
import './index.css'

const router = createBrowserRouter([
  {
    element: (
      <ProtectedRoute>
        <MainLayout />
      </ProtectedRoute>
    ),
    children: [
      {
        path: "/",
        element: <App />,
      },
      {
        path: "bills",
        element: <BillsPage />,
      },
      {
        path: "lottery-results",
        element: <LotteryResultsPage />,
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
