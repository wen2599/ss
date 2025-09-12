import React, { createContext, useState, useContext, useCallback } from 'react';

const ErrorContext = createContext(null);

export const ErrorProvider = ({ children }) => {
    const [error, setError] = useState(null);

    const showError = useCallback((message) => {
        setError(message);
    }, []);

    const clearError = useCallback(() => {
        setError(null);
    }, []);

    const value = {
        error,
        showError,
        clearError,
    };

    return <ErrorContext.Provider value={value}>{children}</ErrorContext.Provider>;
};

export const useError = () => {
    const context = useContext(ErrorContext);
    if (!context) {
        throw new Error('useError must be used within an ErrorProvider');
    }
    return context;
};
