import React, { createContext, useState, useEffect, useContext, useCallback } from 'react';
import { checkSession, login as apiLogin, register as apiRegister, logout as apiLogout } from '../api';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    const [currentUser, setCurrentUser] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        const verifySession = async () => {
            try {
                const response = await checkSession();
                if (response.success && response.isAuthenticated) {
                    setCurrentUser(response.user);
                }
            } catch (err) {
                setError("无法连接到服务器，请稍后重试。");
            }
        };
        verifySession();
    }, []);

    const login = useCallback(async (phone, password) => {
        try {
            const response = await apiLogin(phone, password);
            if (response.success) {
                setCurrentUser(response.user);
                setError(null);
                return response;
            } else {
                setError(response.message);
                return response;
            }
        } catch (err) {
            setError('登录请求失败，请检查网络连接。');
            return { success: false, message: '登录请求失败，请检查网络连接。' };
        }
    }, []);

    const register = useCallback(async (phone, password) => {
        try {
            const response = await apiRegister(phone, password);
            if (response.success) {
                setError(null);
                return response;
            } else {
                setError(response.message);
                return response;
            }
        } catch (err) {
            setError('注册请求失败，请检查网络连接。');
            return { success: false, message: '注册请求失败，请检查网络连接。' };
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
