import { createContext, useContext } from 'react';

/** Forme renvoyée par l'API — jamais le hachage du mot de passe. */
export type Customer = {
  id: number;
  name: string;
  email: string;
  phone: string;
  email_verified: boolean;
  /** Affiche l'accès à l'administration. Ne protège rien : le serveur revérifie. */
  is_admin: boolean;
  /** Décide ce que la barre latérale montre. Les routes correspondantes revérifient le rang. */
  is_super_admin: boolean;
  created_at: string;
};

export type RegisterInput = {
  name: string;
  email: string;
  phone: string;
  password: string;
  terms: boolean;
};

export type AuthValue = {
  customer: Customer | null;
  /** Vrai tant que la session n'a pas été vérifiée au chargement. Sans cet
   *  état, une page protégée renverrait le visiteur vers la connexion pendant
   *  la fraction de seconde qui précède la réponse de /auth/me. */
  loading: boolean;
  register: (input: RegisterInput) => Promise<Customer>;
  login: (identifier: string, password: string, remember: boolean) => Promise<Customer>;
  logout: () => Promise<void>;
  updateProfile: (input: { name: string; email: string; phone: string }) => Promise<Customer>;
  updatePassword: (current: string, next: string) => Promise<void>;
  resendVerification: () => Promise<string>;
  refresh: () => Promise<void>;
};

export const AuthContext = createContext<AuthValue | null>(null);

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth doit être utilisé dans un AuthProvider');
  return context;
}
