# 十三水 Web 游戏

这是一个基础的十三水（大菠萝/Pusoy Dos变种）Web游戏项目。

## 项目结构

-   `backend/`: Node.js 后端 (Express + Socket.IO)
    -   `server.js`: WebSocket 服务器和主要游戏流程控制。
    -   `game.js`: 核心游戏逻辑（发牌、牌型判断、比较等）。
    -   `package.json`: 后端依赖。
-   `frontend/`: 纯 HTML, CSS, JavaScript 前端
    -   `index.html`: 游戏页面结构。
    -   `style.css`: 页面样式。
    -   `script.js`: 前端交互逻辑和与后端通信。
    -   `images/`: **你需要将52张扑克牌图片和1张牌背面图片 (`back.png`) 放在这里。**
        -   命名规则示例: `ace_of_spades.png`, `10_of_clubs.png`, `king_of_diamonds.png`, `queen_of_hearts.png`, `jack_of_spades.png`。
        -   数字牌用数字 (e.g., `2_of_hearts.png`)。
        -   花牌用全称 (e.g., `jack_of_clubs.png`)。

## 如何运行

### 1. 准备图片

将所有53张扑克牌图片（52张牌面 + 1张 `back.png`）放入 `frontend/images/` 文件夹。确保文件名符合 `game.js` 和 `script.js` 中的预期。

### 2. 运行后端 (本地)

```bash
cd backend
npm install
npm start 
# 或者使用 nodemon (如果已安装): npm run dev
