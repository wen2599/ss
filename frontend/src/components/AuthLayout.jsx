import React from 'react';
import './AuthLayout.css';

/**
 * A simple layout component that centers its content in a container
 * with a maximum width. Ideal for authentication forms or other focused content.
 *
 * @param {{children: React.ReactNode}} props
 */
function AuthLayout({ children }) {
  return (
    <div className="auth-layout-container">
      {children}
    </div>
  );
}

export default AuthLayout;