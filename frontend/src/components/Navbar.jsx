import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const Navbar = () => {
    const { user, logout } = useAuth();

    return (
        <nav className="navbar">
            <Link to="/"><h1>电子邮件投注处理器</h1></Link>
            <ul>
                {user ? (
                    <>
                        <li><span>{user.email}</span></li>
                        <li>
                            <button onClick={logout}>登出</button>
                        </li>
                    </>
                ) : (
                    <>
                        <li><Link to="/login">登录</Link></li>
                        <li><Link to="/register">注册</Link></li>
                    </>
                )}
            </ul>
        </nav>
    );
};

export default Navbar;
