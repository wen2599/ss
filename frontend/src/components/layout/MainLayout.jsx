import React from 'react';
import { Outlet } from 'react-router-dom';
import Navbar from './Navbar';

function MainLayout() {
  return (
    <>
      <Navbar />
      <main>
        <Outlet /> {/* 子路由对应的页面组件将在这里渲染 */}
      </main>
    </>
  );
}

export default MainLayout;