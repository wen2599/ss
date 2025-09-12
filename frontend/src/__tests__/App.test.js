import React from 'react';
import { render, screen } from '@testing-library/react';
import App from '../App';
import { AppProvider } from '../contexts/AppContext';

test('renders the main application heading', () => {
  render(
    <AppProvider>
      <App />
    </AppProvider>
  );
  const headingElement = screen.getByText(/十三张/i);
  expect(headingElement).toBeInTheDocument();
});
