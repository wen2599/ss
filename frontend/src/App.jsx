import { BrowserRouter, Routes, Route, Link, useNavigate } from 'react-router-dom'
import { AuthProvider, AuthContext } from './context/AuthContext'
import React, { useContext, useEffect } from 'react'

import Login from './pages/Login'
import Register from './pages/Register'
import Dashboard from './pages/Dashboard'
import Emails from './pages/Emails'
import Settlement from './pages/Settlement'

function AppContent() {
  const { user, logout } = useContext(AuthContext)
  const navigate = useNavigate()

  useEffect(() => {
    if (user && (window.location.pathname === '/login' || window.location.pathname === '/register')) {
      navigate('/')
    } else if (!user && (window.location.pathname === '/emails' || window.location.pathname.startsWith('/settlement'))) {
      navigate('/login')
    }
  }, [user, navigate])

  return (
    <div>
      <nav style={{ display: 'flex', justifyContent: 'space-around', background: '#eee', padding: '1em' }}>
        <Link to="/">开奖记录</Link>
        <Link to="/emails">邮件原文</Link>
        <Link to="/settlement/1">结算表单 (示例)</Link> {/* Placeholder, will be dynamic */}
        {user ? (
          <button onClick={logout}>退出登录</button>
        ) : (
          <div>
            <Link to="/register">注册</Link>
            <Link to="/login">登录</Link>
          </div>
        )}
      </nav>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/" element={<Dashboard />} />
        <Route path="/emails" element={<Emails />} />
        <Route path="/settlement/:emailId" element={<Settlement />} />
      </Routes>
    </div>
  )
}

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <AppContent />
      </BrowserRouter>
    </AuthProvider>
  )
}

export default App