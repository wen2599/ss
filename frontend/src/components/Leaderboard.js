import React, { useState, useEffect } from 'react';
import { getLeaderboard } from '../api';
import './Leaderboard.css';

const Leaderboard = () => {
    const [leaderboard, setLeaderboard] = useState([]);

    useEffect(() => {
        const fetchLeaderboard = async () => {
            const response = await getLeaderboard();
            if (response.success) {
                setLeaderboard(response.leaderboard);
            }
        };
        fetchLeaderboard();
    }, []);

    return (
        <div className="leaderboard-container">
            <h2>Leaderboard</h2>
            <ol className="leaderboard-list">
                {leaderboard.map((player, index) => (
                    <li key={index} className="leaderboard-item">
                        <span>{player.display_id}</span>
                        <span>{player.points}</span>
                    </li>
                ))}
            </ol>
        </div>
    );
};

export default Leaderboard;
