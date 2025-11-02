import React from 'react';
import { Outlet } from 'react-router-dom';
import Navbar from '../components/Navbar';

const DashboardLayout = () => {
  return (
    <div className="app-container">
      <Navbar />
      <main>
        {/* 子路由的内容将在这里渲染 */}
        <Outlet />
      </main>
    </div>
  );
};

export default DashboardLayout;