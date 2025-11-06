import React, { createContext, useState, useContext, useEffect, useCallback } from 'react';
import { loginUser, registerUser } from '../services/api';

const AuthContext = createContext(null);

export const useAuth = () => useContext(AuthContext);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [token, setToken] = useState(localStorage.getItem('authToken'));
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (token) {
      // In a real app, you might want to verify the token with the backend here
      // For simplicity, we'll just decode the user info from localStorage
      const userEmail = localStorage.getItem('userEmail');
      setUser({ email: userEmail });
    }
    setIsLoading(false);
  }, [token]);

  const login = useCallback(async (email, password) => {
    const data = await loginUser(email, password);
    setToken(data.token);
    setUser({ email: data.email });
    localStorage.setItem('authToken', data.token);
    localStorage.setItem('userEmail', data.email);
  }, []);

  const register = useCallback(async (email, password) => {
    await registerUser(email, password);
    // Optionally, you can log the user in directly after registration
  }, []);

  const logout = useCallback(() => {
    setUser(null);
    setToken(null);
    localStorage.removeItem('authToken');
    localStorage.removeItem('userEmail');
  }, []);

  const value = {
    user,
    token,
    isAuthenticated: !!token,
    isLoading,
    login,
    register,
    logout,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};
