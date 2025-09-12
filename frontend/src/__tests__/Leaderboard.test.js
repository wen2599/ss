import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import Leaderboard from '../components/Leaderboard';
import { getLeaderboard } from '../api';
import { AuthProvider } from '../contexts/AuthContext';

jest.mock('../api');

const mockLeaderboard = [
    { display_id: 'player1', points: 1500 },
    { display_id: 'player2', points: 1000 },
];

test('renders leaderboard', async () => {
    getLeaderboard.mockResolvedValue({ success: true, leaderboard: mockLeaderboard });

    render(
        <AuthProvider>
            <Leaderboard />
        </AuthProvider>
    );

    await waitFor(() => {
        expect(screen.getByText('player1')).toBeInTheDocument();
        expect(screen.getByText('1500')).toBeInTheDocument();
        expect(screen.getByText('player2')).toBeInTheDocument();
        expect(screen.getByText('1000')).toBeInTheDocument();
    });
});
