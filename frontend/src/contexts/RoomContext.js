import React, { createContext, useState, useEffect, useContext, useCallback } from 'react';
import { getRoomState, matchmake as apiMatchmake, startGame as apiStartGame } from '../api';
import { useAuth } from './AuthContext';

const RoomContext = createContext(null);

export const RoomProvider = ({ children }) => {
    const { currentUser } = useAuth();
    const [roomId, setRoomId] = useState(null);
    const [gameState, setGameState] = useState(null);
    const [stateHash, setStateHash] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (roomId && currentUser) {
            let active = true;

            const longPoll = async (hash) => {
                if (!active) return;
                try {
                    const response = await getRoomState(roomId, hash);
                    if (!active) return;

                    if (response.success) {
                        if (!response.no_change) {
                            setGameState(response);
                            setStateHash(response.state_hash);
                        }
                        longPoll(response.state_hash || hash);
                    } else {
                        setError(response.message || '获取游戏状态失败');
                        if (active) {
                            setRoomId(null);
                            setGameState(null);
                        }
                    }
                } catch (error) {
                    setError('与服务器的连接中断。');
                    if (active) {
                        setTimeout(() => longPoll(hash), 5000);
                    }
                }
            };

            const init = async () => {
                const initialState = await getRoomState(roomId);
                if (active && initialState.success) {
                    setGameState(initialState);
                    setStateHash(initialState.state_hash);
                    longPoll(initialState.state_hash);
                } else if (active) {
                    setError(initialState.message || '无法获取初始游戏状态');
                    setRoomId(null);
                }
            };

            init();

            return () => {
                active = false;
            };
        }
    }, [roomId, currentUser]);

    const matchmake = useCallback(async (gameMode) => {
        if (!currentUser) {
            setError('请先登录再开始游戏');
            return;
        }
        try {
            const response = await apiMatchmake(gameMode);
            if (response && response.success) {
                setRoomId(response.roomId);
                setError(null);
            } else {
                setError(response.message || '匹配失败');
            }
        } catch (err) {
            setError('匹配请求失败，请检查网络连接。');
        }
    }, [currentUser]);

    const startGame = useCallback(async () => {
        if (roomId) {
            try {
                await apiStartGame(roomId);
            } catch (err) {
                setError('开始游戏失败。');
            }
        }
    }, [roomId]);

    const leaveRoom = useCallback(() => {
        setRoomId(null);
        setGameState(null);
        setStateHash(null);
    }, []);

    const value = {
        roomId,
        gameState,
        error,
        matchmake,
        startGame,
        leaveRoom,
        clearError: () => setError(null)
    };

    return <RoomContext.Provider value={value}>{children}</RoomContext.Provider>;
};

export const useRoom = () => {
    const context = useContext(RoomContext);
    if (!context) {
        throw new Error('useRoom must be used within a RoomProvider');
    }
    return context;
};
