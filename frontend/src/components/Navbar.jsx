import React, { useContext } from 'react';
import { NavLink } from 'react-router-dom';
import { ThemeContext } from '../ThemeContext';
import AppBar from '@mui/material/AppBar';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import IconButton from '@mui/material/IconButton';
import Box from '@mui/material/Box';
import { styled, useTheme } from '@mui/material/styles';
import Brightness4Icon from '@mui/icons-material/Brightness4';
import Brightness7Icon from '@mui/icons-material/Brightness7';

const StyledNavLink = styled(NavLink)(({ theme }) => ({
  textDecoration: 'none',
  color: 'inherit',
  '&.active': {
    textDecoration: 'underline',
    textUnderlineOffset: '4px',
  },
}));

const Navbar = () => {
  const theme = useTheme();
  const colorMode = useContext(ThemeContext);

  return (
    <AppBar position="static">
      <Toolbar>
        <Typography variant="h6" component="div" sx={{ flexGrow: 1 }}>
          <StyledNavLink to="/">
            应用中心
          </StyledNavLink>
        </Typography>
        <Button color="inherit" component={StyledNavLink} to="/">
          开奖
        </Button>
        <Button color="inherit" component={StyledNavLink} to="/bills">
          账单
        </Button>
        <Button color="inherit" component={StyledNavLink} to="/login">
          登录
        </Button>
        <Button color="inherit" component={StyledNavLink} to="/register">
          注册
        </Button>
        <Box>
          <IconButton sx={{ ml: 1 }} onClick={colorMode.toggleTheme} color="inherit" aria-label="toggle theme">
            {theme.palette.mode === 'dark' ? <Brightness7Icon /> : <Brightness4Icon />}
          </IconButton>
        </Box>
      </Toolbar>
    </AppBar>
  );
};

export default Navbar;