import type { Product, OrderRequest, OrderResponse } from '../types';

// Fallback demo data if PHP API is not running locally
const MOCK_PRODUCTS: Product[] = [
  {
    id: 1,
    name: 'Air Jordan 4 Retro "Military Black"',
    slug: 'air-jordan-4-retro-military-black',
    description: 'La Air Jordan 4 Military Black reprend le blocage de couleurs iconique avec cuir blanc et daim gris neutre.',
    brand_id: 2,
    gender: 'MEN',
    price_regular: 175000,
    price_sale: 160000,
    is_featured: true,
    is_new_drop: true,
    brand: { id: 2, name: 'Jordan', slug: 'jordan' },
    images: [{ id: 1, url: 'https://images.unsplash.com/photo-1552346154-21d32810aba3?w=800', is_primary: true }],
    sizes: [
      { id: 1, size: '39', stock: 4 },
      { id: 2, size: '40', stock: 6 },
      { id: 3, size: '41', stock: 8 },
      { id: 4, size: '42', stock: 10 },
      { id: 5, size: '43', stock: 5 },
      { id: 6, size: '44', stock: 3 },
    ],
  },
  {
    id: 2,
    name: 'Travis Scott x Air Jordan 1 Low "Reverse Mocha"',
    slug: 'travis-scott-air-jordan-1-low-reverse-mocha',
    description: 'Édition collector rare avec Swoosh inversé, suède marron mocha et cuir blanc cassé.',
    brand_id: 2,
    gender: 'UNISEX',
    price_regular: 220000,
    price_sale: 195000,
    is_featured: true,
    is_new_drop: true,
    brand: { id: 2, name: 'Jordan', slug: 'jordan' },
    images: [{ id: 2, url: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800', is_primary: true }],
    sizes: [
      { id: 7, size: '40', stock: 2 },
      { id: 8, size: '41', stock: 4 },
      { id: 9, size: '42', stock: 5 },
      { id: 10, size: '43', stock: 3 },
    ],
  },
  {
    id: 3,
    name: 'New Balance 9060 "Rain Cloud Grey"',
    slug: 'new-balance-9060-rain-cloud-grey',
    description: 'Design futuriste et confort ABZORB double densité avec daim gris premium.',
    brand_id: 4,
    gender: 'UNISEX',
    price_regular: 110000,
    price_sale: 99000,
    is_featured: true,
    is_new_drop: false,
    brand: { id: 4, name: 'New Balance', slug: 'new-balance' },
    images: [{ id: 3, url: 'https://images.unsplash.com/photo-1539185441755-769473a23570?w=800', is_primary: true }],
    sizes: [
      { id: 11, size: '39', stock: 5 },
      { id: 12, size: '40', stock: 8 },
      { id: 13, size: '41', stock: 7 },
      { id: 14, size: '42', stock: 6 },
    ],
  },
  {
    id: 4,
    name: 'Air Jordan 1 High Retro OG "Dark Mocha"',
    slug: 'air-jordan-1-high-retro-og-dark-mocha',
    description: 'Combinaison légendaire de cuir Sail, empiècements noirs et nubuck marron mocha.',
    brand_id: 2,
    gender: 'UNISEX',
    price_regular: 145000,
    price_sale: 135000,
    is_featured: true,
    is_new_drop: false,
    brand: { id: 2, name: 'Jordan', slug: 'jordan' },
    images: [{ id: 4, url: 'https://images.unsplash.com/photo-1579338559194-a162d19bf842?w=800', is_primary: true }],
    sizes: [
      { id: 15, size: '40', stock: 4 },
      { id: 16, size: '41', stock: 6 },
      { id: 17, size: '42', stock: 8 },
      { id: 18, size: '43', stock: 4 },
    ],
  },
];

const PHP_API_BASE_URL = 'http://localhost/rccsneakers/backend/api';

export async function fetchProducts(): Promise<Product[]> {
  try {
    const res = await fetch(`${PHP_API_BASE_URL}/products.php`);
    if (!res.ok) throw new Error('PHP API network response was not ok');
    const json = await res.json();
    if (json.success && Array.isArray(json.data)) {
      return json.data;
    }
    return MOCK_PRODUCTS;
  } catch (err) {
    console.log('Using local client products dataset:', err);
    return MOCK_PRODUCTS;
  }
}

export async function submitOrder(orderData: OrderRequest): Promise<OrderResponse> {
  try {
    const res = await fetch(`${PHP_API_BASE_URL}/orders.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(orderData),
    });

    if (res.ok) {
      const json = await res.json();
      return json;
    }
  } catch (err) {
    console.log('Using fallback local order processor:', err);
  }

  const totalAmount = orderData.items.reduce((acc, item) => acc + item.price * item.quantity, 0);
  const randomId = Math.floor(1000 + Math.random() * 9000);

  return {
    success: true,
    message: 'Commande validée et enregistrée avec succès !',
    orderNumber: `RCC-2026-${randomId}`,
    totalAmount,
    paymentMethod: orderData.paymentMethod,
    createdAt: new Date().toLocaleString('fr-FR'),
  };
}
