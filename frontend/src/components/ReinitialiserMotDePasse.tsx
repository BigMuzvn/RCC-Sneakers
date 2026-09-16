import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Check } from 'lucide-react';
import AuthShell from './AuthShell';
import { ApiFailure, api } from '../api/client';

/** Page d'atterrissage du lien envoyé par e-mail : /compte/reinitialiser?token=… */
export default function ReinitialiserMotDePasse() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const token = params.get('token') ?? '';

  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);

  if (token === '') {
    return (
      <AuthShell title="Lien incomplet">
        <div className="border border-[#E2564A]/35 bg-[#E2564A]/[0.06] px-6 py-8">
          <p className="text-[11px] leading-[1.7] text-white/65">
            Ce lien ne contient pas de jeton. Rouvrez-le directement depuis votre e-mail, sans le recopier.
          </p>
          <Link
            to="/compte/mot-de-passe-oublie"
            className="mt-6 inline-block border border-white px-6 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-white transition-colors hover:bg-white hover:text-[#141516]"
          >
            Demander un nouveau lien
          </Link>
        </div>
      </AuthShell>
    );
  }

  if (done) {
    return (
      <AuthShell title="Mot de passe modifié">
        <div className="border border-white/15 bg-white/[0.03] px-6 py-9">
          <span className="flex h-11 w-11 items-center justify-center rounded-full bg-[#EDEFF2] text-[#17191C]">
            <Check className="h-5 w-5" strokeWidth={2.5} />
          </span>
          <p className="mt-4 text-[11px] leading-[1.7] text-white/65">
            Votre mot de passe a été changé. Par sécurité, toutes vos sessions ouvertes ont été fermées — y compris
            sur vos autres appareils.
          </p>
          <button
            type="button"
            onClick={() => navigate('/compte')}
            className="mt-6 border border-white px-6 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-white transition-colors hover:bg-white hover:text-[#141516]"
          >
            Se connecter
          </button>
        </div>
      </AuthShell>
    );
  }

  return (
    <AuthShell title="Nouveau mot de passe" lead="Choisissez un mot de passe d'au moins 8 caractères.">
      <form
        className="flex flex-col gap-4"
        onSubmit={(event) => {
          event.preventDefault();

          // Vérifié ici et non côté serveur : la confirmation n'existe que
          // pour attraper une faute de frappe, l'API n'a pas à la connaître.
          if (password !== confirmation) {
            setErrors({ confirmation: 'Les deux mots de passe ne correspondent pas.' });
            return;
          }

          setErrors({});
          setSubmitting(true);

          api<{ message: string }>('/auth/reset-password', { method: 'POST', body: { token, password } })
            .then(() => setDone(true))
            .catch((error: unknown) => {
              setSubmitting(false);
              setErrors(
                error instanceof ApiFailure
                  ? Object.keys(error.fields).length > 0
                    ? error.fields
                    : { password: error.message }
                  : { password: 'Une erreur est survenue. Réessayez dans un instant.' },
              );
            });
        }}
      >
        {errors.token && (
          <p className="border border-[#E2564A]/40 bg-[#E2564A]/[0.08] px-4 py-3 text-[11px] leading-[1.6] text-[#F2A79E]">
            {errors.token}{' '}
            <Link to="/compte/mot-de-passe-oublie" className="underline underline-offset-2">
              Demander un nouveau lien
            </Link>
          </p>
        )}

        <PasswordField
          label="Nouveau mot de passe"
          value={password}
          onChange={setPassword}
          error={errors.password}
          autoComplete="new-password"
          placeholder="8 caractères minimum"
        />
        <PasswordField
          label="Confirmer"
          value={confirmation}
          onChange={setConfirmation}
          error={errors.confirmation}
          autoComplete="new-password"
        />

        <button
          type="submit"
          disabled={submitting}
          className="mt-1 w-full bg-white px-6 py-3.5 text-[11px] font-bold uppercase tracking-[0.12em] text-[#141516] transition-opacity hover:opacity-90 disabled:opacity-55"
        >
          {submitting ? 'Modification…' : 'Modifier mon mot de passe'}
        </button>
      </form>
    </AuthShell>
  );
}

function PasswordField({
  label,
  value,
  onChange,
  error,
  autoComplete,
  placeholder,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  autoComplete?: string;
  placeholder?: string;
}) {
  return (
    <label className="flex flex-col gap-2">
      <span className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/60">{label}</span>
      <input
        required
        type="password"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        autoComplete={autoComplete}
        placeholder={placeholder}
        aria-invalid={error ? true : undefined}
        className={`w-full border bg-white/[0.03] px-3.5 py-3 text-[12px] text-white placeholder:text-white/35 transition-colors focus:outline-none ${
          error ? 'border-[#E2564A]/70' : 'border-white/15 focus:border-white/50'
        }`}
      />
      {error && <span className="text-[10px] leading-[1.5] text-[#E2564A]">{error}</span>}
    </label>
  );
}
