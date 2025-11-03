export const getLotteryNumbers = async (token) => {
  try {
    const response = await fetch('/api/index.php?action=get_numbers', {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    return await response.json();
  } catch (error) {
    console.error("Failed to fetch lottery numbers:", error);
    throw error;
  }
};