import React, { createContext, useState, useContext, useEffect } from 'react';
import { api } from '../api';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(() => localStorage.getItem('token'));
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (token) {
            setUser({ isAuthenticated: true });
        }
        setLoading(false);
    }, [token]);

    const login = async (email, password) => {
        try {
            const data = await api.login({ email, password });
            localStorage.setItem('token', data.token);
            setToken(data.token);
            setUser({ isAuthenticated: true });
            return data;
        } catch (error) {
            console.error("Login failed:", error);
            throw error;
        }
    };

    const register = async (email, password) => {
        try {
            const data = await api.register({ email, password });
            return data;
        } catch (error) {
            console.error("Registration failed:", error);
            throw error;
        }
    };

    const logout = () => {
        localStorage.removeItem('token');
        setToken(null);
        setUser(null);
    };

    const value = { user, token, login, register, logout, loading };

    return (
        <AuthContext.Provider value={value}>
            {!loading && children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => {
    return useContext(AuthContext);
};
