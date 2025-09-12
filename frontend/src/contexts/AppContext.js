import React from 'react';
import { AuthProvider } from './AuthContext';
import { RoomProvider } from './RoomContext';
import { GameProvider } from './GameContext';
import { ErrorProvider } from './ErrorContext';

export const AppProvider = ({ children }) => {
    return (
        <ErrorProvider>
            <AuthProvider>
                <RoomProvider>
                    <GameProvider>
                        {children}
                    </GameProvider>
                </RoomProvider>
            </AuthProvider>
        </ErrorProvider>
    );
};
