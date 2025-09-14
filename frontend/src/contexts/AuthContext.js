import React, { createContext, useState, useEffect, useContext, useCallback } from 'react';
import { checkSession, login as apiLogin, register as apiRegister, logout as apiLogout } from '../api';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    const [currentUser, setCurrentUser] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        const verifySession = async () => {
            try {
                const data = await checkSession();
                if (data.isAuthenticated) {
                    setCurrentUser(data.user);
                }
            } catch (err) {
                setError(err.message);
            }
        };
        verifySession();
    }, []);

    const login = useCallback(async (phone, password) => {
        try {
            const data = await apiLogin(phone, password);
            setCurrentUser(data.user);
            setError(null);
            return data;
        } catch (err) {
            setError(err.message);
            throw err;
        }
    }, []);

    const register = useCallback(async (phone, password) => {
        try {
            const data = await apiRegister(phone, password);
            setError(null);
            return data;
        } catch (err) {
            setError(err.message);
            throw err;
        }
    }, []);

    const logout = useCallback(async () => {
        try {
            await apiLogout();
            setCurrentUser(null);
        } catch (err) {
            setError('退出登录失败。');
        }
    }, []);

    const updateUser = useCallback(async () => {
        const response = await checkSession();
        if (response.success && response.isAuthenticated) {
            setCurrentUser(response.user);
        }
    }, []);

    const value = {
        currentUser,
        error,
        login,
        register,
        logout,
        updateUser,
        clearError: () => setError(null)
    };

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = () => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};
