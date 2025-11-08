// src/context/AuthContext.jsx
import React, { createContext, useState, useContext, useEffect } from 'react';
import { apiService } from '../api';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // 应用加载时，检查后端 session
    apiService.checkSession()
      .then(data => {
        if (data.status === 'success' && data.isAuthenticated) {
          setUser(data.user);
        }
      })
      .catch(() => setUser(null)) // 如果请求失败，则视为未登录
      .finally(() => setLoading(false));
  }, []);

  const login = async (email, password) => {
    const data = await apiService.login(email, password);
    if (data.status === 'success') {
      setUser(data.user);
    }
    return data; // 返回整个响应给组件处理
  };

  const register = (email, password) => {
    return apiService.register(email, password);
  };

  const logout = async () => {
    await apiService.logout();
    setUser(null);
  };

  const value = { user, loading, login, register, logout };

  return (
    <AuthContext.Provider value={value}>
      {!loading && children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  return useContext(AuthContext);
};