import React from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter, Routes, Route } from 'react-router-dom'
import Home from './pages/Home.tsx'
import Settlement from './pages/Settlement.tsx'
import './index.css'
import { AuthProvider } from './context/AuthContext'

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/settlement" element={<Settlement />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  </React.StrictMode>,
)
