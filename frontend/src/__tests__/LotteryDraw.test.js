import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import LotteryDraw from '../components/LotteryDraw';
import request from '../api';

jest.mock('../api');

const mockDraws = [
  { id: 1, draw_number: '2025001', draw_date: '2025-01-01', status: 'settled', winning_numbers: '[1,2,3,4,5,6]' },
  { id: 2, draw_number: '2025002', draw_date: '2025-01-02', status: 'open', winning_numbers: null },
];

test('renders lottery draws', async () => {
  request.mockResolvedValue({ draws: mockDraws });

  render(<LotteryDraw />);

  expect(screen.getByText(/Loading draws.../i)).toBeInTheDocument();

  await waitFor(() => {
    expect(screen.getByText(/Draw 2025001/i)).toBeInTheDocument();
    expect(screen.getByText(/Draw 2025002/i)).toBeInTheDocument();
  });

  expect(screen.getByText(/Winning Numbers: 1, 2, 3, 4, 5, 6/i)).toBeInTheDocument();
});
