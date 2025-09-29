import React, { createContext, useState, useEffect, useContext } from 'react';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Check session on initial load
    const verifySession = async () => {
      try {
        const response = await fetch('backend/index.php?action=check_session');
        const data = await response.json();
        if (data.isAuthenticated) {
          setUser(data.user);
        }
      } catch (error) {
        console.error("Session check failed", error);
      } finally {
        setIsLoading(false);
      }
    };
    verifySession();
  }, []);

  const login = (userData) => {
    setUser(userData);
  };

  const logout = async () => {
    await fetch('backend/index.php?action=logout');
    setUser(null);
  };

  const authValue = {
    user,
    isAuthenticated: !!user,
    isLoading,
    login,
    logout,
  };

  return (
    <AuthContext.Provider value={authValue}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
