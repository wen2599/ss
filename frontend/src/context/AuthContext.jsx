import React, { createContext, useState, useContext, useEffect } from 'react';
import { jwtDecode } from 'jwt-decode'; // A lightweight library to decode JWTs

// 1. Create the context
const AuthContext = createContext(null);

// 2. Create the AuthProvider component
export const AuthProvider = ({ children }) => {
    const [token, setToken] = useState(localStorage.getItem('authToken'));
    const [user, setUser] = useState(null);

    useEffect(() => {
        // When the token changes, update the user state
        if (token) {
            try {
                const decodedUser = jwtDecode(token);
                setUser(decodedUser);
                localStorage.setItem('authToken', token);
            } catch (error) {
                console.error("Invalid token:", error);
                setUser(null);
                localStorage.removeItem('authToken');
            }
        } else {
            setUser(null);
            localStorage.removeItem('authToken');
        }
    }, [token]);

    const login = (newToken) => {
        setToken(newToken);
    };

    const logout = () => {
        setToken(null);
    };

    // The value provided to the consumer components
    const value = {
        token,
        user,
        isAuthenticated: !!user, // Double negation converts object to boolean
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
