import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { api, setSessionLostHandler } from '../api/client';
import { AuthContext, type AuthValue, type Customer, type RegisterInput } from './auth-context';

type Envelope = { customer: Customer };

export function AuthProvider({ children }: { children: ReactNode }) {
  const [customer, setCustomer] = useState<Customer | null>(null);
  const [loading, setLoading] = useState(true);

  /**
   * La session vit dans un cookie HttpOnly : le JavaScript ne peut pas la lire.
   * Le seul moyen de savoir qui est connecté est de le demander au serveur, une
   * fois, au chargement.
   */
  const refresh = useCallback(async () => {
    try {
      const data = await api<Envelope>('/auth/me');
      setCustomer(data.customer);
    } catch {
      // Un 401 est la réponse normale d'un visiteur, pas une anomalie.
      setCustomer(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  /**
   * Le serveur fait autorité sur la session. S'il refuse un appel que seule une
   * session ouverte autorise, l'interface se range à son avis immédiatement :
   * les pages protégées renvoient alors vers la connexion d'elles-mêmes, au
   * lieu d'afficher « vous devez être connecté » à quelqu'un qui croyait l'être.
   */
  useEffect(() => {
    setSessionLostHandler(() => setCustomer(null));

    return () => setSessionLostHandler(null);
  }, []);

  const register = useCallback(async (input: RegisterInput) => {
    const data = await api<Envelope>('/auth/register', { method: 'POST', body: input });
    setCustomer(data.customer);
    return data.customer;
  }, []);

  const login = useCallback(async (identifier: string, password: string, remember: boolean) => {
    const data = await api<Envelope>('/auth/login', {
      method: 'POST',
      body: { identifier, password, remember },
    });
    setCustomer(data.customer);
    return data.customer;
  }, []);

  const logout = useCallback(async () => {
    try {
      await api<void>('/auth/logout', { method: 'POST' });
    } finally {
      // Même si l'appel échoue, l'intention du client est de partir : on ne le
      // laisse pas sur une interface qui prétend qu'il est encore connecté.
      setCustomer(null);
    }
  }, []);

  const updateProfile = useCallback(async (input: { name: string; email: string; phone: string }) => {
    const data = await api<Envelope>('/auth/profile', { method: 'POST', body: input });
    setCustomer(data.customer);
    return data.customer;
  }, []);

  const updatePassword = useCallback(async (current: string, next: string) => {
    await api<{ message: string }>('/auth/password', {
      method: 'POST',
      body: { current_password: current, password: next },
    });
  }, []);

  const resendVerification = useCallback(async () => {
    const data = await api<{ sent: boolean; message: string }>('/auth/resend-verification', {
      method: 'POST',
    });
    return data.message;
  }, []);

  const value = useMemo<AuthValue>(
    () => ({
      customer,
      loading,
      register,
      login,
      logout,
      updateProfile,
      updatePassword,
      resendVerification,
      refresh,
    }),
    [customer, loading, register, login, logout, updateProfile, updatePassword, resendVerification, refresh],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
