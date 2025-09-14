import React, { useState, useEffect } from 'react';
import { getChannelInfo } from '../api';

interface ChannelData {
  title: string;
  description: string;
  invite_link: string;
  members_count: number;
}

const ChannelInfo: React.FC = () => {
  const [channelInfo, setChannelInfo] = useState<ChannelData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchInfo = async () => {
      try {
        setLoading(true);
        const response = await getChannelInfo();
        if (response.data.success) {
          setChannelInfo(response.data.data);
        } else {
          setError(response.data.message);
        }
      } catch (err: any) {
        setError(err.response?.data?.message || 'Failed to fetch channel info.');
      } finally {
        setLoading(false);
      }
    };
    fetchInfo();
  }, []);

  if (loading) return <p>Loading channel info...</p>;
  if (error) return <p style={{ color: 'red' }}>Error: {error}</p>;

  return (
    <div style={{ marginTop: '20px', border: '1px solid #ccc', padding: '10px' }}>
      <h3>Channel Information</h3>
      {channelInfo && channelInfo.title ? (
        <>
          <h4>{channelInfo.title}</h4>
          <p>{channelInfo.description}</p>
          {channelInfo.invite_link && <a href={channelInfo.invite_link} target="_blank" rel="noopener noreferrer">Join Channel</a>}
          {channelInfo.members_count && <p>Members: {channelInfo.members_count}</p>}
        </>
      ) : (
        <p>No channel information available.</p>
      )}
    </div>
  );
};

export default ChannelInfo;
