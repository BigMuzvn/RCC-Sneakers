import { useState } from 'react';
import { Check, Clock, Mail, MapPin, Phone } from 'lucide-react';
import { ApiFailure, api } from '../api/client';
import Navbar from './Navbar';
import Footer from './Footer';
import HangingShoe from './HangingShoe';
import hangingAirMax1 from '../assets/hanging-airmax1.png';
import { useCatalogue } from '../context/catalogue-context';
import type { ShopSettings } from '../api/catalogue';

const PAGE_GRADIENT =
  'radial-gradient(ellipse 95% 58% at 45% 0%, #6B4A3E 0%, #453035 26%, #241E24 56%, #0C0C0E 100%)';

/**
 * Les coordonnées viennent des réglages, et une case laissée vide fait
 * disparaître son encadré. La page portait auparavant des horaires inventés et
 * un numéro d'exemple : mieux vaut une coordonnée de moins qu'une fausse.
 */
const channelsOf = (settings: ShopSettings) =>
  [
    { icon: MapPin, label: 'Adresse', lines: [settings.shop_city] },
    { icon: Phone, label: 'Téléphone & WhatsApp', lines: [settings.shop_phone] },
    { icon: Mail, label: 'E-mail', lines: [settings.shop_email] },
    { icon: Clock, label: 'Horaires', lines: [settings.shop_hours] },
  ].filter((channel) => channel.lines.some((line) => line !== ''));

const SUBJECTS = ['Disponibilité d’une paire', 'Suivi de commande', 'Retour ou échange', 'Autre demande'];

const inputClass =
  'w-full border border-white/15 bg-white/[0.03] px-3.5 py-3 text-[12px] text-white placeholder:text-white/35 transition-colors focus:border-white/50 focus:outline-none';

/** Un champ refusé se signale par sa bordure, avant même qu'on lise le motif. */
const champ = (erreur?: string) => `${inputClass}${erreur ? ' border-[#E2564A]/70' : ''}`;

function Erreur({ message }: { message?: string }) {
  if (!message) return null;

  return <span className="text-[10px] leading-[1.5] text-[#E2564A]">{message}</span>;
}

export default function Contact() {
  const { settings } = useCatalogue();
  const [sent, setSent] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState('');
  const [sending, setSending] = useState(false);

  const channels = channelsOf(settings);

  /**
   * Le message part réellement vers `POST /api/contact`, qui l'enregistre,
   * prévient la boutique et alimente l'écran « Messagerie » de
   * l'administration. Ce formulaire se contentait d'afficher une confirmation :
   * le client croyait avoir écrit, et personne ne recevait rien.
   */
  const envoyer = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setErrors({});
    setNotice('');
    setSending(true);

    const data = new FormData(event.currentTarget);

    try {
      const reponse = await api<{ message: string }>('/contact', {
        method: 'POST',
        body: {
          name: String(data.get('name') ?? ''),
          email: String(data.get('email') ?? ''),
          phone: String(data.get('phone') ?? ''),
          subject: String(data.get('subject') ?? ''),
          message: String(data.get('message') ?? ''),
        },
      });

      // On affiche la phrase du serveur plutôt qu'une phrase écrite ici : le
      // délai de réponse annoncé doit tenir à un seul endroit.
      setSent(reponse.message);
    } catch (error) {
      setSending(false);

      if (error instanceof ApiFailure) {
        setErrors(error.fields);
        // Trop d'envois, réseau coupé : une erreur sans champ associé doit
        // quand même s'afficher quelque part.
        if (Object.keys(error.fields).length === 0) setNotice(error.message);
      } else {
        setNotice('Une erreur est survenue. Réessayez dans un instant.');
      }

      return;
    }

    setSending(false);
  };

  return (
    <div className="relative flex min-h-[100svh] w-full flex-col overflow-x-clip">
      <div aria-hidden="true" className="fixed inset-0 z-0" style={{ background: PAGE_GRADIENT }} />
      {/* Air Max 1 Wild West — gum amber + camo taupe */}
      <HangingShoe image={hangingAirMax1} primary="#C67A2E" secondary="#8A6B45" />

      <div className="relative z-10 flex-1 px-4 py-4 sm:px-8 sm:py-6 lg:px-14 lg:py-8">
        <Navbar />

        {/* ---------- TITLE ---------- */}
        <header className="relative mt-14 select-none sm:mt-20">
          <div className="relative w-fit">
            <span
              aria-hidden="true"
              className="absolute bottom-full left-0 mb-[-0.45em] font-display uppercase leading-none tracking-[0.3em] text-white/25 text-[clamp(11px,2.6vw,34px)]"
            >
              RCC
            </span>
            <h1 className="whitespace-nowrap font-display uppercase leading-none tracking-[-0.02em] text-white/[0.16] text-[clamp(38px,11vw,142px)]">
              Contact
            </h1>
          </div>

          <p className="mt-5 max-w-md text-[11px] leading-[1.7] text-white/55 sm:mt-6 sm:text-xs">
            Une question sur une paire, une taille ou une commande en cours ? Écrivez-nous, nous répondons sous 24 h
            ouvrées.
          </p>
        </header>

        <div className="mt-10 grid grid-cols-1 items-start gap-8 pb-4 lg:grid-cols-[1fr_1fr] lg:gap-14 xl:grid-cols-[1.1fr_1fr]">
          {/* ---------- FORM ---------- */}
          <section>
            <h2 className="text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2]">Écrivez-nous</h2>

            {sent !== '' ? (
              <div className="mt-5 flex flex-col items-start border border-white/15 bg-white/[0.03] px-6 py-10">
                <span className="flex h-11 w-11 items-center justify-center rounded-full bg-[#EDEFF2] text-[#17191C]">
                  <Check className="h-5 w-5" strokeWidth={2.5} />
                </span>
                <p className="mt-4 text-sm font-bold uppercase tracking-[0.12em] text-[#EDEFF2]">Message enregistré</p>
                <p className="mt-2 text-[11px] leading-[1.7] text-white/55">{sent}</p>
                <button
                  type="button"
                  onClick={() => setSent('')}
                  className="mt-6 border border-white px-6 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-white transition-colors hover:bg-white hover:text-[#141516]"
                >
                  Écrire un autre message
                </button>
              </div>
            ) : (
              <form className="mt-5 flex flex-col gap-4" onSubmit={(event) => void envoyer(event)}>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <label className="flex flex-col gap-2">
                    <span className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/60">Nom complet</span>
                    <input required type="text" name="name" autoComplete="name" placeholder="Votre nom" className={champ(errors.name)} />
                    <Erreur message={errors.name} />
                  </label>
                  <label className="flex flex-col gap-2">
                    <span className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/60">Téléphone</span>
                    <input required type="tel" name="phone" autoComplete="tel" placeholder="+229 ..." className={champ(errors.phone)} />
                    <Erreur message={errors.phone} />
                  </label>
                </div>

                <label className="flex flex-col gap-2">
                  <span className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/60">E-mail</span>
                  <input required type="email" name="email" autoComplete="email" placeholder="vous@exemple.com" className={champ(errors.email)} />
                  <Erreur message={errors.email} />
                </label>

                <label className="flex flex-col gap-2">
                  <span className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/60">Sujet</span>
                  <select required name="subject" defaultValue="" className={champ(errors.subject)}>
                    <option value="" disabled>
                      Choisir un sujet
                    </option>
                    {SUBJECTS.map((subject) => (
                      <option key={subject} value={subject} className="bg-[#17191C]">
                        {subject}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="flex flex-col gap-2">
                  <span className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/60">Message</span>
                  <textarea
                    required
                    name="message"
                    rows={5}
                    placeholder="Dites-nous en quelques mots ce qu'il vous faut."
                    className={`${champ(errors.message)} resize-none`}
                  />
                  <Erreur message={errors.message} />
                </label>

                {notice !== '' && (
                  <p className="border border-[#E2564A]/40 bg-[#E2564A]/[0.07] px-4 py-3 text-[11px] leading-[1.6] text-[#F2A79E]">
                    {notice}
                  </p>
                )}

                <button
                  type="submit"
                  disabled={sending}
                  className="mt-1 w-full bg-white px-6 py-3.5 text-[11px] font-bold uppercase tracking-[0.12em] text-[#141516] transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50 sm:w-fit sm:px-10 sm:tracking-[0.14em]"
                >
                  {sending ? 'Envoi…' : 'Envoyer le message'}
                </button>
              </form>
            )}
          </section>

          {/* ---------- CHANNELS ---------- */}
          <section>
            <h2 className="text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2]">Nos coordonnées</h2>
            <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
              {channels.map(({ icon: Icon, label, lines }) => (
                <div key={label} className="border border-white/10 bg-white/[0.02] p-5">
                  <span className="flex h-9 w-9 items-center justify-center border border-white/15 text-white/70">
                    <Icon className="h-4 w-4" strokeWidth={2} />
                  </span>
                  <p className="mt-4 text-[10px] font-bold uppercase tracking-[0.16em] text-[#EDEFF2]">{label}</p>
                  {lines.map((line) => (
                    <p key={line} className="mt-1.5 text-[11px] leading-[1.6] text-white/55">
                      {line}
                    </p>
                  ))}
                </div>
              ))}
            </div>

            <div className="mt-3 border border-white/10 bg-white/[0.02] p-5">
              <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[#EDEFF2]">Livraison & paiement</p>
              <p className="mt-2 text-[11px] leading-[1.75] text-white/55">
                Livraison à Cotonou sous 24 h, expédition dans tout le Bénin sous 72 h. Paiement par mobile money
                (MTN MoMo, Moov Money) ou en espèces à la réception.
              </p>
            </div>
          </section>
        </div>
      </div>

      <Footer />
    </div>
  );
}
