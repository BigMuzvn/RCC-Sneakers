import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // L'API PHP tourne à côté (php -S localhost:8000 -t backend/public), mais
      // le navigateur ne voit qu'une seule origine : http://localhost:5173.
      // C'est ce qui permet au cookie de session de se comporter en
      // développement exactement comme en production, où le front et l'API
      // partagent le domaine — sans CORS ni SameSite=None.
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: false,
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
    },
  },
})
