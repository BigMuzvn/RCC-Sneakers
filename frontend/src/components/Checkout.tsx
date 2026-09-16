import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Check, ShoppingBag, Trash2 } from 'lucide-react';
import Navbar from './Navbar';
import Footer from './Footer';
import HangingShoe from './HangingShoe';
import hangingDunk from '../assets/hanging-dunk-russet.png';
import { useCart } from '../context/cart-context';
import { formatXof } from '../utils/format';

const PAGE_GRADIENT =
  'radial-gradient(ellipse 95% 58% at 58% 0%, #6B3520 0%, #40241C 28%, #1F1614 58%, #0B0807 100%)';

/** Placeholder tariffs — confirm with the actual couriers before launch. */
const ZONES = [
  { id: 'cotonou', label: 'Cotonou', delay: 'sous 24 h', fee: 1000 },
  { id: 'nokoue', label: 'Grand Nokoué', delay: 'sous 48 h', fee: 1500 },
  { id: 'benin', label: 'Reste du Bénin', delay: 'sous 72 h', fee: 2500 },
] as const;

/** One online option: the aggregator handles every mobile network and cards behind the same flow. */
const PAYMENTS = [
  {
    id: 'online',
    label: 'Paiement mobile / carte bancaire',
    hint: 'Tous réseaux mobile money et cartes Visa ou Mastercard',
  },
  { id: 'cash', label: 'Espèces à la livraison', hint: 'Vous réglez au livreur à la réception' },
] as const;

const inputClass =
  'w-full border border-white/15 bg-white/[0.03] px-3.5 py-3 text-[12px] text-white placeholder:text-white/35 transition-colors focus:border-white/50 focus:outline-none';
const labelClass = 'text-[10px] font-bold uppercase tracking-[0.16em] text-white/60';

export default function Checkout() {
  const { lines, subtotal, clear, remove } = useCart();
  const navigate = useNavigate();
  const [zone, setZone] = useState<(typeof ZONES)[number]['id']>('cotonou');
  const [payment, setPayment] = useState<(typeof PAYMENTS)[number]['id']>('online');
  const [reference, setReference] = useState<string | null>(null);

  useEffect(() => {
    window.scrollTo(0, 0);
  }, []);

  const fee = ZONES.find((item) => item.id === zone)?.fee ?? 0;
  const total = subtotal + fee;
  const summary = useMemo(() => lines, [lines]);

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
            onSubmit={(event) => {
              event.preventDefault();
              // TODO: POST /api/orders — nothing is persisted, the reference is generated client-side
              setReference(`RCC-${String(Date.now()).slice(-6)}`);
              clear();
            }}
          >
            {/* ---------- FORM ---------- */}
            <div className="flex flex-col gap-9">
              <section>
                <h2 className="text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2]">1. Vos coordonnées</h2>
                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <label className="flex flex-col gap-2">
                    <span className={labelClass}>Nom complet</span>
                    <input required name="name" autoComplete="name" placeholder="Votre nom" className={inputClass} />
                  </label>
                  <label className="flex flex-col gap-2">
                    <span className={labelClass}>Téléphone</span>
                    <input required name="phone" type="tel" autoComplete="tel" placeholder="+229 ..." className={inputClass} />
                  </label>
                  <label className="flex flex-col gap-2 sm:col-span-2">
                    <span className={labelClass}>E-mail</span>
                    <input required name="email" type="email" autoComplete="email" placeholder="vous@exemple.com" className={inputClass} />
                  </label>
                </div>
              </section>

              <section>
                <h2 className="text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2]">2. Livraison</h2>
                <div className="mt-4 flex flex-col gap-2.5">
                  {ZONES.map((item) => (
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
                          <span className="block text-[10px] text-white/45">Livraison {item.delay}</span>
                        </span>
                      </span>
                      <span className="font-display text-[13px] text-[#EDEFF2]">{formatXof(item.fee)}</span>
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
                      className={`flex cursor-pointer flex-col gap-1 border px-4 py-3.5 transition-colors ${
                        payment === item.id ? 'border-[#EDEFF2] bg-white/[0.06]' : 'border-white/15 hover:border-white/35'
                      }`}
                    >
                      <span className="flex items-center gap-2.5">
                        <input
                          type="radio"
                          name="payment"
                          value={item.id}
                          checked={payment === item.id}
                          onChange={() => setPayment(item.id)}
                          className="h-3.5 w-3.5 shrink-0 accent-[#EDEFF2]"
                        />
                        <span className="text-[12px] font-bold uppercase tracking-[0.08em] text-[#EDEFF2]">
                          {item.label}
                        </span>
                      </span>
                      <span className="pl-6 text-[10px] leading-[1.6] text-white/45">{item.hint}</span>
                    </label>
                  ))}
                </div>

                {payment === 'online' && (
                  <p className="mt-4 border border-white/10 bg-white/[0.02] px-4 py-3 text-[10px] leading-[1.7] text-white/50">
                    Vous serez redirigé vers la page sécurisée de notre prestataire de paiement pour choisir votre
                    réseau mobile ou saisir votre carte.
                  </p>
                )}
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

              <button
                type="submit"
                className="mt-5 w-full bg-white px-6 py-3.5 text-[11px] font-bold uppercase tracking-[0.14em] text-[#141516] transition-opacity hover:opacity-90"
              >
                Confirmer la commande
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
