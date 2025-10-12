import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext.jsx';

const ProtectedRoute = ({ children }) => {
    const { isAuthenticated } = useAuth();

    if (!isAuthenticated) {
        // If user is not authenticated, redirect to the login page
        return <Navigate to="/login" />;
    }

    return children;
};

export default ProtectedRoute;
