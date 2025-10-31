import React, { useState } from 'react';
import LotteryResults from './components/LotteryResults';
import EmailViewer from './components/EmailViewer';
import './App.css';

function App() {
  const [activeView, setActiveView] = useState('lottery');

  return (
    <div className="app">
      <nav>
        <button 
          onClick={() => setActiveView('lottery')} 
          className={activeView === 'lottery' ? 'active' : ''}
        >
          开奖结果
        </button>
        <button 
          onClick={() => setActiveView('email')} 
          className={activeView === 'email' ? 'active' : ''}
        >
          邮件查看
        </button>
      </nav>
      <main>
        {activeView === 'lottery' && <LotteryResults />}
        {activeView === 'email' && <EmailViewer />}
      </main>
    </div>
  );
}

export default App;