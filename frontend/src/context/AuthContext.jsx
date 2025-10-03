import React, { createContext, useState, useEffect, useContext } from 'react';

const AuthContext = createContext(null);

export const useAuth = () => useContext(AuthContext);

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        // Check for an active session when the app loads
        const checkUserSession = async () => {
            try {
                // The '/check_session' path will be handled by the worker/proxy
                const response = await fetch('/check_session');
                const data = await response.json();
                if (response.ok && data.is_logged_in) {
                    setUser(data.user);
                }
            } catch (error) {
                console.error("Session check failed:", error);
                // Handle error, maybe show a notification to the user
            } finally {
                setIsLoading(false);
            }
        };

        checkUserSession();
    }, []);

    const login = (userData) => {
        setUser(userData);
    };

    const logout = async () => {
        try {
            await fetch('/logout', { method: 'POST' });
        } catch (error) {
            console.error("Logout failed:", error);
        } finally {
            setUser(null);
        }
    };

    const value = {
        user,
        isAuthenticated: !!user,
        isLoading,
        login,
        logout,
    };

    return (
        <AuthContext.Provider value={value}>
            {!isLoading && children}
        </AuthContext.Provider>
    );
};