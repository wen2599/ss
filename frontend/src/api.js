const API_BASE_URL = ''; // The backend directory is served as the root

const handleResponse = async (response) => {
  // First, check for a 204 No Content response, which doesn't have a body.
  if (response.status === 204) {
    return { success: true, message: 'Operation successful.' };
  }
  
  // For other responses, try to parse the JSON body.
  const text = await response.text();
  try {
    const data = JSON.parse(text);
    if (!response.ok) {
      throw new Error(data.error || 'Something went wrong.');
    }
    return data;
  } catch (err) {
    // If JSON parsing fails, the response was not valid JSON.
    console.error('Failed to parse JSON:', text);
    // Provide a more structured error.
    throw new Error('The server returned an unexpected response. Please check the console for more details.');
  }
};

export const registerUser = async (username, email, password) => {
  const response = await fetch(`${API_BASE_URL}/register`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ username, email, password }),
  });
  return handleResponse(response);
};

export const loginUser = async (username, password) => {
  const response = await fetch(`${API_BASE_URL}/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ username, password }),
  });
  return handleResponse(response);
};

export const logoutUser = async () => {
  const response = await fetch(`${API_BASE_URL}/logout`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
  });
  return handleResponse(response);
};

export const checkSession = async () => {
  const response = await fetch(`${API_BASE_URL}/check_session`, {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
    },
  });
  return handleResponse(response);
};

export const getBills = async () => {
  const response = await fetch(`${API_BASE_URL}/get_bills`, {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
    },
  });
  return handleResponse(response);
};

export const deleteBill = async (billId) => {
  const response = await fetch(`${API_BASE_URL}/delete_bill`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ billId }),
  });
  return handleResponse(response);
};

export const getLotteryResults = async () => {
  const response = await fetch(`${API_BASE_URL}/get_lottery_results`, {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
    },
  });
  return handleResponse(response);
};
