import { Outlet, Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const Layout = () => {
    const { token, logout } = useAuth();
    const navigate = useNavigate();

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    return (
        <div>
            <header style={{ background: '#eee', padding: '1rem', display: 'flex', justifyContent: 'space-between' }}>
                <Link to="/"><h1>开奖系统</h1></Link>
                <nav>
                    {token ? (
                        <button onClick={handleLogout}>登出</button>
                    ) : (
                        <>
                            <Link to="/login" style={{ marginRight: '1rem' }}>登录</Link>
                            <Link to="/register">注册</Link>
                        </>
                    )}
                </nav>
            </header>
            <main style={{ padding: '1rem' }}>
                <Outlet /> {/* 子路由对应的页面会在这里渲染 */}
            </main>
        </div>
    );
};

export default Layout;