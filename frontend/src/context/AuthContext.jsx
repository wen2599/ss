import React, { createContext, useState, useContext, useEffect } from 'react';
import axios from 'axios';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true); // To check initial session status

    useEffect(() => {
        // Check for an existing session when the app loads
        const checkLoggedIn = async () => {
            try {
                const response = await axios.get('/api/check_session.php');
                if (response.data.loggedIn) {
                    setUser(response.data.user);
                }
            } catch (error) {
                console.error("Could not check session", error);
            } finally {
                setLoading(false);
            }
        };

        checkLoggedIn();
    }, []);

    const login = (userData) => {
        setUser(userData);
    };

    const logout = async () => {
        await axios.post('/api/logout.php');
        setUser(null);
    };

    const value = {
        user,
        setUser,
        login,
        logout,
        isAuthenticated: !!user,
        loading,
    };

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => {
    return useContext(AuthContext);
};
