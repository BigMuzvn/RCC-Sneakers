/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        rcc: {
          bg: "#0A0A0C",
          surface: "#121216",
          card: "#181820",
          cream: "#EDE8DB",
          gold: "#D4AF37",
          muted: "#8E8E9F",
        },
      },
    },
  },
  plugins: [],
}
