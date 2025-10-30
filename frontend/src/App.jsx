// frontend/src/App.jsx
import React from 'react';
import LotteryDisplay from './components/LotteryDisplay';
import './App.css';

function App() {
  return (
    <div className="App">
      <header className="App-header">
        <h1>Lottery Information Center</h1>
      </header>
      <main>
        <LotteryDisplay />
      </main>
    </div>
  );
}

export default App;
