import React from 'react';

function AuthLayout({ children }) {
  return (
    <div style={{ width: '100%', maxWidth: '420px', margin: '2rem auto' }}>
      {children}
    </div>
  );
}

export default AuthLayout;
