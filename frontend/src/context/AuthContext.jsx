import React, { createContext, useState, useContext } from 'react';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    // 优先从 localStorage 读取 token，实现持久化登录
    const [token, setToken] = useState(localStorage.getItem('authToken'));

    const login = (newToken) => {
        localStorage.setItem('authToken', newToken);
        setToken(newToken);
    };

    const logout = () => {
        localStorage.removeItem('authToken');
        setToken(null);
    };

    const value = { token, login, logout };

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

// 创建一个自定义 Hook，方便在组件中使用
export const useAuth = () => {
    return useContext(AuthContext);
};