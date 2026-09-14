import { BrowserRouter, Route, Routes } from 'react-router-dom';
import Hero from './components/Hero';
import Boutique from './components/Boutique';
import ProductDetail from './components/ProductDetail';
import Contact from './components/Contact';
import Soldes from './components/Soldes';
import Maillots from './components/Maillots';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<Hero />} />
        <Route path="/boutique" element={<Boutique />} />
        <Route path="/boutique/:slug" element={<ProductDetail />} />
        <Route path="/maillots" element={<Maillots />} />
        <Route path="/soldes" element={<Soldes />} />
        <Route path="/contact" element={<Contact />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;
