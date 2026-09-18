import { useState } from 'react';
import { ApiFailure } from '../../api/client';
import { useAuth } from '../../context/auth-context';
import { Button, PageTitle, TextField, cardClass } from './ui';

/**
 * Le compte de la personne connectée.
 *
 * Cet écran appartient à **tous** les administrateurs, et pas seulement au
 * premier : il vivait d'abord dans les réglages, qui sont devenus le domaine du
 * super administrateur. Un administrateur ajouté s'y serait retrouvé sans aucun
 * moyen de changer son propre mot de passe — ni ici, ni dans l'espace client,
 * dont l'administration le détourne.
 *
 * Il n'a pas d'entrée dans le menu : on y accède par son nom, en bas de la
 * barre latérale, là où on le cherche.
 */
export default function AdminAccount() {
  return (
    <>
      <PageTitle
        title="Mon compte"
        lead="Vos identifiants d'administrateur. Personne d'autre ne peut les changer à votre place."
      />

      <div className="max-w-xl">
        <MonCompte />
      </div>
    </>
  );
}

/** La carte elle-même : coordonnées d'un côté, mot de passe de l'autre. */
function MonCompte() {
  const { customer, updateProfile, updatePassword } = useAuth();

  const [profil, setProfil] = useState({
    name: customer?.name ?? '',
    email: customer?.email ?? '',
    phone: customer?.phone ?? '',
  });
  const [profilErrors, setProfilErrors] = useState<Record<string, string>>({});
  const [profilMessage, setProfilMessage] = useState('');
  const [profilEnCours, setProfilEnCours] = useState(false);

  const [motsDePasse, setMotsDePasse] = useState({ current_password: '', password: '' });
  const [passErrors, setPassErrors] = useState<Record<string, string>>({});
  const [passMessage, setPassMessage] = useState('');
  const [passEnCours, setPassEnCours] = useState(false);

  const champs = (error: unknown, repli: string): Record<string, string> => {
    if (!(error instanceof ApiFailure)) return { _: repli };
    return Object.keys(error.fields).length > 0 ? error.fields : { _: error.message };
  };

  return (
    <section className={`${cardClass} p-5 sm:p-6`}>
      <p className="text-[10px] leading-[1.6] text-white/35">
        Changer de mot de passe déconnecte vos autres appareils ; celui-ci reste connecté.
      </p>

      <div className="mt-4 flex flex-col gap-4">
        <TextField
          label="Nom complet"
          name="admin_name"
          value={profil.name}
          onChange={(v) => setProfil((c) => ({ ...c, name: v }))}
          error={profilErrors.name}
        />
        <TextField
          label="E-mail"
          name="admin_email"
          type="email"
          value={profil.email}
          onChange={(v) => setProfil((c) => ({ ...c, email: v }))}
          error={profilErrors.email}
          hint={profil.email !== customer?.email ? 'Un lien de vérification partira vers la nouvelle adresse.' : undefined}
        />
        <TextField
          label="Téléphone"
          name="admin_phone"
          type="tel"
          value={profil.phone}
          onChange={(v) => setProfil((c) => ({ ...c, phone: v }))}
          error={profilErrors.phone}
        />
      </div>

      {(profilMessage || profilErrors._) && (
        <p className={`mt-3 text-[10px] leading-[1.6] ${profilErrors._ ? 'text-[#E2564A]' : 'text-[#7FCB9B]'}`}>
          {profilErrors._ || profilMessage}
        </p>
      )}

      <Button
        disabled={profilEnCours}
        className="mt-4"
        onClick={() => {
          setProfilEnCours(true);
          setProfilErrors({});
          setProfilMessage('');

          updateProfile(profil)
            .then(() => setProfilMessage('Coordonnées enregistrées.'))
            .catch((error: unknown) => setProfilErrors(champs(error, 'Enregistrement impossible.')))
            .finally(() => setProfilEnCours(false));
        }}
      >
        {profilEnCours ? 'Enregistrement…' : 'Enregistrer'}
      </Button>

      <div className="mt-6 flex flex-col gap-4 border-t border-white/10 pt-5">
        <TextField
          label="Mot de passe actuel"
          name="admin_current_password"
          type="password"
          value={motsDePasse.current_password}
          onChange={(v) => setMotsDePasse((c) => ({ ...c, current_password: v }))}
          error={passErrors.current_password}
        />
        <TextField
          label="Nouveau mot de passe"
          name="admin_password"
          type="password"
          value={motsDePasse.password}
          onChange={(v) => setMotsDePasse((c) => ({ ...c, password: v }))}
          error={passErrors.password}
          placeholder="8 caractères minimum"
        />
      </div>

      {(passMessage || passErrors._) && (
        <p className={`mt-3 text-[10px] leading-[1.6] ${passErrors._ ? 'text-[#E2564A]' : 'text-[#7FCB9B]'}`}>
          {passErrors._ || passMessage}
        </p>
      )}

      <Button
        variant="ghost"
        disabled={passEnCours}
        className="mt-4"
        onClick={() => {
          setPassEnCours(true);
          setPassErrors({});
          setPassMessage('');

          updatePassword(motsDePasse.current_password, motsDePasse.password)
            .then(() => {
              setPassMessage('Mot de passe modifié.');
              setMotsDePasse({ current_password: '', password: '' });
            })
            .catch((error: unknown) => setPassErrors(champs(error, 'Modification impossible.')))
            .finally(() => setPassEnCours(false));
        }}
      >
        {passEnCours ? 'Modification…' : 'Modifier le mot de passe'}
      </Button>
    </section>
  );
}
