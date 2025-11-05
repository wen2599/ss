import React, { createContext, useState, useContext, useEffect, useCallback } from 'react';

// 从 '../api' 导入命名导出的 'api' 对象
import { api } from '../api'; 

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(() => localStorage.getItem('token')); // 初始化时直接读取
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const storedToken = localStorage.getItem('token');
        if (storedToken) {
            setUser({ isAuthenticated: true });
            setToken(storedToken);
        }
        setLoading(false);
    }, []);

    // login 函数现在接收一个完整的 api 调用函数
    const login = async (loginFunction) => {
        const data = await loginFunction(); // loginFunction 会是例如 api.login(...)
        localStorage.setItem('token', data.token);
        setToken(data.token);
        setUser({ isAuthenticated: true });
    };

    const logout = () => {
        localStorage.removeItem('token');
        setToken(null);
        setUser(null);
    };

    const value = { user, token, login, logout, loading };

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => {
    return useContext(AuthContext);
};