export interface DrawResult {
  period: number;
  date: string;
  numbers: number[];
  specialNumber: number;
}

export const mockDraws: DrawResult[] = [
  {
    period: 2024088,
    date: '2024-08-03',
    numbers: [3, 12, 18, 25, 33, 41],
    specialNumber: 49,
  },
  {
    period: 2024087,
    date: '2024-08-01',
    numbers: [7, 11, 20, 34, 38, 45],
    specialNumber: 22,
  },
  {
    period: 2024086,
    date: '2024-07-30',
    numbers: [5, 16, 21, 29, 40, 47],
    specialNumber: 10,
  },
  {
    period: 2024085,
    date: '2024-07-27',
    numbers: [1, 9, 15, 23, 30, 44],
    specialNumber: 37,
  },
];
