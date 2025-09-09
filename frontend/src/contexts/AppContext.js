import React, { createContext, useState, useEffect, useContext, useCallback } from 'react';
import { matchmake, getRoomState, startGame, checkSession, logout as apiLogout } from '../api';

const AppContext = createContext(null);

export const AppProvider = ({ children }) => {
  const [currentUser, setCurrentUser] = useState(null);
  const [roomId, setRoomId] = useState(null);
  const [gameState, setGameState] = useState(null);
  const [stateHash, setStateHash] = useState(null);
  const [error, setError] = useState(null); // For user-friendly error messages

  // Check session on initial load
  useEffect(() => {
    const verifySession = async () => {
      try {
        const response = await checkSession();
        if (response.success && response.isAuthenticated) {
          setCurrentUser(response.user);
        }
      } catch (err) {
        setError("无法连接到服务器，请稍后重试。");
      }
    };
    verifySession();
  }, []);

  // Long-polling effect to keep the game state updated
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
            // Always continue polling
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
            // Wait a bit before retrying on network error
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

  const handleMatchmake = useCallback(async (gameMode) => {
    if (!currentUser) {
      setError('请先登录再开始游戏');
      // Maybe also trigger a modal to open in the UI component
      return;
    }
    try {
      const response = await matchmake(gameMode);
      if (response && response.success) {
        setRoomId(response.roomId);
        setError(null); // Clear previous errors
      } else {
        setError(response.message || '匹配失败');
      }
    } catch (err) {
      setError('匹配请求失败，请检查网络连接。');
    }
  }, [currentUser]);

  const handleStartGame = useCallback(async () => {
    if (roomId) {
      try {
        await startGame(roomId);
        // The long-poll will detect the state change
      } catch (err) {
        setError('开始游戏失败。');
      }
    }
  }, [roomId]);

  const handleLogout = useCallback(async () => {
    try {
      await apiLogout();
      setCurrentUser(null);
      setRoomId(null);
      setGameState(null);
      setStateHash(null);
    } catch (err) {
      setError('退出登录失败。');
    }
  }, []);

  const login = useCallback((user) => {
      setCurrentUser(user);
  }, []);

  const updateUser = useCallback(async () => {
    const response = await checkSession();
    if (response.success && response.isAuthenticated) {
        setCurrentUser(response.user);
    }
  }, []);

  const value = {
    currentUser,
    roomId,
    gameState,
    error,
    login,
    logout: handleLogout,
    matchmake: handleMatchmake,
    startGame: handleStartGame,
    updateUser,
    clearError: () => setError(null)
  };

  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
};

export const useAppContext = () => {
  const context = useContext(AppContext);
  if (!context) {
    throw new Error('useAppContext must be used within an AppProvider');
  }
  return context;
};
