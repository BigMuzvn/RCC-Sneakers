import type { ReactNode } from 'react';

/**
 * Briques communes aux écrans d'administration.
 *
 * Angles vifs, fond sombre, capitales espacées : les mêmes conventions que le
 * site public, pour que l'administration n'ait pas l'air d'un autre produit.
 */

export const inputClass =
  'w-full border border-white/15 bg-white/[0.03] px-3.5 py-2.5 text-[12px] text-white placeholder:text-white/30 transition-colors focus:border-white/50 focus:outline-none';

export const labelClass = 'text-[10px] font-bold uppercase tracking-[0.16em] text-white/55';

export const cardClass = 'border border-white/10 bg-white/[0.03]';

export function PageTitle({ title, lead, action }: { title: string; lead?: string; action?: ReactNode }) {
  return (
    <header className="mb-7 flex flex-wrap items-end justify-between gap-4">
      <div className="min-w-0">
        <h1 className="font-display text-[clamp(22px,3.4vw,32px)] uppercase leading-none tracking-[-0.01em] text-[#EDEFF2]">
          {title}
        </h1>
        {lead && <p className="mt-2 max-w-xl text-[11px] leading-[1.7] text-white/45">{lead}</p>}
      </div>
      {action}
    </header>
  );
}

export function Field({
  label,
  error,
  hint,
  children,
  className = '',
}: {
  label: string;
  error?: string;
  hint?: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <label className={`flex flex-col gap-1.5 ${className}`}>
      <span className={labelClass}>{label}</span>
      {children}
      {error && <span className="text-[10px] leading-[1.5] text-[#E2564A]">{error}</span>}
      {!error && hint && <span className="text-[10px] leading-[1.5] text-white/35">{hint}</span>}
    </label>
  );
}

export function TextField({
  label,
  name,
  value,
  onChange,
  error,
  hint,
  type = 'text',
  placeholder,
  className = '',
}: {
  label: string;
  name: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  hint?: string;
  type?: string;
  placeholder?: string;
  className?: string;
}) {
  return (
    <Field label={label} error={error} hint={hint} className={className}>
      <input
        name={name}
        type={type}
        value={value}
        placeholder={placeholder}
        onChange={(event) => onChange(event.target.value)}
        aria-invalid={error ? true : undefined}
        className={`${inputClass} ${error ? 'border-[#E2564A]/70' : ''}`}
      />
    </Field>
  );
}

export function SelectField({
  label,
  value,
  onChange,
  options,
  error,
  className = '',
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: readonly { value: string; label: string }[];
  error?: string;
  className?: string;
}) {
  return (
    <Field label={label} error={error} className={className}>
      <select
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className={`${inputClass} ${error ? 'border-[#E2564A]/70' : ''}`}
      >
        {options.map((option) => (
          <option key={option.value} value={option.value} className="bg-[#17191C]">
            {option.label}
          </option>
        ))}
      </select>
    </Field>
  );
}

const TONES = {
  neutral: 'border-white/20 bg-white/[0.06] text-white/70',
  amber: 'border-[#E2B04A]/40 bg-[#E2B04A]/10 text-[#E2B04A]',
  blue: 'border-[#6BA8E2]/40 bg-[#6BA8E2]/10 text-[#9CC6EE]',
  green: 'border-[#4A9E6B]/45 bg-[#4A9E6B]/12 text-[#7FCB9B]',
  red: 'border-[#E2564A]/40 bg-[#E2564A]/10 text-[#F2A79E]',
} as const;

export type Tone = keyof typeof TONES;

export function Badge({ children, tone = 'neutral' }: { children: ReactNode; tone?: Tone }) {
  return (
    <span
      className={`inline-flex shrink-0 items-center border px-2.5 py-1 text-[9px] font-bold uppercase tracking-[0.12em] ${TONES[tone]}`}
    >
      {children}
    </span>
  );
}

export function Button({
  children,
  onClick,
  type = 'button',
  variant = 'primary',
  disabled,
  className = '',
}: {
  children: ReactNode;
  onClick?: () => void;
  type?: 'button' | 'submit';
  variant?: 'primary' | 'ghost' | 'danger';
  disabled?: boolean;
  className?: string;
}) {
  const styles = {
    primary: 'bg-[#EDEFF2] text-[#17191C] hover:opacity-90',
    ghost: 'border border-white/20 text-white hover:border-white',
    danger: 'border border-[#E2564A]/50 text-[#F2A79E] hover:bg-[#E2564A] hover:text-white',
  }[variant];

  return (
    <button
      type={type}
      onClick={onClick}
      disabled={disabled}
      className={`px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.12em] transition-colors disabled:cursor-not-allowed disabled:opacity-40 ${styles} ${className}`}
    >
      {children}
    </button>
  );
}

/**
 * État vide.
 *
 * Toujours explicite sur la raison : « aucune commande » et « la recherche ne
 * donne rien » sont deux situations différentes, qu'un même écran gris
 * confondrait.
 */
export function EmptyState({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <div className={`${cardClass} px-6 py-10 text-center`}>
      <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-[#EDEFF2]">{title}</p>
      {children && <div className="mx-auto mt-2 max-w-md text-[11px] leading-[1.7] text-white/45">{children}</div>}
    </div>
  );
}

export function Loading() {
  return <p className="py-10 text-center text-[11px] uppercase tracking-[0.18em] text-white/35">Chargement…</p>;
}

/** Bandeau d'erreur générale, pour ce qui ne vise aucun champ. */
export function ErrorBanner({ message }: { message: string }) {
  if (!message) return null;

  return (
    <p className="mb-4 border border-[#E2564A]/40 bg-[#E2564A]/[0.08] px-4 py-3 text-[11px] leading-[1.6] text-[#F2A79E]">
      {message}
    </p>
  );
}

/** Confirmation de succès, effacée d'elle-même par l'action suivante. */
export function SuccessBanner({ message }: { message: string }) {
  if (!message) return null;

  return (
    <p className="mb-4 border border-[#4A9E6B]/40 bg-[#4A9E6B]/[0.08] px-4 py-3 text-[11px] leading-[1.6] text-[#7FCB9B]">
      {message}
    </p>
  );
}

/** Les dates arrivent en UTC ; Cotonou est à UTC+1. */
export const formatDate = (utc: string, withTime = false) =>
  new Date(utc.replace(' ', 'T') + 'Z').toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  });
