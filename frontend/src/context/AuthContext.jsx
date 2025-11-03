import React, { createContext, useState, useContext } from 'react';
import { useNavigate } from 'react-router-dom';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
  const navigate = useNavigate();
  // Use 'authToken' to match the api.js interceptor
  const [token, setToken] = useState(localStorage.getItem('authToken'));

  const login = (newToken) => {
    setToken(newToken);
    localStorage.setItem('authToken', newToken);
    navigate('/'); // 登录成功后跳转到主页
  };

  const logout = () => {
    setToken(null);
    localStorage.removeItem('authToken');
    navigate('/login'); // 退出后跳转到登录页
  };

  const value = { token, login, logout };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

// 创建一个自定义hook，方便其他组件使用AuthContext
export const useAuth = () => {
  return useContext(AuthContext);
};