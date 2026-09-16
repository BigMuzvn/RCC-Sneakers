const rgba = (hex: string, alpha: number) => {
  const value = parseInt(hex.slice(1), 16);
  return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
};

type Props = {
  image: string;
  /** Ton dominant de la paire — porte le halo. */
  primary: string;
  /** Ton secondaire, pour les arrêts intermédiaires. */
  secondary: string;
};

/**
 * Paire entrant par le bord droit de l'écran, et le halo que son coloris jette
 * sur la page.
 *
 * Variante latérale de la paire suspendue des autres pages internes. Le halo
 * est ici un dégradé radial ancré au bord droit plutôt qu'en haut : il suit
 * l'axe d'entrée du visuel, sinon la lumière viendrait d'un endroit où il n'y
 * a rien.
 *
 * Sur téléphone la paire est volontairement rognée par le bord — la garder
 * entière la réduirait à une vignette illisible, alors que ce cadrage serré
 * lit comme un parti pris.
 */
export default function SideShoe({ image, primary, secondary }: Props) {
  return (
    <div aria-hidden="true" className="pointer-events-none absolute inset-0 z-0 select-none overflow-hidden">
      {/* lavis large, du bord droit vers le centre */}
      <div
        className="absolute inset-y-0 right-0 w-full sm:w-[72%]"
        style={{
          background: `linear-gradient(to left, ${rgba(primary, 0.22)} 0%, ${rgba(secondary, 0.12)} 34%, ${rgba(
            secondary,
            0.04,
          )} 62%, transparent 100%)`,
        }}
      />
      {/* halo concentré, calé sur la paire elle-même */}
      <div
        className="absolute inset-x-0 top-0 h-[560px] [--glow-y:20%] sm:h-[760px] sm:[--glow-y:34%]"
        style={{
          background: `radial-gradient(ellipse 58% 70% at 92% var(--glow-y), ${rgba(primary, 0.42)} 0%, ${rgba(
            secondary,
            0.24,
          )} 32%, ${rgba(secondary, 0.09)} 54%, transparent 76%)`,
        }}
      />

      {/* Masquée sur téléphone, où le halo suffit.
          Une paire entrant par la droite a besoin d'une gouttière pour ne pas
          couvrir le texte ; sur 390 px de large, cette gouttière prendrait la
          moitié de l'écran. Mesuré : à 390 px elle passait en travers du
          bandeau de vérification, qui devenait illisible. Le coloris continue
          d'éclairer la page — c'est lui le dispositif, pas la photo. */}
      <img
        src={image}
        alt=""
        className="absolute right-[-7%] top-[4%] hidden w-[260px] rotate-[-4deg] drop-shadow-[0_30px_50px_rgba(0,0,0,0.6)] sm:block lg:w-[330px] xl:w-[380px]"
      />
    </div>
  );
}
