import axios from 'axios';

const LOTTERY_API_BASE_URL = '/api'; // 或者你的API实际路径

const lotteryService = {
  async getLatestDraw() {
    try {
      const response = await axios.get(`${LOTTERY_API_BASE_URL}/get-latest-draw.php`);
      return response.data;
    } catch (error) {
      console.error("Error fetching latest lottery draw:", error);
      throw error;
    }
  }
};

export default lotteryService;
