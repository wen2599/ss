import React, { createContext, useState, useContext } from 'react';
import * as api from '../services/api';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    const [token, setToken] = useState(localStorage.getItem('authToken'));

    const login = async (email, password) => {
        const response = await api.login(email, password);
        if (response.success && response.token) {
            setToken(response.token);
            localStorage.setItem('authToken', response.token);
        }
        return response;
    };

    const logout = () => {
        setToken(null);
        localStorage.removeItem('authToken');
    };

    const isAuthenticated = () => !!token;

    return (
        <AuthContext.Provider value={{ token, login, logout, isAuthenticated }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => {
    return useContext(AuthContext);
};
