import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import FriendsList from '../components/FriendsList';
import { getFriends } from '../api';
import { AuthProvider } from '../contexts/AuthContext';

jest.mock('../api');

const mockFriends = [
    { id: 2, display_id: 'friend1', status: 'accepted' },
    { id: 3, display_id: 'friend2', status: 'pending' },
];

test('renders friends list', async () => {
    getFriends.mockResolvedValue({ success: true, friends: mockFriends });

    render(
        <AuthProvider>
            <FriendsList />
        </AuthProvider>
    );

    await waitFor(() => {
        expect(screen.getByText('friend1')).toBeInTheDocument();
        expect(screen.getByText('friend2')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Accept/i })).toBeInTheDocument();
    });
});
