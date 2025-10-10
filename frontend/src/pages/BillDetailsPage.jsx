import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { Container, Paper, Typography, CircularProgress, Alert, Box, Divider } from '@mui/material';

const BillDetailsPage = () => {
    const [email, setEmail] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const { id } = useParams();

    useEffect(() => {
        fetch(`/api/get_emails?id=${id}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应错误');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.emails.length > 0) {
                    setEmail(data.emails[0]);
                } else {
                    throw new Error(data.message || '未找到该邮件');
                }
            })
            .catch(error => {
                console.error('获取邮件详情时出错:', error);
                setError(error.message);
            })
            .finally(() => {
                setLoading(false);
            });
    }, [id]);

    if (loading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', my: 4 }}>
                <CircularProgress />
            </Box>
        );
    }

    if (error) {
        return (
            <Container maxWidth="md" sx={{ mt: 4 }}>
                <Alert severity="error">错误: {error}</Alert>
            </Container>
        );
    }

    if (!email) {
        return (
            <Container maxWidth="md" sx={{ mt: 4 }}>
                <Alert severity="warning">未找到该邮件。</Alert>
            </Container>
        );
    }

    return (
        <Container maxWidth="lg" sx={{ mt: 4 }}>
            <Paper elevation={3} sx={{ p: 3 }}>
                <Typography variant="h4" component="h1" gutterBottom>
                    {email.subject}
                </Typography>
                <Box sx={{ mb: 2, color: 'text.secondary' }}>
                    <Typography variant="body2"><b>发件人:</b> {email.from}</Typography>
                    <Typography variant="body2"><b>收件人:</b> {email.to}</Typography>
                    <Typography variant="body2"><b>日期:</b> {new Date(email.created_at).toLocaleString()}</Typography>
                </Box>
                <Divider sx={{ my: 2 }} />
                <Box
                    className="bill-body"
                    sx={{
                        mt: 2,
                        '& img': { maxWidth: '100%', height: 'auto' }, // Basic responsive images
                        '& table': { borderCollapse: 'collapse', width: '100%' },
                        '& th, & td': { border: '1px solid #ddd', padding: '8px' },
                        '& th': { backgroundColor: '#f2f2f2' }
                    }}
                    dangerouslySetInnerHTML={{ __html: email.html_content }}
                />
            </Paper>
        </Container>
    );
};

export default BillDetailsPage;