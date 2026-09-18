import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../../context/auth-context';

/**
 * Garde des écrans réservés au super administrateur.
 *
 * Elle ne protège rien par elle-même — un administrateur qui taperait l'adresse
 * atteindrait des routes d'API qui lui répondent 403 de toute façon. Elle évite
 * seulement d'afficher un écran vide et incompréhensible à quelqu'un qui n'a
 * rien à y faire, et le ramène à la vue d'ensemble.
 */
export default function SuperAdminRoute({ children }: { children: ReactNode }) {
  const { customer, loading } = useAuth();

  // Pendant la vérification de session, `customer` est null sans que cela
  // signifie « pas administrateur » : rediriger ici renverrait le super
  // administrateur lui-même à la vue d'ensemble, à chaque rechargement.
  if (loading) return null;

  if (customer?.is_super_admin !== true) return <Navigate to="/admin" replace />;

  return <>{children}</>;
}
