import React, { createContext, useContext, useCallback, useState } from 'react';
import { submitHand as apiSubmitHand } from '../api';

const GameContext = createContext(null);

export const GameProvider = ({ children }) => {
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    const submitHand = useCallback(async (gameId, front, middle, back) => {
        setLoading(true);
        setError(null);
        try {
            const response = await apiSubmitHand(gameId, front, middle, back);
            if (!response.success) {
                setError(response.message || '提交手牌失败');
            }
        } catch (err) {
            setError('提交手牌请求失败，请检查网络连接。');
        } finally {
            setLoading(false);
        }
    }, []);

    const value = {
        error,
        loading,
        submitHand,
        clearError: () => setError(null),
    };

    return <GameContext.Provider value={value}>{children}</GameContext.Provider>;
};

export const useGame = () => {
    const context = useContext(GameContext);
    if (!context) {
        throw new Error('useGame must be used within a GameProvider');
    }
    return context;
};
