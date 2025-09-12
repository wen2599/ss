import React from 'react';
import { render, screen } from '@testing-library/react';
import Auth from '../components/Auth';
import { AuthProvider } from '../contexts/AuthContext';

test('renders login form by default', () => {
  render(
    <AuthProvider>
      <Auth onClose={() => {}} onLoginSuccess={() => {}} />
    </AuthProvider>
  );

  expect(screen.getByRole('heading', { name: /登录/i })).toBeInTheDocument();
  expect(screen.getByLabelText(/手机号/i)).toBeInTheDocument();
  expect(screen.getByLabelText(/密码/i)).toBeInTheDocument();
  expect(screen.getByRole('button', { name: /登录/i })).toBeInTheDocument();
});
