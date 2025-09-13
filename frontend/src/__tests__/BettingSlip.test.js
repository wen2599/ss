import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import BettingSlip from '../components/BettingSlip';
import { ErrorProvider } from '../contexts/ErrorContext';
import request from '../api';

jest.mock('../api');

const renderWithProvider = (component) => {
  return render(
    <ErrorProvider>
      {component}
    </ErrorProvider>
  );
};

test('places a bet successfully', async () => {
  request.mockResolvedValue({ success: true });
  window.alert = jest.fn();

  renderWithProvider(<BettingSlip />);

  fireEvent.change(screen.getByLabelText(/Numbers/i), { target: { value: '1,2,3,4,5,6' } });
  fireEvent.change(screen.getByLabelText(/Amount/i), { target: { value: '10' } });

  fireEvent.click(screen.getByText(/Place Bet/i));

  await waitFor(() => {
    expect(request).toHaveBeenCalledWith('place_bet', 'POST', {
      draw_id: 1,
      bet_type: 'single',
      bet_numbers: [1, 2, 3, 4, 5, 6],
      bet_amount: 10,
    });
  });

  expect(window.alert).toHaveBeenCalledWith('Bet placed successfully!');
});
