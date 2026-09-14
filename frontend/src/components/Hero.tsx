import { useEffect, useState } from 'react';
import Navbar from './Navbar';
import p6000 from '../assets/nike-p6000.png';
import shoxTl from '../assets/nike-shox-tl.png';
import airMax95 from '../assets/nike-air-max-95.png';
import airMaxPlus from '../assets/nike-air-max-plus.png';

const SLIDE_INTERVAL = 5000;

const SLIDES = [
  {
    id: 'shox-tl',
    brand: 'Nike',
    model: 'Shox TL',
    price: '170$',
    image: shoxTl,
    alt: 'Nike Shox TL coloris noir et racer blue',
    description:
      "Réplique quasi exacte de la Shox TL de 2003 : tige en mesh respirant, cage TPU intégrale et empiècements moulés. Les colonnes Shox sur toute la longueur absorbent l'impact et relancent la foulée.",
    gradient:
      'radial-gradient(ellipse 78% 72% at 50% 46%, #23409B 0%, #16255C 34%, #0B1026 70%, #05070E 100%)',
    colorways: [
      {
        id: 'black-racer-blue',
        label: 'Noir / Racer Blue',
        swatch: 'linear-gradient(135deg, #1B1C1F 0%, #1B1C1F 48%, #2F5BD8 52%, #1E3FA8 100%)',
      },
      {
        id: 'metallic-silver',
        label: 'Argent métallisé',
        swatch: 'linear-gradient(135deg, #EDEEF0 0%, #A5A9AF 40%, #DCDEE1 62%, #74797F 100%)',
      },
      {
        id: 'triple-black',
        label: 'Noir intégral',
        swatch: 'linear-gradient(135deg, #34363A 0%, #131416 55%, #0A0B0C 100%)',
      },
    ],
  },
  {
    id: 'p-6000',
    brand: 'Nike',
    model: 'P-6000',
    price: '134$',
    image: p6000,
    alt: 'Nike P-6000 coloris argent métallisé',
    description:
      "La nouvelle silhouette Nike P-6000 s'inspire de la Nike Air Pegasus 2006, un grand classique. Ce runner rétro associe une tige en mesh respirant, des empiècements en cuir synthétique et une mousse souple sous le pied.",
    gradient:
      'radial-gradient(ellipse 78% 72% at 50% 46%, #4C525A 0%, #2D3238 36%, #17191C 72%, #0A0B0C 100%)',
    colorways: [
      {
        id: 'metallic-silver',
        label: 'Argent métallisé',
        swatch: 'linear-gradient(135deg, #F1F2F4 0%, #A9ADB3 38%, #E2E4E7 62%, #7C8189 100%)',
      },
      {
        id: 'photon-dust',
        label: 'Photon Dust',
        swatch: 'linear-gradient(135deg, #DCD9D3 0%, #9B978F 55%, #6B6862 100%)',
      },
      {
        id: 'black-white',
        label: 'Noir / Blanc',
        swatch: 'linear-gradient(135deg, #F4F4F5 0%, #F4F4F5 48%, #17181A 52%, #17181A 100%)',
      },
    ],
  },
  {
    id: 'air-max-95',
    brand: 'Nike',
    model: 'Air Max 95',
    price: '190$',
    image: airMax95,
    alt: 'Nike Air Max 95 coloris Neon',
    description:
      "Dessinée par Sergio Lozano d'après l'anatomie humaine : panneaux superposés comme des fibres musculaires, œillets en forme de côtes et dégradé qui masque l'usure. La bulle Max Air visible et la semelle waffle complètent l'icône de 1995.",
    gradient:
      'radial-gradient(ellipse 78% 72% at 50% 46%, #5F6E22 0%, #343A1A 34%, #16180F 70%, #08090A 100%)',
    colorways: [
      {
        id: 'neon',
        label: 'Neon',
        swatch: 'linear-gradient(135deg, #E4E6E3 0%, #9A9D9B 38%, #3C3F3E 62%, #D6F534 100%)',
      },
      {
        id: 'solar-red',
        label: 'Solar Red',
        swatch: 'linear-gradient(135deg, #E6E6E4 0%, #8C8D8B 40%, #2E2F2E 62%, #E3342B 100%)',
      },
      {
        id: 'triple-white',
        label: 'Blanc intégral',
        swatch: 'linear-gradient(135deg, #FFFFFF 0%, #EDEDEB 45%, #C9C9C6 100%)',
      },
    ],
  },
  {
    id: 'air-max-plus',
    brand: 'Nike',
    model: 'Air Max Plus',
    price: '180$',
    image: airMaxPlus,
    alt: 'Nike Air Max Plus TN coloris Sunset',
    description:
      "Croquée sur une plage de Floride par Sean McDowell en 1998 : le dégradé reprend le ciel au coucher du soleil, les nervures TPU noires dessinent des palmiers et le châssis évoque une queue de baleine. Dessous, le Tuned Air calibre l'amorti zone par zone.",
    gradient:
      'radial-gradient(ellipse 78% 72% at 50% 46%, #B0550A 0%, #5E2A06 34%, #1F1006 70%, #0A0705 100%)',
    colorways: [
      {
        id: 'sunset',
        label: 'Sunset',
        swatch: 'linear-gradient(135deg, #FFD21E 0%, #FF8A00 45%, #E8420E 72%, #17181A 100%)',
      },
      {
        id: 'hyper-blue',
        label: 'Hyper Blue',
        swatch: 'linear-gradient(135deg, #7FE3F0 0%, #1F8FD6 45%, #133A86 72%, #17181A 100%)',
      },
      {
        id: 'triple-black',
        label: 'Noir intégral',
        swatch: 'linear-gradient(135deg, #34363A 0%, #131416 55%, #0A0B0C 100%)',
      },
    ],
  },
];

export default function Hero() {
  const [active, setActive] = useState(0);
  const slide = SLIDES[active];

  // restarts on every change, so a manual pick gets a full interval before auto-advancing
  useEffect(() => {
    const id = setInterval(() => setActive((current) => (current + 1) % SLIDES.length), SLIDE_INTERVAL);
    return () => clearInterval(id);
  }, [active]);

  return (
    <section className="relative isolate flex h-[100svh] w-full flex-col overflow-hidden bg-[#05070E] px-4 py-4 sm:px-8 sm:py-6 lg:px-14 lg:py-8">
      {/* per-slide backgrounds, crossfaded */}
      {SLIDES.map((item, index) => (
        <div
          key={item.id}
          aria-hidden="true"
          className={`absolute inset-0 -z-10 transition-opacity duration-700 ease-out motion-reduce:transition-none ${
            index === active ? 'opacity-100' : 'opacity-0'
          }`}
          style={{ background: item.gradient }}
        />
      ))}

      {/* decorative hairlines */}
      <span className="pointer-events-none absolute -top-16 right-[26%] h-[300px] w-px rotate-[22deg] bg-gradient-to-b from-white/20 to-transparent sm:h-[440px]" />
      <span className="pointer-events-none absolute -right-20 -top-24 h-[220px] w-[220px] rounded-full border border-white/10 sm:-right-28 sm:-top-32 sm:h-[380px] sm:w-[380px]" />

      <Navbar />

      {/* ---------- STAGE ---------- */}
      <div className="relative mt-4 min-h-0 flex-1 sm:mt-6">
        <div className="pointer-events-none absolute left-1/2 top-[48%] z-0 -translate-x-1/2 -translate-y-1/2 select-none">
          <div className="relative">
            <span
              aria-hidden="true"
              className="absolute bottom-full left-0 mb-[-0.45em] font-display uppercase leading-none tracking-[0.3em] text-white/25 text-[clamp(14px,3.6vw,50px)]"
            >
              RCC
            </span>
            <h1 className="whitespace-nowrap font-display uppercase leading-none tracking-[-0.02em] text-white/[0.085] text-[clamp(54px,15.5vw,205px)]">
              Sneakers
            </h1>
          </div>
        </div>

        {SLIDES.map((item, index) => (
          <img
            key={item.id}
            src={item.image}
            alt={index === active ? item.alt : ''}
            aria-hidden={index !== active}
            className={`absolute left-1/2 top-[52%] z-10 h-[118%] w-auto max-w-full -translate-x-1/2 -translate-y-1/2 object-contain drop-shadow-[0_35px_50px_rgba(0,0,0,0.55)] transition-[opacity,transform] duration-700 ease-out motion-reduce:transition-none sm:max-w-[96%] ${
              index === active ? 'scale-100 opacity-100' : 'pointer-events-none scale-95 opacity-0'
            }`}
          />
        ))}
      </div>

      {/* ---------- SLIDE DOTS: inline row on phones, vertical rail from sm up ---------- */}
      <div
        className="relative z-20 mt-2 flex shrink-0 items-center justify-center gap-2 sm:absolute sm:right-4 sm:top-1/2 sm:mt-0 sm:-translate-y-1/2 sm:flex-col sm:gap-3 lg:right-7"
        role="tablist"
        aria-label="Sélection du modèle"
      >
        {SLIDES.map((item, index) => (
          <button
            key={item.id}
            type="button"
            role="tab"
            aria-selected={index === active}
            aria-label={`${item.brand} ${item.model}`}
            title={`${item.brand} ${item.model}`}
            onClick={() => setActive(index)}
            className="flex h-6 w-6 items-center justify-center sm:h-5 sm:w-5"
          >
            <span
              className={`rounded-full transition-all duration-300 ease-out motion-reduce:transition-none ${
                index === active ? 'h-[9px] w-[9px] bg-[#EDEFF2]' : 'h-[7px] w-[7px] bg-white/25 hover:bg-white/60'
              }`}
            />
          </button>
        ))}
      </div>

      {/* ---------- BOTTOM BAR ---------- */}
      <div className="relative z-10 mt-2 grid shrink-0 grid-cols-1 items-end gap-4 sm:mt-0 sm:grid-cols-2 sm:gap-5 lg:grid-cols-[auto_1fr_auto] lg:gap-10">
        <div key={`${slide.id}-colors`} className="order-2 animate-slide-in motion-reduce:animate-none lg:order-1">
          <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-white sm:text-[11px]">
            Choisir la couleur :
          </p>
          <div className="mt-2.5 flex items-center gap-3 sm:mt-3 sm:gap-4">
            {slide.colorways.map((color, index) => (
              <button
                key={color.id}
                type="button"
                aria-label={color.label}
                title={color.label}
                className={`h-11 w-11 rounded-full border transition-transform hover:scale-105 sm:h-[46px] sm:w-[46px] ${
                  index === 0 ? 'border-[#EDEFF2]' : 'border-white/20'
                }`}
                style={{ background: color.swatch }}
              />
            ))}
          </div>
        </div>

        <div className="order-3 flex items-center gap-3 sm:gap-4 lg:order-2 lg:justify-center">
          <button
            type="button"
            className="flex-1 bg-white px-3 py-3.5 text-[10px] font-bold uppercase tracking-[0.08em] text-[#141516] transition-opacity hover:opacity-90 sm:flex-none sm:px-9 sm:py-3 sm:text-[11px] sm:tracking-[0.14em]"
          >
            Ajouter au panier
          </button>
          <button
            type="button"
            className="flex-1 border border-white px-3 py-3.5 text-[10px] font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-white hover:text-[#141516] sm:flex-none sm:px-9 sm:py-3 sm:text-[11px] sm:tracking-[0.14em]"
          >
            Acheter
          </button>
        </div>

        <div
          key={`${slide.id}-details`}
          className="order-1 w-full animate-slide-in motion-reduce:animate-none sm:col-span-2 lg:order-3 lg:col-span-1 lg:max-w-[330px] lg:justify-self-end"
        >
          <div className="flex flex-col items-start gap-1.5">
            <span className="rounded-full bg-[#C5C9CE] px-3 py-[3px] text-[10px] lowercase tracking-wide text-[#22252A] sm:self-end">
              exclusif
            </span>
            <div className="flex items-baseline gap-3">
              <span className="font-display text-[32px] text-[#EDEFF2] sm:text-[38px]">{slide.price}</span>
              <span className="text-[13px] font-bold uppercase leading-[1.3] tracking-[0.12em] text-[#EDEFF2] sm:text-sm">
                {slide.brand}
                <br />
                {slide.model}
              </span>
            </div>
          </div>

          <h2 className="mt-3 text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2] sm:mt-5">Inspiration</h2>
          <p className="mt-1.5 text-[11px] leading-[1.6] text-white/60 sm:mt-2 sm:text-xs sm:leading-[1.7]">
            {slide.description}
          </p>
        </div>
      </div>

    </section>
  );
}
