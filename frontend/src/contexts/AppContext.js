import React from 'react';
import { AuthProvider } from './AuthContext';
import { ErrorProvider } from './ErrorContext';

export const AppProvider = ({ children }) => {
    return (
        <ErrorProvider>
            <AuthProvider>
                {children}
            </AuthProvider>
        </ErrorProvider>
    );
};
