import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Mail } from 'lucide-react';
import AuthShell from './AuthShell';
import { ApiFailure, api } from '../api/client';

export default function MotDePasseOublie() {
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  if (sent) {
    return (
      <AuthShell title="Vérifiez vos e-mails">
        <div className="border border-white/15 bg-white/[0.03] px-6 py-9">
          <span className="flex h-11 w-11 items-center justify-center rounded-full bg-[#EDEFF2] text-[#17191C]">
            <Mail className="h-5 w-5" strokeWidth={2.2} />
          </span>
          {/* Formulation volontairement conditionnelle : confirmer qu'un compte
              existe transformerait cette page en moyen de vérifier qui est
              client de la boutique. */}
          <p className="mt-4 text-[11px] leading-[1.7] text-white/65">
            Si un compte existe pour <span className="text-[#EDEFF2]">{email}</span>, un lien vient d'y être envoyé.
          </p>
          <p className="mt-3 text-[11px] leading-[1.7] text-white/45">
            Le lien est valable une heure et ne fonctionne qu'une fois. Pensez à regarder dans vos indésirables.
          </p>

          {/* Sortie de secours. La réponse ci-dessus est volontairement
              conditionnelle — confirmer qu'un compte existe transformerait ce
              formulaire en annuaire de la clientèle — mais sans cette porte,
              quelqu'un qui n'a pas encore de compte attend indéfiniment un
              message qui ne partira jamais. Le lien ne révèle rien : il est
              montré à tout le monde. */}
          <p className="mt-4 border-t border-white/10 pt-4 text-[11px] leading-[1.7] text-white/45">
            Rien reçu au bout de quelques minutes ? Il se peut que cette adresse n'ait pas encore de compte chez
            nous.{' '}
            <Link
              to="/compte?mode=inscription"
              className="font-bold uppercase tracking-[0.1em] text-white/75 underline underline-offset-2 transition-colors hover:text-white"
            >
              En créer un
            </Link>
          </p>

          <Link
            to="/compte"
            className="mt-6 inline-block border border-white px-6 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-white transition-colors hover:bg-white hover:text-[#141516]"
          >
            Retour à la connexion
          </Link>
        </div>
      </AuthShell>
    );
  }

  return (
    <AuthShell
      title="Mot de passe oublié"
      lead="Indiquez l'adresse e-mail de votre compte. Nous vous enverrons un lien pour en choisir un nouveau."
    >
      <form
        className="flex flex-col gap-4"
        onSubmit={(event) => {
          event.preventDefault();
          setError('');
          setSubmitting(true);

          api<{ message: string }>('/auth/forgot-password', { method: 'POST', body: { email } })
            .then(() => setSent(true))
            .catch((err: unknown) => {
              setSubmitting(false);
              setError(
                err instanceof ApiFailure
                  ? (err.fields.email ?? err.message)
                  : 'Envoi impossible. Réessayez dans un instant.',
              );
            });
        }}
      >
        <label className="flex flex-col gap-2">
          <span className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/60">E-mail</span>
          <input
            required
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            autoComplete="email"
            placeholder="vous@exemple.com"
            aria-invalid={error ? true : undefined}
            className={`w-full border bg-white/[0.03] px-3.5 py-3 text-[12px] text-white placeholder:text-white/35 transition-colors focus:outline-none ${
              error ? 'border-[#E2564A]/70' : 'border-white/15 focus:border-white/50'
            }`}
          />
          {error && <span className="text-[10px] leading-[1.5] text-[#E2564A]">{error}</span>}
        </label>

        <button
          type="submit"
          disabled={submitting}
          className="mt-1 w-full bg-white px-6 py-3.5 text-[11px] font-bold uppercase tracking-[0.12em] text-[#141516] transition-opacity hover:opacity-90 disabled:opacity-55"
        >
          {submitting ? 'Envoi…' : 'Envoyer le lien'}
        </button>

        <p className="text-[10px] leading-[1.6] text-white/40">
          Vous vous en souvenez ?{' '}
          <Link
            to="/compte"
            className="font-bold uppercase tracking-[0.1em] text-white/75 underline underline-offset-2 transition-colors hover:text-white"
          >
            Se connecter
          </Link>
        </p>
      </form>
    </AuthShell>
  );
}
