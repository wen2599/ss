import { useState } from 'react';
import './App.css';

// 动态导入组件
import LotteryNumbers from './components/LotteryNumbers';
import EmailList from './components/EmailList';
import Register from './components/Register';

function App() {
  const [currentPage, setCurrentPage] = useState('lottery'); // 默认显示开奖号码页面

  const renderPage = () => {
    switch (currentPage) {
      case 'lottery':
        return <LotteryNumbers />;
      case 'emails':
        return <EmailList />;
      case 'register':
        return <Register />;
      default:
        return <LotteryNumbers />;
    }
  };

  return (
    <>
      <h1>多功能应用面板</h1>
      <nav>
        <button 
          onClick={() => setCurrentPage('lottery')} 
          className={currentPage === 'lottery' ? 'active' : ''}
        >
          开奖号码
        </button>
        <button 
          onClick={() => setCurrentPage('emails')} 
          className={currentPage === 'emails' ? 'active' : ''}
        >
          邮件列表
        </button>
        <button 
          onClick={() => setCurrentPage('register')} 
          className={currentPage === 'register' ? 'active' : ''}
        >
          用户注册
        </button>
      </nav>

      <main className="page-container">
        {renderPage()}
      </main>

      <footer>
        <p>构建于 {new Date().getFullYear()}</p>
      </footer>
    </>
  );
}

export default App;
