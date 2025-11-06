import React from 'react';
import { Navigate, Outlet } from 'react-router-dom';

const ProtectedRoute = ({ children }) => {
    const authToken = localStorage.getItem('authToken');

    if (!authToken) {
        // If no token, redirect to the login page
        return <Navigate to="/login" replace />;
    }

    // If token exists, render the child components
    return children ? children : <Outlet />;
};

export default ProtectedRoute;