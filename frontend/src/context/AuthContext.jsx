import React, { createContext, useState, useContext, useEffect } from 'react';
import { logoutUser as apiLogout } from '../api'; // Import the new logout API function

// 1. Create the context
const AuthContext = createContext(null);

// 2. Create the AuthProvider component
export const AuthProvider = ({ children }) => {
    // Check for user data in localStorage to persist session across page refreshes
    const [user, setUser] = useState(() => {
        const storedUser = localStorage.getItem('user');
        try {
            return storedUser ? JSON.parse(storedUser) : null;
        } catch (error) {
            console.error("Failed to parse user from localStorage", error);
            return null;
        }
    });

    const login = (userData) => {
        setUser(userData);
        localStorage.setItem('user', JSON.stringify(userData));
    };

    const logout = async () => {
        try {
            await apiLogout(); // Call the backend to destroy the session
        } catch (error) {
            console.error("Logout failed on server:", error);
        } finally {
            // Always clear client-side state
            setUser(null);
            localStorage.removeItem('user');
        }
    };

    // The value provided to the consumer components
    const value = {
        user,
        isAuthenticated: !!user,
        login,
        logout,
    };

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

// 3. Create a custom hook for easy consumption of the context
export const useAuth = () => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};
