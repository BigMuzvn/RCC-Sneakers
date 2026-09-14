export default function Chip({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={`whitespace-nowrap rounded-full border px-4 py-2 text-[10px] font-bold uppercase tracking-[0.14em] transition-colors sm:text-[11px] ${
        active
          ? 'border-[#EDEFF2] bg-[#EDEFF2] text-[#17191C]'
          : 'border-white/15 text-white/70 hover:border-white/40 hover:text-white'
      }`}
    >
      {children}
    </button>
  );
}
