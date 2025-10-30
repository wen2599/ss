import React, { useState, useEffect } from 'react';
import { getNumbers } from './api'; // 导入新的 API 函数
import './App.css';

function App() {
    const [numbers, setNumbers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchNumbers = async () => {
            try {
                setLoading(true);
                const data = await getNumbers(); // 使用新的 API 函数
                setNumbers(data);
                setError(null);
            } catch (error) {
                console.error("Fetching numbers failed in component:", error);
                setError('无法加载开奖号码，请稍后再试。');
            } finally {
                setLoading(false);
            }
        };

        fetchNumbers();

        // 设置定时器，每30秒刷新一次数据
        const intervalId = setInterval(fetchNumbers, 30000);

        // 组件卸载时清除定时器
        return () => clearInterval(intervalId);
    }, []);

    return (
        <div className="App">
            <header className="App-header">
                <h1>最新开奖号码</h1>
            </header>
            <main>
                {loading && <p className="loading">加载中...</p>}
                {error && <p className="error">{error}</p>}
                {numbers.length > 0 && (
                    <div className="numbers-list">
                        <table>
                            <thead>
                                <tr>
                                    <th>期号</th>
                                    <th>开奖号码</th>
                                    <th>开奖时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                {numbers.map((item) => (
                                    <tr key={item.id}>
                                        <td>{item.id}</td>
                                        <td>{item.number}</td>
                                        <td>{item.created_at}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                 {!loading && numbers.length === 0 && !error && (
                    <p>暂无开奖号码</p>
                )}
            </main>
            <footer>
                <p>数据每30秒自动刷新</p>
            </footer>
        </div>
    );
}

export default App;
