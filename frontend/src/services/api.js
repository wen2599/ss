// The proxy path configured in public/_worker.js
const API_BASE_URL = '/api'; 

const request = async (endpoint, options = {}) => {
  const url = `${API_BASE_URL}/${endpoint}`;
  const token = localStorage.getItem('authToken');

  const headers = {
    'Content-Type': 'application/json',
    ...options.headers,
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const config = {
    ...options,
    headers,
  };

  try {
    const response = await fetch(url, config);
    // Check if the response is JSON, otherwise return text
    const contentType = response.headers.get("content-type");
    if (contentType && contentType.indexOf("application/json") !== -1) {
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || '发生了一个错误');
        }
        return data;
    } else {
        if (!response.ok) {
            throw new Error(await response.text());
        }
        return await response.text();
    }
  } catch (error) {
    console.error(`API Error on ${endpoint}:`, error);
    throw error;
  }
};

// --- User API ---
export const registerUser = (email, password) => {
  return request('users/register', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });
};

export const loginUser = (email, password) => {
  return request('users/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });
};


// --- Data API ---
export const getEmails = () => {
  return request('emails/list'); // Assumes backend has this endpoint
};

export const getSettlements = () => {
  // Placeholder: you need to create this endpoint in your backend
  console.warn("getSettlements is a placeholder. Implement the backend API.");
  return Promise.resolve([]); // Returns an empty array for now
  // return request('settlements/list');
};
