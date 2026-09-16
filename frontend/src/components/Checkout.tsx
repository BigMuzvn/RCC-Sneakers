import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Check, ShoppingBag, Trash2 } from 'lucide-react';
import Navbar from './Navbar';
import Footer from './Footer';
import HangingShoe from './HangingShoe';
import hangingDunk from '../assets/hanging-dunk-russet.png';
import { useCart } from '../context/cart-context';
import { useAuth } from '../context/auth-context';
import { ApiFailure, api } from '../api/client';
import { formatXof } from '../utils/format';

const PAGE_GRADIENT =
  'radial-gradient(ellipse 95% 58% at 58% 0%, #6B3520 0%, #40241C 28%, #1F1614 58%, #0B0807 100%)';

type Zone = { id: string; label: string; delay_label: string; fee_xof: number };

/**
 * Les modes de paiement restent en dur — ils décrivent ce que la boutique sait
 * faire, pas des données. Le paiement en ligne est montré mais désactivé :
 * aucun agrégateur n'est encore branché, et le serveur refuse cette option.
 * L'afficher comme disponible promettrait un règlement impossible.
 */
const PAYMENTS = [
  { id: 'cash', label: 'Espèces à la livraison', hint: 'Vous réglez au livreur à la réception', available: true },
  {
    id: 'online',
    label: 'Paiement mobile / carte bancaire',
    hint: 'Tous réseaux mobile money et cartes Visa ou Mastercard — bientôt disponible',
    available: false,
  },
] as const;

const inputClass =
  'w-full border border-white/15 bg-white/[0.03] px-3.5 py-3 text-[12px] text-white placeholder:text-white/35 transition-colors focus:border-white/50 focus:outline-none';
const labelClass = 'text-[10px] font-bold uppercase tracking-[0.16em] text-white/60';

export default function Checkout() {
  const { lines, subtotal, clear, remove } = useCart();
  const { customer, loading } = useAuth();
  const navigate = useNavigate();
  const [zones, setZones] = useState<Zone[]>([]);
  const [zone, setZone] = useState('');
  const [payment, setPayment] = useState<(typeof PAYMENTS)[number]['id']>('cash');
  const [reference, setReference] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState('');

  useEffect(() => {
    window.scrollTo(0, 0);
  }, []);

  // Le tiroir du panier pose la question avant d'arriver ici, mais cette adresse
  // reste atteignable directement — par un signet, ou après expiration de la
  // session. La garde doit donc exister des deux côtés.
  useEffect(() => {
    if (!loading && !customer) {
      navigate('/compte?retour=commande&suite=/checkout', { replace: true });
    }
  }, [loading, customer, navigate]);

  // Les tarifs viennent du serveur : ils changeront quand les vrais prix des
  // coursiers seront connus, et c'est de toute façon le montant en base qui
  // sera appliqué à la commande.
  useEffect(() => {
    api<{ zones: Zone[] }>('/delivery-zones')
      .then((data) => {
        setZones(data.zones);
        setZone((current) => current || (data.zones[0]?.id ?? ''));
      })
      .catch(() => setNotice("Impossible de charger les zones de livraison. Rechargez la page."));
  }, []);

  const fee = zones.find((item) => item.id === zone)?.fee_xof ?? 0;
  const total = subtotal + fee;
  const summary = useMemo(() => lines, [lines]);

  const submitOrder = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setErrors({});
    setNotice('');
    setSubmitting(true);

    const data = new FormData(event.currentTarget);

    try {
      // Le panier envoie ce qui a été choisi, jamais les prix : c'est le
      // serveur qui les relit en base et calcule le total.
      const order = await api<{ order: { reference: string } }>('/orders', {
        method: 'POST',
        body: {
          name: String(data.get('name') ?? ''),
          email: String(data.get('email') ?? ''),
          phone: String(data.get('phone') ?? ''),
          zone,
          address: String(data.get('address') ?? ''),
          payment_method: payment,
          items: lines.map((line) => ({
            item_type: line.type,
            item_id: line.id,
            size: line.size,
            qty: line.qty,
          })),
        },
      });

      setReference(order.order.reference);
      clear();
    } catch (error) {
      setSubmitting(false);

      if (error instanceof ApiFailure) {
        setErrors(error.fields);
        const sansChamp = Object.keys(error.fields).filter((k) => !k.startsWith('items.'));
        if (sansChamp.length === 0) setNotice(error.message);
      } else {
        setNotice('Une erreur est survenue. Réessayez dans un instant.');
      }
    }
  };

  return (
    <div className="relative flex min-h-[100svh] w-full flex-col overflow-x-clip">
      <div aria-hidden="true" className="fixed inset-0 z-0" style={{ background: PAGE_GRADIENT }} />
      {/* Dunk Dark Russet — rust and deep brown sampled from the colorway */}
      <HangingShoe image={hangingDunk} primary="#D8551E" secondary="#8A3A18" />

      <div className="relative z-10 flex-1 px-4 py-4 sm:px-8 sm:py-6 lg:px-14 lg:py-8">
        <Navbar />

        <header className="relative mt-14 select-none sm:mt-20">
          <div className="relative w-fit">
            <span
              aria-hidden="true"
              className="absolute bottom-full left-0 mb-[-0.45em] font-display uppercase leading-none tracking-[0.3em] text-white/25 text-[clamp(11px,2.6vw,34px)]"
            >
              RCC
            </span>
            <h1 className="whitespace-nowrap font-display uppercase leading-none tracking-[-0.02em] text-white/[0.16] text-[clamp(38px,11vw,142px)]">
              Paiement
            </h1>
          </div>
        </header>

        {reference ? (
          <div className="mt-10 max-w-lg border border-white/15 bg-white/[0.03] px-6 py-10 sm:mt-14 sm:px-10">
            <span className="flex h-12 w-12 items-center justify-center rounded-full bg-[#EDEFF2] text-[#17191C]">
              <Check className="h-6 w-6" strokeWidth={2.5} />
            </span>
            <h2 className="mt-5 text-base font-bold uppercase tracking-[0.12em] text-[#EDEFF2]">Commande enregistrée</h2>
            <p className="mt-2 text-[11px] leading-[1.75] text-white/55 sm:text-xs">
              Votre référence est <span className="font-bold text-[#EDEFF2]">{reference}</span>. Nous vous appelons
              pour confirmer la livraison et la disponibilité des tailles.
            </p>
            <div className="mt-7 flex flex-wrap gap-3">
              <Link
                to="/boutique"
                className="bg-white px-6 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-[#141516] transition-opacity hover:opacity-90"
              >
                Continuer mes achats
              </Link>
              <Link
                to="/contact"
                className="border border-white px-6 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-white transition-colors hover:bg-white hover:text-[#141516]"
              >
                Nous contacter
              </Link>
            </div>
          </div>
        ) : summary.length === 0 ? (
          <div className="mt-10 flex max-w-lg flex-col items-start gap-4 border border-white/15 bg-white/[0.03] px-6 py-12 sm:mt-14 sm:px-10">
            <span className="flex h-12 w-12 items-center justify-center rounded-full border border-white/15 text-white/45">
              <ShoppingBag className="h-5 w-5" strokeWidth={1.8} />
            </span>
            <h2 className="text-sm font-bold uppercase tracking-[0.12em] text-[#EDEFF2]">Votre panier est vide</h2>
            <p className="text-[11px] leading-[1.7] text-white/55">
              Ajoutez au moins un article avant de passer au paiement.
            </p>
            <Link
              to="/boutique"
              className="mt-2 bg-white px-6 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-[#141516] transition-opacity hover:opacity-90"
            >
              Voir la boutique
            </Link>
          </div>
        ) : (
          <form
            className="mt-10 grid grid-cols-1 items-start gap-8 pb-6 sm:mt-14 lg:grid-cols-[minmax(0,1fr)_380px] lg:gap-14"
            onSubmit={submitOrder}
          >
            {/* ---------- FORM ---------- */}
            <div className="flex flex-col gap-9">
              <section>
                <h2 className="text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2]">1. Vos coordonnées</h2>
                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                  {/* Préremplis depuis le compte — on ne redemande pas ce qu'on
                      sait déjà. Les champs restent modifiables : la commande
                      peut être livrée à quelqu'un d'autre. */}
                  <label className="flex flex-col gap-2">
                    <span className={labelClass}>Nom complet</span>
                    <input required name="name" defaultValue={customer?.name ?? ''} autoComplete="name" placeholder="Votre nom" className={inputClass} />
                  </label>
                  <label className="flex flex-col gap-2">
                    <span className={labelClass}>Téléphone</span>
                    <input required name="phone" type="tel" defaultValue={customer?.phone ?? ''} autoComplete="tel" placeholder="+229 ..." className={inputClass} />
                  </label>
                  <label className="flex flex-col gap-2 sm:col-span-2">
                    <span className={labelClass}>E-mail</span>
                    <input required name="email" type="email" defaultValue={customer?.email ?? ''} autoComplete="email" placeholder="vous@exemple.com" className={inputClass} />
                  </label>
                </div>
              </section>

              <section>
                <h2 className="text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2]">2. Livraison</h2>
                <div className="mt-4 flex flex-col gap-2.5">
                  {zones.map((item) => (
                    <label
                      key={item.id}
                      className={`flex cursor-pointer items-center justify-between gap-3 border px-4 py-3.5 transition-colors ${
                        zone === item.id ? 'border-[#EDEFF2] bg-white/[0.06]' : 'border-white/15 hover:border-white/35'
                      }`}
                    >
                      <span className="flex items-center gap-3">
                        <input
                          type="radio"
                          name="zone"
                          value={item.id}
                          checked={zone === item.id}
                          onChange={() => setZone(item.id)}
                          className="h-3.5 w-3.5 accent-[#EDEFF2]"
                        />
                        <span>
                          <span className="block text-[12px] font-bold uppercase tracking-[0.08em] text-[#EDEFF2]">
                            {item.label}
                          </span>
                          <span className="block text-[10px] text-white/45">Livraison {item.delay_label}</span>
                        </span>
                      </span>
                      <span className="font-display text-[13px] text-[#EDEFF2]">{formatXof(item.fee_xof)}</span>
                    </label>
                  ))}
                </div>

                <label className="mt-4 flex flex-col gap-2">
                  <span className={labelClass}>Adresse de livraison</span>
                  <textarea
                    required
                    name="address"
                    rows={3}
                    placeholder="Quartier, rue, repère connu…"
                    className={`${inputClass} resize-none`}
                  />
                </label>
              </section>

              <section>
                <h2 className="text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2]">3. Paiement</h2>
                <div className="mt-4 flex flex-col gap-2.5">
                  {PAYMENTS.map((item) => (
                    <label
                      key={item.id}
                      aria-disabled={!item.available}
                      className={`flex flex-col gap-1 border px-4 py-3.5 transition-colors ${
                        !item.available
                          ? 'cursor-not-allowed border-white/10 opacity-45'
                          : payment === item.id
                            ? 'cursor-pointer border-[#EDEFF2] bg-white/[0.06]'
                            : 'cursor-pointer border-white/15 hover:border-white/35'
                      }`}
                    >
                      <span className="flex items-center gap-2.5">
                        <input
                          type="radio"
                          name="payment"
                          value={item.id}
                          disabled={!item.available}
                          checked={payment === item.id}
                          onChange={() => setPayment(item.id)}
                          className="h-3.5 w-3.5 shrink-0 accent-[#EDEFF2]"
                        />
                        <span className="text-[12px] font-bold uppercase tracking-[0.08em] text-[#EDEFF2]">
                          {item.label}
                        </span>
                        {!item.available && (
                          <span className="ml-auto shrink-0 border border-white/20 px-2 py-[2px] text-[9px] font-bold uppercase tracking-[0.12em] text-white/50">
                            Bientôt
                          </span>
                        )}
                      </span>
                      <span className="pl-6 text-[10px] leading-[1.6] text-white/45">{item.hint}</span>
                    </label>
                  ))}
                </div>
              </section>
            </div>

            {/* ---------- SUMMARY ---------- */}
            <aside className="w-full border border-white/10 bg-white/[0.03] p-5 lg:sticky lg:top-8">
              <h2 className="text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2]">Votre commande</h2>

              <ul className="mt-4 flex flex-col">
                {summary.map((line) => (
                  <li key={line.key} className="flex gap-3 border-b border-white/10 py-3 first:pt-0 last:border-0">
                    <div className="relative h-14 w-14 shrink-0 overflow-hidden border border-white/10">
                      <span
                        aria-hidden="true"
                        className="absolute inset-0"
                        style={{
                          background: `radial-gradient(ellipse 75% 70% at 50% 55%, ${line.accent}70 0%, ${line.accent}25 45%, transparent 75%)`,
                        }}
                      />
                      {line.image && (
                        <img src={line.image} alt="" className="absolute inset-0 h-full w-full scale-105 object-contain p-1" />
                      )}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-[11px] font-bold uppercase tracking-[0.06em] text-[#EDEFF2]">
                        {line.title}
                      </p>
                      <p className="truncate text-[10px] text-white/45">{line.subtitle}</p>
                      <p className="text-[10px] uppercase tracking-[0.1em] text-white/55">
                        Taille {line.size} · ×{line.qty}
                      </p>
                    </div>
                    <div className="flex shrink-0 flex-col items-end gap-1.5">
                      <span className="font-display text-[12px] text-[#EDEFF2]">
                        {formatXof(line.unit_price_xof * line.qty)}
                      </span>
                      <button
                        type="button"
                        onClick={() => remove(line.key)}
                        aria-label={`Retirer ${line.title} de la commande`}
                        className="text-white/30 transition-colors hover:text-white"
                      >
                        <Trash2 className="h-3.5 w-3.5" strokeWidth={1.8} />
                      </button>
                    </div>
                  </li>
                ))}
              </ul>

              <dl className="mt-4 flex flex-col gap-2 border-t border-white/10 pt-4">
                <div className="flex items-baseline justify-between">
                  <dt className="text-[11px] text-white/55">Sous-total</dt>
                  <dd className="text-[12px] text-[#EDEFF2]">{formatXof(subtotal)}</dd>
                </div>
                <div className="flex items-baseline justify-between">
                  <dt className="text-[11px] text-white/55">Livraison</dt>
                  <dd className="text-[12px] text-[#EDEFF2]">{formatXof(fee)}</dd>
                </div>
                <div className="mt-2 flex items-baseline justify-between border-t border-white/10 pt-3">
                  <dt className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#EDEFF2]">Total</dt>
                  <dd className="font-display text-[22px] text-[#EDEFF2]">{formatXof(total)}</dd>
                </div>
              </dl>

              {/* Erreurs renvoyées par le serveur. Celles sur `items` méritent
                  d'être lues : « il n'en reste que 2 en 42 » se produit quand
                  quelqu'un d'autre a acheté entre-temps. */}
              {(notice || Object.keys(errors).length > 0) && (
                <div className="mt-4 border border-[#E2564A]/40 bg-[#E2564A]/[0.08] px-4 py-3">
                  {notice && <p className="text-[11px] leading-[1.6] text-[#F2A79E]">{notice}</p>}
                  {Object.entries(errors)
                    .filter(([key]) => !key.includes('.'))
                    .map(([key, message]) => (
                      <p key={key} className="text-[11px] leading-[1.6] text-[#F2A79E]">
                        {message}
                      </p>
                    ))}
                </div>
              )}

              <button
                type="submit"
                disabled={submitting || zone === ''}
                className="mt-5 w-full bg-white px-6 py-3.5 text-[11px] font-bold uppercase tracking-[0.14em] text-[#141516] transition-opacity hover:opacity-90 disabled:opacity-50"
              >
                {submitting ? 'Enregistrement…' : 'Confirmer la commande'}
              </button>
              <button
                type="button"
                onClick={() => navigate('/boutique')}
                className="mt-2 w-full px-6 py-2 text-[10px] uppercase tracking-[0.14em] text-white/50 transition-colors hover:text-white"
              >
                Continuer mes achats
              </button>
              <p className="mt-3 text-[10px] leading-[1.6] text-white/35">
                En confirmant, vous acceptez nos{' '}
                <Link to="/cgv" className="underline underline-offset-2 hover:text-white">
                  conditions générales de vente
                </Link>
                .
              </p>
            </aside>
          </form>
        )}
      </div>

      <Footer />
    </div>
  );
}
