const suitMap = { S: 'spades', H: 'hearts', D: 'diamonds', C: 'clubs' };
const rankMap = {
  'A': 'ace', '2': '2', '3': '3', '4': '4', '5': '5', '6': '6', '7': '7',
  '8': '8', '9': '9', '10': '10', 'J': 'jack', 'Q': 'queen', 'K': 'king'
};

export const mapCardNameToFilename = (cardName) => {
  if (!cardName) return 'red_joker.svg'; // Default or error card

  if (cardName === 'BJ') return 'black_joker.svg';
  if (cardName === 'RJ') return 'red_joker.svg';

  const suit = cardName.substring(0, 1);
  const rank = cardName.substring(1);

  if (rankMap[rank] && suitMap[suit]) {
    return `${rankMap[rank]}_of_${suitMap[suit]}.svg`;
  }

  return 'red_joker.svg'; // Fallback for any unexpected card name
};
