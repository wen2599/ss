import React, { useState, useEffect } from 'react';
import { getFriends, acceptFriend } from '../api';
import './FriendsList.css';

const FriendsList = () => {
    const [friends, setFriends] = useState([]);

    const fetchFriends = async () => {
        const response = await getFriends();
        if (response.success) {
            setFriends(response.friends);
        }
    };

    useEffect(() => {
        fetchFriends();
    }, []);

    const handleAcceptFriend = async (friendId) => {
        const response = await acceptFriend(friendId);
        if (response.success) {
            fetchFriends();
        }
    };

    return (
        <div className="friends-list-container">
            <h2>Friends</h2>
            <div className="friends-list">
                {friends.map(friend => (
                    <div key={friend.id} className="friend-item">
                        <span>{friend.display_id}</span>
                        {friend.status === 'pending' && (
                            <button onClick={() => handleAcceptFriend(friend.id)}>Accept</button>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
};

export default FriendsList;
