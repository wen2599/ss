import React, { createContext, useContext, useState, useEffect } from 'react';
import { api } from '../api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(localStorage.getItem('token'));

    useEffect(() => {
        if (token) {
            // 如果有token，可以从后端获取用户信息并设置user
            // 这里为了简化，我们仅将token作为登录凭证
            setUser({ token }); // 假设user对象至少包含token
            localStorage.setItem('token', token);
        } else {
            setUser(null);
            localStorage.removeItem('token');
        }
    }, [token]);

    const login = async (email, password) => {
        const { token } = await api.login({ email, password });
        setToken(token);
    };

    const register = async (email, password) => {
        const { token } = await api.register({ email, password });
        setToken(token);
    };

    const logout = () => {
        setToken(null);
    };

    return (
        <AuthContext.Provider value={{ user, token, login, register, logout }}>
            {children}
        </AuthContext.Provider>
    );
}

export const useAuth = () => useContext(AuthContext);