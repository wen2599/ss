import { createContext, useState, useEffect, useContext } from 'react';
import type { ReactNode } from 'react';
import * as api from '../api';

interface User {
  id: number;
  username: string;
  phone: string;
  points: number;
}

interface AuthContextType {
  isAuthenticated: boolean;
  user: User | null;
  login: (credentials: object) => Promise<void>;
  logout: () => Promise<void>;
  register: (credentials: object) => Promise<void>;
  isLoading: boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider = ({ children }: { children: ReactNode }) => {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Check for an active session when the app loads
    const checkLoggedIn = async () => {
      try {
        const response = await api.checkSession();
        if (response.data.loggedIn) {
          setUser(response.data.user);
        }
      } catch (error) {
        console.error("Session check failed", error);
      } finally {
        setIsLoading(false);
      }
    };
    checkLoggedIn();
  }, []);

  const login = async (credentials: object) => {
    const response = await api.login(credentials);
    if (response.data.success) {
      setUser(response.data.user);
    } else {
      throw new Error(response.data.message || 'Login failed');
    }
  };

  const register = async (credentials: object) => {
    const response = await api.register(credentials);
    if (!response.data.success) {
      throw new Error(response.data.message || 'Registration failed');
    }
  };

  const logout = async () => {
    await api.logout();
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ isAuthenticated: !!user, user, login, logout, register, isLoading }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
