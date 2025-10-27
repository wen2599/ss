import api from './api';

const lotteryService = {
  async getLatestDraw() {
    try {
      const response = await api.get('/get-latest-draw.php');
      return response.data;
    } catch (error) {
      console.error("Error fetching latest lottery draw:", error);
      throw error;
    }
  }
};

export default lotteryService;
