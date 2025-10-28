import { createApp } from 'vue';
import { createPinia } from 'pinia'; // 导入createPinia
import App from './App.vue';
import router from './router';
import './assets/main.css';

const app = createApp(App);
const pinia = createPinia(); // 创建Pinia实例

app.use(pinia); // 挂载Pinia到Vue应用
app.use(router); // 挂载Vue Router

app.mount('#app');
