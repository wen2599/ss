import React from 'react';
import { Outlet } from 'react-router-dom';
import Navbar from './Navbar';
import '../App.css';

/**
 * The main layout component for the application.
 * It provides the consistent structure for all pages, including the top
 * navigation bar and the main content area where routed pages are rendered.
 */
function MainLayout() {
  return (
    <div className="app-container">
      <Navbar />
      <main className="main-content">
        {/* The Outlet component from React Router renders the matched child route's component. */}
        <Outlet />
      </main>
    </div>
  );
}

export default MainLayout;