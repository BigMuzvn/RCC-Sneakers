import { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Check, X } from 'lucide-react';
import AuthShell from './AuthShell';
import { ApiFailure, api } from '../api/client';
import { useAuth } from '../context/auth-context';

type State = 'verifying' | 'done' | 'failed';

/** Page d'atterrissage du lien envoyé par e-mail : /compte/verifier?token=… */
export default function VerifierEmail() {
  const [params] = useSearchParams();
  const { refresh } = useAuth();
  const token = params.get('token') ?? '';

  const [state, setState] = useState<State>(token === '' ? 'failed' : 'verifying');
  const [message, setMessage] = useState(
    token === '' ? 'Ce lien est incomplet. Rouvrez-le depuis votre e-mail.' : '',
  );

  // Le jeton est à usage unique : le double appel du mode strict de React en
  // développement consommerait le lien puis afficherait un échec sur le second.
  const attempted = useRef(false);

  useEffect(() => {
    if (token === '' || attempted.current) return;
    attempted.current = true;

    api<{ email_verified: boolean }>('/auth/verify-email', { method: 'POST', body: { token } })
      .then(async () => {
        setState('done');
        // La pastille « vérifié » de l'espace client doit suivre immédiatement.
        await refresh();
      })
      .catch((error: unknown) => {
        setState('failed');
        setMessage(
          error instanceof ApiFailure
            ? (error.fields.token ?? error.message)
            : 'Vérification impossible. Réessayez dans un instant.',
        );
      });
  }, [token, refresh]);

  return (
    <AuthShell
      title={state === 'done' ? 'Adresse confirmée' : 'Vérification'}
      lead={state === 'verifying' ? 'Un instant, nous validons votre lien.' : undefined}
    >
      {state === 'verifying' && (
        <p className="text-[11px] uppercase tracking-[0.18em] text-white/45">Vérification en cours…</p>
      )}

      {state === 'done' && (
        <div className="border border-white/15 bg-white/[0.03] px-6 py-9">
          <span className="flex h-11 w-11 items-center justify-center rounded-full bg-[#EDEFF2] text-[#17191C]">
            <Check className="h-5 w-5" strokeWidth={2.5} />
          </span>
          <p className="mt-4 text-sm font-bold uppercase tracking-[0.12em] text-[#EDEFF2]">
            Votre adresse est confirmée
          </p>
          <p className="mt-2 text-[11px] leading-[1.7] text-white/55">
            Vous recevrez désormais vos confirmations de commande, et vous pourrez réinitialiser votre mot de passe
            si vous l'oubliez.
          </p>
          <Link
            to="/espace-client"
            className="mt-6 inline-block border border-white px-6 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-white transition-colors hover:bg-white hover:text-[#141516]"
          >
            Aller à mon espace
          </Link>
        </div>
      )}

      {state === 'failed' && (
        <div className="border border-[#E2564A]/35 bg-[#E2564A]/[0.06] px-6 py-9">
          <span className="flex h-11 w-11 items-center justify-center rounded-full bg-[#E2564A]/20 text-[#F2A79E]">
            <X className="h-5 w-5" strokeWidth={2.5} />
          </span>
          <p className="mt-4 text-sm font-bold uppercase tracking-[0.12em] text-[#F2A79E]">Lien inutilisable</p>
          <p className="mt-2 text-[11px] leading-[1.7] text-white/60">{message}</p>
          <p className="mt-3 text-[11px] leading-[1.7] text-white/45">
            Un lien de vérification expire au bout de 24 heures et ne fonctionne qu'une fois. Vous pouvez en
            redemander un depuis votre espace client.
          </p>
          <Link
            to="/espace-client"
            className="mt-6 inline-block border border-white px-6 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-white transition-colors hover:bg-white hover:text-[#141516]"
          >
            Demander un nouveau lien
          </Link>
        </div>
      )}
    </AuthShell>
  );
}
