import React, { createContext, useState, useContext, useEffect } from 'react';
import { api } from '../api';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(localStorage.getItem('token'));
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (token) {
            // 这里可以加一个 /api/users/me 的接口来验证 token 并获取用户信息
            // 暂时简化处理，有 token 就认为已登录
            setUser({ isAuthenticated: true }); 
        }
        setLoading(false);
    }, [token]);

    const login = async (credentials) => {
        const data = await api.login(credentials);
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
            {!loading && children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => {
    return useContext(AuthContext);
};