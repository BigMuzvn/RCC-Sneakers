const rgba = (hex: string, alpha: number) => {
  const value = parseInt(hex.slice(1), 16);
  return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
};

type Props = {
  image: string;
  /** Dominant tone of the pair — drives the glow. */
  primary: string;
  /** Secondary tone, used for the mid stops. */
  secondary: string;
};

/**
 * Decorative pair hanging by its laces from the top of the page, with the warm wash
 * its colorway throws onto the header — the inner pages' counterpart to the hero's per-slide glow.
 */
export default function HangingShoe({ image, primary, secondary }: Props) {
  return (
    <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-0 z-0 select-none">
      {/* broad wash lifting the whole top band */}
      <div
        className="absolute inset-x-0 top-0 h-[420px] sm:h-[560px]"
        style={{
          background: `linear-gradient(to bottom, ${rgba(primary, 0.2)} 0%, ${rgba(secondary, 0.1)} 38%, ${rgba(
            secondary,
            0.04,
          )} 62%, transparent 100%)`,
        }}
      />
      {/* focused glow, tracking wherever the shoe sits at this breakpoint */}
      <div
        className="absolute inset-x-0 top-0 h-[480px] [--glow-x:42%] sm:h-[680px] sm:[--glow-x:78%]"
        style={{
          background: `radial-gradient(ellipse 60% 92% at var(--glow-x) 2%, ${rgba(primary, 0.45)} 0%, ${rgba(
            secondary,
            0.26,
          )} 30%, ${rgba(secondary, 0.1)} 52%, transparent 74%)`,
        }}
      />

      {/* on phones the free space sits between the logo and the icon cluster, not on the right */}
      <img
        src={image}
        alt=""
        className="absolute top-0 left-[29%] w-[104px] drop-shadow-[0_28px_40px_rgba(0,0,0,0.55)] sm:left-auto sm:right-[8%] sm:w-[190px] lg:right-[11%] lg:w-[250px] xl:w-[290px]"
      />
    </div>
  );
}
