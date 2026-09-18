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
import { CatalogueProvider } from './context/CatalogueContext';
import { CartProvider } from './context/CartContext';
import AdminLayout from './components/admin/AdminLayout';
import AdminOverview from './components/admin/AdminOverview';
import AdminOrders from './components/admin/AdminOrders';
import AdminCatalogue from './components/admin/AdminCatalogue';
import AdminCustomers from './components/admin/AdminCustomers';
import AdminInbox from './components/admin/AdminInbox';
import AdminSettings from './components/admin/AdminSettings';
import AdminAdmins from './components/admin/AdminAdmins';
import AdminAccount from './components/admin/AdminAccount';
import SuperAdminRoute from './components/admin/SuperAdminRoute';
import { LEGAL_SLUGS } from './data/legal';

function App() {
  return (
    <BrowserRouter>
      {/* FavoritesProvider lit la session, il doit donc vivre sous AuthProvider.
          Le panier retrouve ses articles dans le catalogue : il vit sous lui. */}
      <AuthProvider>
        <FavoritesProvider>
          <CatalogueProvider>
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

                {/* Administration. La garde vit dans AdminLayout, et surtout
                    côté serveur : chaque route d'API revérifie le drapeau. */}
                <Route path="/admin" element={<AdminLayout />}>
                  <Route index element={<AdminOverview />} />
                  <Route path="commandes" element={<AdminOrders />} />
                  <Route path="produits" element={<AdminCatalogue kind="product" />} />
                  <Route path="maillots" element={<AdminCatalogue kind="jersey" />} />
                  <Route path="clients" element={<AdminCustomers />} />
                  <Route path="messages" element={<AdminInbox />} />
                  <Route path="vitrine" element={<AdminSettings view="vitrine" />} />
                  {/* Sans entrée de menu : on y arrive par son nom, en bas de
                      la barre latérale. Ouvert à tout administrateur. */}
                  <Route path="compte" element={<AdminAccount />} />
                  {/* Réservés au super administrateur. La garde est ici pour
                      l'écran ; les routes d'API la refont pour de bon. */}
                  <Route
                    path="administrateurs"
                    element={
                      <SuperAdminRoute>
                        <AdminAdmins />
                      </SuperAdminRoute>
                    }
                  />
                  <Route
                    path="reglages"
                    element={
                      <SuperAdminRoute>
                        <AdminSettings view="reglages" />
                      </SuperAdminRoute>
                    }
                  />
                </Route>
                {LEGAL_SLUGS.map((slug) => (
                  <Route key={slug} path={`/${slug}`} element={<PageLegale slug={slug} />} />
                ))}
              </Routes>
              <CartDrawer />
              <CartToast />
            </CartProvider>
          </CatalogueProvider>
        </FavoritesProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
