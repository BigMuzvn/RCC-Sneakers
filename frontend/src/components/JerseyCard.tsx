import type { Jersey } from '../data/jerseys';
import { formatXof } from '../utils/format';

export default function JerseyCard({ jersey }: { jersey: Jersey }) {
  const inStock = jersey.variants.filter((variant) => variant.stock > 0);
  const discount = jersey.old_price_xof
    ? Math.round((1 - jersey.price_xof / jersey.old_price_xof) * 100)
    : null;

  return (
    <article className="group relative flex flex-col overflow-hidden border border-white/10 bg-white/[0.02] transition-colors duration-300 hover:border-white/25 hover:bg-white/[0.04]">
      <div className="relative aspect-[4/3] overflow-hidden">
        <div
          aria-hidden="true"
          className="absolute inset-0 opacity-90 transition-opacity duration-500 group-hover:opacity-100 motion-reduce:transition-none"
          style={{
            background: `radial-gradient(ellipse 80% 70% at 50% 56%, ${jersey.accent}8C 0%, ${jersey.accent}45 38%, ${jersey.accent}1A 60%, transparent 78%)`,
          }}
        />

        {jersey.image ? (
          <img
            src={jersey.image}
            alt={`Maillot ${jersey.club} ${jersey.kit} ${jersey.season}`}
            loading="lazy"
            className="absolute inset-0 h-full w-full scale-[1.04] object-contain p-2 drop-shadow-[0_18px_24px_rgba(0,0,0,0.5)] transition-transform duration-500 ease-out group-hover:scale-[1.12] motion-reduce:transition-none"
          />
        ) : (
          <div className="absolute inset-0 flex flex-col items-center justify-center gap-3">
            <span
              aria-hidden="true"
              className="h-16 w-4/5 rounded-[50%] blur-2xl"
              style={{ background: jersey.accent, opacity: 0.55 }}
            />
            <span className="text-[9px] uppercase tracking-[0.24em] text-white/35">Visuel à venir</span>
          </div>
        )}

        <span className="absolute left-3 top-3 rounded-full border border-white/25 bg-black/35 px-2.5 py-[3px] text-[9px] font-bold uppercase tracking-[0.1em] text-white/90 backdrop-blur-sm">
          {jersey.kit}
        </span>
        {jersey.is_new_drop && (
          <span className="absolute right-3 top-3 rounded-full bg-[#EDEFF2] px-2.5 py-[3px] text-[9px] font-bold uppercase tracking-[0.1em] text-[#17191C]">
            Nouveau
          </span>
        )}
        {discount !== null && (
          <span className="absolute right-3 top-3 rounded-full bg-[#C8242F] px-2.5 py-[3px] text-[9px] font-bold uppercase tracking-[0.1em] text-white">
            -{discount}%
          </span>
        )}
      </div>

      <div className="flex flex-1 flex-col p-3 sm:p-4">
        <p className="text-[9px] uppercase tracking-[0.2em] text-white/45 sm:text-[10px]">
          {jersey.league} · {jersey.season}
        </p>
        <h3 className="mt-1 text-[12px] font-bold uppercase leading-snug tracking-[0.06em] text-[#EDEFF2] sm:text-[13px]">
          {jersey.club}
        </h3>
        <p className="mt-1 text-[10px] text-white/50 sm:text-[11px]">
          {jersey.colorway} · {jersey.brand}
        </p>

        {/* sizes double as the purchase affordance until the cart exists */}
        <div className="mt-3 flex flex-wrap gap-1.5">
          {jersey.variants.map((variant) => (
            <span
              key={variant.size}
              className={`border px-2 py-1 text-[9px] font-bold tracking-[0.06em] ${
                variant.stock > 0 ? 'border-white/20 text-white/75' : 'border-white/5 text-white/20 line-through'
              }`}
            >
              {variant.size}
            </span>
          ))}
        </div>

        <div className="mt-3 flex items-end justify-between gap-2 sm:mt-4">
          <div className="flex flex-col">
            {jersey.old_price_xof && (
              <span className="text-[10px] text-white/35 line-through sm:text-[11px]">
                {formatXof(jersey.old_price_xof)}
              </span>
            )}
            <span className="font-display text-[15px] text-[#EDEFF2] sm:text-base">{formatXof(jersey.price_xof)}</span>
          </div>
          <span className="whitespace-nowrap text-[9px] uppercase tracking-[0.12em] text-white/40 sm:text-[10px]">
            {inStock.length > 0 ? `${inStock.length} tailles` : 'épuisé'}
          </span>
        </div>
      </div>
    </article>
  );
}
