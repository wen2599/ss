// frontend/src/api.js

const API_BASE_URL = import.meta.env.DEV ? '/api' : 'https://wenge.cloudns.ch';

/**
 * Fetches the winning numbers from the backend.
 * @returns {Promise<Array<Object>>} A promise that resolves to an array of number objects.
 */
export const getNumbers = async () => {
  try {
    const response = await fetch(`${API_BASE_URL}`);
    
    if (!response.ok) {
      // 如果服务器响应了一个错误状态，就抛出一个错误
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();
    return data;
  } catch (error) {
    console.error("Error fetching numbers:", error);
    // 在发生错误时，可以返回一个空数组或重新抛出错误，取决于您希望如何处理
    return [];
  }
};

// 如果将来有其他 API 调用（例如 POST），您也可以在这里添加它们：
/*
export const postSomeData = async (someData) => {
  try {
    const response = await fetch(`${API_BASE_URL}/some-other-endpoint`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(someData),
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
  } catch (error) {
    console.error("Error posting data:", error);
    throw error;
  }
};
*/
