import React from 'react';
import { Outlet } from 'react-router-dom';
import Navbar from './Navbar';

const MainLayout = () => (
    <div>
        <Navbar />
        <main className="container">
            <Outlet />
        </main>
    </div>
);

export default MainLayout;