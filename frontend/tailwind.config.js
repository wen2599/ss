
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  darkMode: 'class', // Enable dark mode
  theme: {
    extend: {
      colors: {
        'primary': {
          DEFAULT: '#3B82F6', // A modern blue
          'hover': '#2563EB',
          'dark': '#60A5FA',
        },
        'gray': {
          '50': '#F9FAFB',
          '100': '#F3F4F6', // Light background
          '200': '#E5E7EB',
          '300': '#D1D5DB',
          '400': '#9CA3AF',
          '500': '#6B7280', // Main text color
          '600': '#4B5563',
          '700': '#374151',
          '800': '#1F2937', // Dark background
          '900': '#111827', // Darker background
        },
      }
    },
  },
  plugins: [],
}
