import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const ProtectedRoute = ({ children }) => {
    const { isAuthenticated, isLoading } = useAuth();
    const location = useLocation();

    // If the authentication status is still loading, don't render anything yet
    // This prevents a flash of the login page before the session is checked
    if (isLoading) {
        return <div>Loading...</div>; // Or a spinner component
    }

    // If the user is not authenticated, redirect them to the login page
    if (!isAuthenticated) {
        // Pass the current location so we can redirect back after login
        return <Navigate to="/login" state={{ from: location }} replace />;
    }

    // If the user is authenticated, render the child components
    return children;
};

export default ProtectedRoute;