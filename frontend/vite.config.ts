import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
// Le même renvoi sert au serveur de développement et à `vite preview`.
// `preview` sert le contenu de `dist/`, c'est-à-dire exactement ce qui part chez
// l'hébergeur : c'est la seule façon de vérifier le site compilé sans le mettre
// en ligne. Sans ce renvoi, `preview` cherche l'API chez lui et le catalogue
// arrive vide — on croirait le site cassé alors que seul le bac d'essai l'est.
const renvoiApi = {
  // L'API PHP tourne à côté (php -S localhost:8000 -t backend/public), mais
  // le navigateur ne voit qu'une seule origine. C'est ce qui permet au cookie
  // de session de se comporter en développement exactement comme en
  // production, où le front et l'API partagent le domaine — sans CORS ni
  // SameSite=None.
  '/api': {
    target: 'http://127.0.0.1:8000',
    changeOrigin: false,
    rewrite: (path: string) => path.replace(/^\/api/, ''),
  },
}

export default defineConfig({
  plugins: [react()],
  preview: { proxy: renvoiApi },
  server: { proxy: renvoiApi },
})
