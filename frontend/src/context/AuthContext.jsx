import React, { createContext, useState, useContext, useEffect } from 'react';
import { checkSession, logoutUser as apiLogout } from '../api';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true); // Start with loading true

    useEffect(() => {
        const verifySession = async () => {
            try {
                const response = await checkSession();
                if (response.isAuthenticated && response.user) {
                    // Session is valid on the backend, sync the frontend state
                    setUser(response.user);
                    localStorage.setItem('user', JSON.stringify(response.user));
                } else {
                    // No valid session on the backend, clear client-side state
                    setUser(null);
                    localStorage.removeItem('user');
                }
            } catch (error) {
                console.error("Session check failed:", error);
                // On error, assume not authenticated
                setUser(null);
                localStorage.removeItem('user');
            } finally {
                setLoading(false); // Stop loading once the check is complete
            }
        };

        verifySession();
    }, []); // Empty dependency array means this runs once on mount

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

    const value = {
        user,
        isAuthenticated: !!user,
        loading, // Expose loading state
        login,
        logout,
    };

    // Don't render children until session check is complete
    if (loading) {
        return <div>Loading application...</div>; // Or a proper spinner component
    }

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = () => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};