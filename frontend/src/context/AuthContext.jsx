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
                const response = await fetch('/check_session');

                // If the server returns a non-2xx status, treat it as a failure.
                if (!response.ok) {
                    throw new Error(`Server responded with status: ${response.status}`);
                }

                // Try to parse the response as JSON. This will fail for HTML error pages.
                const data = await response.json();

                // If parsing succeeds and user is logged in, set the user.
                if (data.is_logged_in) {
                    setUser(data.user);
                } else {
                    setUser(null);
                }
            } catch (error) {
                // This block catches network errors, non-OK responses, and JSON parsing errors.
                // In all these cases, we assume the user is not logged in.
                // We can log this for debugging, but it's not a critical error for the user.
                console.log("Session check failed, user is not logged in:", error.message);
                setUser(null);
            } finally {
                // Always ensure loading state is turned off.
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