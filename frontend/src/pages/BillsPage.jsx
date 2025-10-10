import React, { useState, useEffect } from 'react';
import { Link as RouterLink } from 'react-router-dom';
import { Container, List, ListItem, ListItemText, Typography, CircularProgress, Alert, Box, Paper, Link } from '@mui/material';

const BillsPage = () => {
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        fetch('/api/get_emails')
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应错误');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    setEmails(data.emails);
                } else {
                    throw new Error(data.message || '获取邮件失败');
                }
            })
            .catch(error => {
                console.error('获取邮件列表时出错:', error);
                setError(error.message);
            })
            .finally(() => {
                setLoading(false);
            });
    }, []);

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

    return (
        <Container maxWidth="md" sx={{ mt: 4 }}>
            <Typography variant="h4" component="h1" gutterBottom>
                账单中心
            </Typography>
            <Paper elevation={2}>
                <List>
                    {emails.length > 0 ? emails.map(email => (
                        <ListItem
                            key={email.id}
                            button
                            component={RouterLink}
                            to={`/bill/${email.id}`}
                            divider
                        >
                            <ListItemText
                                primary={email.subject}
                                secondary={`发件人: ${email.from} - 日期: ${new Date(email.created_at).toLocaleString()}`}
                            />
                        </ListItem>
                    )) : (
                        <ListItem>
                            <ListItemText primary="没有找到账单邮件。" />
                        </ListItem>
                    )}
                </List>
            </Paper>
        </Container>
    );
};

export default BillsPage;