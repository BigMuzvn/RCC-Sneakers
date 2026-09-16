import { useEffect, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import Navbar from './Navbar';
import Footer from './Footer';
import { ApiFailure } from '../api/client';
import { useAuth } from '../context/auth-context';
import visuelConnexion from '../assets/auth-connexion.png';
import visuelInscription from '../assets/auth-inscription.png';

/**
 * Tones sampled from the saturated pixels of each visual. Both images share the red Nike box,
 * so the glows are built on what actually separates them: cool blues/greens against warm browns.
 */
const MODES = {
  connexion: {
    label: 'Connexion',
    title: 'Connexion',
    lead: 'Retrouvez vos commandes, vos tailles enregistrées et vos paires en favori.',
    image: visuelConnexion,
    alt: 'Sélection de sneakers RCC',
    glow: 'radial-gradient(ellipse 66% 62% at 50% 46%, rgba(30,90,158,0.45) 0%, rgba(30,90,56,0.22) 34%, rgba(200,24,40,0.10) 58%, transparent 78%)',
    page: 'radial-gradient(ellipse 95% 58% at 38% 0%, #23456E 0%, #22303F 28%, #161A20 58%, #0A0B0D 100%)',
  },
  inscription: {
    label: 'Inscription',
    title: 'Inscription',
    lead: 'Créez votre compte pour commander en deux clics et suivre vos livraisons.',
    image: visuelInscription,
    alt: 'Sélection de sneakers RCC',
    glow: 'radial-gradient(ellipse 66% 62% at 50% 46%, rgba(158,107,52,0.45) 0%, rgba(160,32,40,0.22) 34%, rgba(120,80,48,0.10) 58%, transparent 78%)',
    page: 'radial-gradient(ellipse 95% 58% at 38% 0%, #6B4A2C 0%, #402A26 28%, #1F1815 58%, #0B0908 100%)',
  },
} as const;

type Mode = keyof typeof MODES;

const inputClass =
  'w-full border border-white/15 bg-white/[0.03] px-3.5 py-3 text-[12px] text-white placeholder:text-white/35 transition-colors focus:border-white/50 focus:outline-none';

const labelClass = 'text-[10px] font-bold uppercase tracking-[0.16em] text-white/60';

/** Raisons d'arrivée sur cette page, pour expliquer pourquoi on y a été conduit. */
const REASONS: Record<string, string> = {
  favori: 'Vos favoris sont rattachés à votre compte. Connectez-vous pour les retrouver partout.',
  commande: 'Un compte est nécessaire pour valider une commande. Votre panier est conservé.',
};

export default function Compte() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const { customer, loading, register, login } = useAuth();

  const [mode, setMode] = useState<Mode>(params.get('mode') === 'inscription' ? 'inscription' : 'connexion');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const current = MODES[mode];
  const isConnexion = mode === 'connexion';
  const reason = REASONS[params.get('retour') ?? ''];
  const next = params.get('suite');

  // Déjà connecté : cette page n'a plus de raison d'être affichée.
  useEffect(() => {
    if (!loading && customer) navigate(next ?? '/espace-client', { replace: true });
  }, [loading, customer, navigate, next]);

  const switchTo = (nextMode: Mode) => {
    if (nextMode === mode) return;
    setMode(nextMode);
    setErrors({});
    setNotice('');
  };

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setErrors({});
    setNotice('');
    setSubmitting(true);

    const data = new FormData(event.currentTarget);

    try {
      if (isConnexion) {
        await login(
          String(data.get('identifier') ?? ''),
          String(data.get('password') ?? ''),
          data.get('remember') === 'on',
        );
      } else {
        await register({
          name: String(data.get('name') ?? ''),
          email: String(data.get('email') ?? ''),
          phone: String(data.get('phone') ?? ''),
          password: String(data.get('password') ?? ''),
          terms: data.get('terms') === 'on',
        });
      }

      navigate(next ?? '/espace-client', { replace: true });
    } catch (error) {
      setSubmitting(false);

      if (error instanceof ApiFailure) {
        setErrors(error.fields);
        // Une erreur sans champ associé — identifiants refusés, trop de
        // tentatives, réseau coupé — doit quand même s'afficher quelque part.
        if (Object.keys(error.fields).length === 0) setNotice(error.message);
      } else {
        setNotice('Une erreur est survenue. Réessayez dans un instant.');
      }
    }
  };

  return (
    <div className="relative flex min-h-[100svh] w-full flex-col overflow-x-clip">
      {/* page grounds, crossfaded so the whole page follows the toggle */}
      {(Object.keys(MODES) as Mode[]).map((key) => (
        <div
          key={key}
          aria-hidden="true"
          className={`fixed inset-0 z-0 transition-opacity duration-700 ease-out motion-reduce:transition-none ${
            key === mode ? 'opacity-100' : 'opacity-0'
          }`}
          style={{ background: MODES[key].page }}
        />
      ))}

      <div className="relative z-10 flex-1 px-4 py-4 sm:px-8 sm:py-6 lg:px-14 lg:py-8">
        <Navbar />

        {/* ---------- MOBILE / TABLET: the visual becomes a lit band above the form ---------- */}
        <div className="relative -mx-4 mt-4 h-[210px] overflow-hidden sm:-mx-8 sm:mt-6 sm:h-[260px] lg:hidden">
          {(Object.keys(MODES) as Mode[]).map((key) => (
            <div
              key={key}
              aria-hidden="true"
              className={`absolute inset-0 transition-opacity duration-700 ease-out motion-reduce:transition-none ${
                key === mode ? 'opacity-100' : 'opacity-0'
              }`}
              style={{ background: MODES[key].glow }}
            />
          ))}
          {(Object.keys(MODES) as Mode[]).map((key) => (
            <img
              key={key}
              src={MODES[key].image}
              alt=""
              aria-hidden="true"
              className={`absolute inset-0 h-full w-full object-cover object-top transition-[opacity,transform] duration-700 ease-out motion-reduce:transition-none ${
                key === mode ? 'scale-100 opacity-100' : 'scale-105 opacity-0'
              }`}
            />
          ))}
          {/* fades into the page so the band has no hard edge behind the form */}
          <div
            aria-hidden="true"
            className="absolute inset-0"
            style={{
              background:
                'linear-gradient(to bottom, transparent 0%, transparent 42%, rgba(10,11,13,0.55) 72%, rgba(10,11,13,0.95) 100%)',
            }}
          />
        </div>

        {/* items-start, not items-center: the title stays put and only the form's length changes on toggle */}
        <div className="mt-8 grid grid-cols-1 items-start gap-8 sm:mt-10 lg:mt-14 lg:grid-cols-2 lg:gap-14">
          {/* ---------- VISUAL ---------- */}
          {/* only shown where the two-column layout exists — the 12-shoe grid needs width to read */}
          <div className="relative order-2 hidden aspect-[5/7] items-center justify-center lg:order-1 lg:flex">
            {(Object.keys(MODES) as Mode[]).map((key) => (
              <div
                key={key}
                aria-hidden="true"
                className={`absolute inset-0 transition-opacity duration-700 ease-out motion-reduce:transition-none ${
                  key === mode ? 'opacity-100' : 'opacity-0'
                }`}
                style={{ background: MODES[key].glow }}
              />
            ))}
            {(Object.keys(MODES) as Mode[]).map((key) => (
              <img
                key={key}
                src={MODES[key].image}
                alt={key === mode ? MODES[key].alt : ''}
                aria-hidden={key !== mode}
                className={`absolute inset-0 h-full w-full object-contain p-2 drop-shadow-[0_28px_45px_rgba(0,0,0,0.5)] transition-[opacity,transform] duration-700 ease-out motion-reduce:transition-none ${
                  key === mode ? 'scale-100 opacity-100' : 'pointer-events-none scale-95 opacity-0'
                }`}
              />
            ))}
          </div>

          {/* ---------- FORM ---------- */}
          <div className="order-1 w-full max-w-md justify-self-center lg:order-2 lg:justify-self-start">
            <span className="block font-display text-[11px] uppercase leading-none tracking-[0.3em] text-white/25 sm:text-[13px]">
              RCC
            </span>
            {/* readable rather than a watermark: this heading has to be legible on a form */}
            <h1 className="mt-2 font-display uppercase leading-none tracking-[-0.01em] text-[#EDEFF2] text-[clamp(30px,5vw,48px)]">
              {current.title}
            </h1>
            <p key={`${mode}-lead`} className="mt-3 animate-slide-in text-[11px] leading-[1.7] text-white/55 motion-reduce:animate-none sm:text-xs">
              {current.lead}
            </p>

            {/* ---------- SWITCH ---------- */}
            <div className="mt-7 flex items-center gap-3">
              <span
                className={`text-[10px] font-bold uppercase tracking-[0.16em] transition-colors sm:text-[11px] ${
                  isConnexion ? 'text-[#EDEFF2]' : 'text-white/40'
                }`}
              >
                Connexion
              </span>
              <button
                type="button"
                role="switch"
                aria-checked={isConnexion}
                aria-label={isConnexion ? 'Passer à l’inscription' : 'Passer à la connexion'}
                onClick={() => switchTo(isConnexion ? 'inscription' : 'connexion')}
                className={`relative h-7 w-14 shrink-0 rounded-full border transition-colors duration-300 motion-reduce:transition-none ${
                  isConnexion ? 'border-[#EDEFF2] bg-[#EDEFF2]' : 'border-white/30 bg-white/10'
                }`}
              >
                <span
                  className={`absolute top-1/2 h-5 w-5 -translate-y-1/2 rounded-full transition-all duration-300 ease-out motion-reduce:transition-none ${
                    isConnexion ? 'left-[calc(100%-1.375rem)] bg-[#17191C]' : 'left-1 bg-[#EDEFF2]'
                  }`}
                />
              </button>
              <span
                className={`text-[10px] font-bold uppercase tracking-[0.16em] transition-colors sm:text-[11px] ${
                  isConnexion ? 'text-white/40' : 'text-[#EDEFF2]'
                }`}
              >
                Inscription
              </span>
            </div>

            {/* ---------- FIELDS ---------- */}
            {reason && (
              <p className="mt-6 border border-white/15 bg-white/[0.04] px-4 py-3 text-[11px] leading-[1.6] text-white/70">
                {reason}
              </p>
            )}

            <form
              key={mode}
              className="mt-7 flex animate-slide-in flex-col gap-4 motion-reduce:animate-none"
              onSubmit={handleSubmit}
            >
              {!isConnexion && (
                <Field
                  label="Nom complet"
                  name="name"
                  autoComplete="name"
                  placeholder="Votre nom"
                  error={errors.name}
                />
              )}

              <Field
                label={isConnexion ? 'E-mail ou téléphone' : 'E-mail'}
                name={isConnexion ? 'identifier' : 'email'}
                type={isConnexion ? 'text' : 'email'}
                autoComplete={isConnexion ? 'username' : 'email'}
                placeholder="vous@exemple.com"
                error={errors.identifier ?? errors.email}
              />

              {!isConnexion && (
                <Field
                  label="Téléphone"
                  name="phone"
                  type="tel"
                  autoComplete="tel"
                  placeholder="+229 01 97 ..."
                  error={errors.phone}
                />
              )}

              <Field
                label="Mot de passe"
                name="password"
                type="password"
                autoComplete={isConnexion ? 'current-password' : 'new-password'}
                placeholder={isConnexion ? 'Votre mot de passe' : '8 caractères minimum'}
                error={errors.password}
              />

              {!isConnexion && (
                <>
                  <label className="flex items-start gap-2.5">
                    <input required type="checkbox" name="terms" className="mt-0.5 h-3.5 w-3.5 shrink-0 accent-[#EDEFF2]" />
                    <span className="text-[10px] leading-[1.6] text-white/55">
                      J'accepte les{' '}
                      <Link to="/cgv" className="text-white/80 underline underline-offset-2 hover:text-white">
                        conditions générales de vente
                      </Link>{' '}
                      et la{' '}
                      <Link to="/confidentialite" className="text-white/80 underline underline-offset-2 hover:text-white">
                        politique de confidentialité
                      </Link>
                      .
                    </span>
                  </label>
                  {errors.terms && <span className="-mt-2 text-[10px] text-[#E2564A]">{errors.terms}</span>}
                </>
              )}

              {isConnexion && (
                <div className="flex items-center justify-between gap-3">
                  <label className="flex items-center gap-2.5">
                    <input type="checkbox" name="remember" className="h-3.5 w-3.5 shrink-0 accent-[#EDEFF2]" />
                    <span className="text-[10px] uppercase tracking-[0.12em] text-white/55">Se souvenir de moi</span>
                  </label>
                  <Link
                    to="/compte/mot-de-passe-oublie"
                    className="text-[10px] uppercase tracking-[0.12em] text-white/55 transition-colors hover:text-white"
                  >
                    Mot de passe oublié ?
                  </Link>
                </div>
              )}

              {/* Erreur générale : identifiants refusés, trop de tentatives,
                  réseau coupé — rien de tout cela ne vise un champ précis. */}
              {notice && (
                <p className="border border-[#E2564A]/40 bg-[#E2564A]/[0.08] px-4 py-3 text-[11px] leading-[1.6] text-[#F2A79E]">
                  {notice}
                </p>
              )}

              <button
                type="submit"
                disabled={submitting}
                className="mt-1 w-full bg-white px-6 py-3.5 text-[11px] font-bold uppercase tracking-[0.12em] text-[#141516] transition-opacity hover:opacity-90 disabled:opacity-55 sm:tracking-[0.14em]"
              >
                {submitting
                  ? isConnexion
                    ? 'Connexion…'
                    : 'Création…'
                  : isConnexion
                    ? 'Se connecter'
                    : 'Créer mon compte'}
              </button>

              <p className="text-[10px] leading-[1.6] text-white/40">
                {isConnexion ? 'Pas encore de compte ?' : 'Vous avez déjà un compte ?'}{' '}
                <button
                  type="button"
                  onClick={() => switchTo(isConnexion ? 'inscription' : 'connexion')}
                  className="font-bold uppercase tracking-[0.1em] text-white/75 underline underline-offset-2 transition-colors hover:text-white"
                >
                  {isConnexion ? 'Créer un compte' : 'Se connecter'}
                </button>
              </p>
            </form>
          </div>
        </div>
      </div>

      <Footer />
    </div>
  );
}

type FieldProps = {
  label: string;
  name: string;
  error?: string;
  type?: string;
  placeholder?: string;
  autoComplete?: string;
};

/**
 * Champ de formulaire non contrôlé — la valeur est lue par FormData à l'envoi.
 * L'erreur vient du serveur : c'est lui qui détient les règles, et les
 * dupliquer côté navigateur garantirait qu'elles divergent un jour.
 */
function Field({ label, name, error, type = 'text', placeholder, autoComplete }: FieldProps) {
  return (
    <label className="flex flex-col gap-2">
      <span className={labelClass}>{label}</span>
      <input
        required
        type={type}
        name={name}
        placeholder={placeholder}
        autoComplete={autoComplete}
        aria-invalid={error ? true : undefined}
        className={`${inputClass} ${error ? 'border-[#E2564A]/70' : ''}`}
      />
      {error && <span className="text-[10px] leading-[1.5] text-[#E2564A]">{error}</span>}
    </label>
  );
}
