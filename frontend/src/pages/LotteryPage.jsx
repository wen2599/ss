import React, { useState, useEffect } from 'react';
import { Container, Card, CardContent, Typography, Button, CircularProgress, Alert, Box } from '@mui/material';

const LotteryPage = () => {
  const [lotteryData, setLotteryData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchData = () => {
    setLoading(true);
    setError(null);
    fetch('/api/getLotteryNumber')
      .then(response => {
        if (!response.ok) {
          return response.json().then(err => {
            throw new Error(err.message || `网络错误 (状态: ${response.status})`);
          }).catch(() => {
            throw new Error(`网络错误 (状态: ${response.status})`);
          });
        }
        return response.json();
      })
      .then(data => {
        if (data && data.winning_numbers) {
          setLotteryData(data);
        } else {
          setLotteryData({ winning_numbers: '等待开奖', issue_number: '--', created_at: '--' });
        }
      })
      .catch(error => {
        console.error('获取开奖数据时出错:', error);
        setError(error.message || '获取数据失败');
      })
      .finally(() => {
        setLoading(false);
      });
  };

  useEffect(() => {
    fetchData();
    const intervalId = setInterval(fetchData, 15000);
    return () => clearInterval(intervalId);
  }, []);

  const renderContent = () => {
    if (loading && !lotteryData) {
      return (
        <Box sx={{ display: 'flex', justifyContent: 'center', my: 4 }}>
          <CircularProgress />
        </Box>
      );
    }

    if (error) {
      return (
        <Alert severity="error" action={
          <Button color="inherit" size="small" onClick={fetchData}>
            重试
          </Button>
        }>
          {error}
        </Alert>
      );
    }

    return (
      <Card elevation={3} sx={{ textAlign: 'center' }}>
        <CardContent>
          <Typography variant="h6" color="text.secondary">
            期号: {lotteryData?.issue_number || '--'}
          </Typography>
          <Typography variant="h2" component="p" sx={{ my: 2, letterSpacing: 3, color: 'primary.main' }}>
            {lotteryData?.winning_numbers || 'N/A'}
          </Typography>
          <Typography variant="caption" color="text.secondary">
            最后更新: {lotteryData?.created_at ? new Date(lotteryData.created_at).toLocaleString() : '--'}
          </Typography>
        </CardContent>
      </Card>
    );
  };

  return (
    <Container maxWidth="sm" sx={{ textAlign: 'center', mt: 4 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        最新开奖号码
      </Typography>
      {renderContent()}
      <Typography variant="caption" display="block" color="text.secondary" sx={{ mt: 4 }}>
        请以官方开奖结果为准
      </Typography>
    </Container>
  );
};

export default LotteryPage;