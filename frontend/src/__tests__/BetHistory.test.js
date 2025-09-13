import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import BetHistory from '../components/BetHistory';
import { ErrorProvider } from '../contexts/ErrorContext';
import request from '../api';

jest.mock('../api');

const mockBets = [
  { id: 1, draw_number: '2025001', bet_numbers: '[1,2,3,4,5,6]', bet_amount: 10, status: 'won', winnings: 100000 },
  { id: 2, draw_number: '2025002', bet_numbers: '[7,8,9,10,11,12]', bet_amount: 20, status: 'lost', winnings: 0 },
];

const renderWithProvider = (component) => {
  return render(
    <ErrorProvider>
      {component}
    </ErrorProvider>
  );
};

test('renders bet history', async () => {
  request.mockResolvedValue({ bets: mockBets });

  renderWithProvider(<BetHistory />);

  expect(screen.getByText(/Loading bet history.../i)).toBeInTheDocument();

  await waitFor(() => {
    expect(screen.getByText(/Draw 2025001/i)).toBeInTheDocument();
    expect(screen.getByText(/Draw 2025002/i)).toBeInTheDocument();
  });

  expect(screen.getByText(/Winnings: 100000/i)).toBeInTheDocument();
});
