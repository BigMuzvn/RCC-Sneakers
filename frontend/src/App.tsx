import { BrowserRouter, Route, Routes } from 'react-router-dom';
import Hero from './components/Hero';
import Boutique from './components/Boutique';
import ProductDetail from './components/ProductDetail';
import Contact from './components/Contact';
import Soldes from './components/Soldes';
import Maillots from './components/Maillots';
import JerseyDetail from './components/JerseyDetail';
import Compte from './components/Compte';
import EspaceClient from './components/EspaceClient';
import VerifierEmail from './components/VerifierEmail';
import MotDePasseOublie from './components/MotDePasseOublie';
import ReinitialiserMotDePasse from './components/ReinitialiserMotDePasse';
import Checkout from './components/Checkout';
import PageLegale from './components/PageLegale';
import CartDrawer from './components/CartDrawer';
import CartToast from './components/CartToast';
import { AuthProvider } from './context/AuthContext';
import { FavoritesProvider } from './context/FavoritesContext';
import { CartProvider } from './context/CartContext';
import { LEGAL_SLUGS } from './data/legal';

function App() {
  return (
    <BrowserRouter>
      {/* FavoritesProvider lit la session, il doit donc vivre sous AuthProvider. */}
      <AuthProvider>
        <FavoritesProvider>
          <CartProvider>
            <Routes>
              <Route path="/" element={<Hero />} />
              <Route path="/boutique" element={<Boutique />} />
              <Route path="/boutique/:slug" element={<ProductDetail />} />
              <Route path="/maillots" element={<Maillots />} />
              <Route path="/maillots/:slug" element={<JerseyDetail />} />
              <Route path="/soldes" element={<Soldes />} />
              <Route path="/contact" element={<Contact />} />

              <Route path="/compte" element={<Compte />} />
              <Route path="/compte/verifier" element={<VerifierEmail />} />
              <Route path="/compte/mot-de-passe-oublie" element={<MotDePasseOublie />} />
              <Route path="/compte/reinitialiser" element={<ReinitialiserMotDePasse />} />
              <Route path="/espace-client" element={<EspaceClient />} />

              <Route path="/checkout" element={<Checkout />} />
              {LEGAL_SLUGS.map((slug) => (
                <Route key={slug} path={`/${slug}`} element={<PageLegale slug={slug} />} />
              ))}
            </Routes>
            <CartDrawer />
            <CartToast />
          </CartProvider>
        </FavoritesProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
