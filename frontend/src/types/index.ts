export interface Brand {
  id: number;
  name: string;
  slug: string;
  logoUrl?: string;
}

export interface ProductImage {
  id: number;
  url: string;
  is_primary: boolean;
}

export interface ProductSize {
  id: number;
  size: string;
  stock: number;
}

export interface Product {
  id: number;
  name: string;
  slug: string;
  description: string;
  brand_id: number;
  gender: 'MEN' | 'WOMEN' | 'UNISEX';
  price_regular: number;
  price_sale?: number | null;
  is_featured: boolean;
  is_new_drop: boolean;
  brand: Brand;
  images: ProductImage[];
  sizes: ProductSize[];
}

export interface CartItem {
  productId: number;
  name: string;
  slug: string;
  brandName: string;
  size: string;
  price: number;
  image: string;
  quantity: number;
  maxStock: number;
}

export interface OrderRequest {
  customerName: string;
  customerEmail: string;
  customerPhone: string;
  deliveryAddress: string;
  paymentMethod: string;
  items: CartItem[];
}

export interface OrderResponse {
  success: boolean;
  message: string;
  orderNumber: string;
  totalAmount: number;
  paymentMethod: string;
  createdAt: string;
}
