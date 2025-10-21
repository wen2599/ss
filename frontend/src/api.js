import axios from 'axios';

const API_BASE_URL = ''; // The backend directory is served as the root

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true, // Important for session management
});

api.interceptors.response.use(
  (response) => {
    // Any status code that lie within the range of 2xx cause this function to trigger
    return response.data; // Return the actual data from the response
  },
  (error) => {
    // Any status codes that falls outside the range of 2xx cause this function to trigger
    if (error.response) {
      // The request was made and the server responded with a status code
      // that falls out of the range of 2xx
      console.error('API Error:', error.response.data);
      throw new Error(error.response.data.error || 'Something went wrong.');
    } else if (error.request) {
      // The request was made but no response was received
      console.error('API Error: No response received', error.request);
      throw new Error('No response from server. Please check your network connection.');
    } else {
      // Something happened in setting up the request that triggered an Error
      console.error('API Error:', error.message);
      throw new Error('An unexpected error occurred.');
    }
  }
);

export const registerUser = async (username, email, password) => {
  return api.post('/register', { username, email, password });
};

export const loginUser = async (username, password) => {
  return api.post('/login', { username, password });
};

export const logoutUser = async () => {
  return api.post('/logout');
};

export const checkSession = async () => {
  return api.get('/check_session');
};

export const getBills = async () => {
  return api.get('/get_bills');
};

export const deleteBill = async (billId) => {
  return api.post('/delete_bill', { billId });
};

export const getLotteryResults = async () => {
  return api.get('/get_lottery_results');
};
