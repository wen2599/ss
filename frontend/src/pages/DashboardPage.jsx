import React from 'react';
import { Outlet, Navigate, useLocation } from 'react-router-dom';
import Navbar from '../components/Navbar';

const DashboardPage = () => {
  const location = useLocation();

  return (
    <div className="container">
      <Navbar />
      <main>
        {/* If user is at /dashboard, redirect to /dashboard/emails */}
        {location.pathname === '/dashboard' && <Navigate to="/dashboard/emails" replace />}
        <Outlet />
      </main>
    </div>
  );
};

export default DashboardPage;
