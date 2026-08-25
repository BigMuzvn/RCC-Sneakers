import React from 'react';

interface LogoProps {
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

export default function Logo({ size = 'md', className = '' }: LogoProps) {
  const sizeMap = {
    sm: { icon: 32, text: 'text-base', sub: 'text-[9px]' },
    md: { icon: 40, text: 'text-xl', sub: 'text-[10px]' },
    lg: { icon: 52, text: 'text-2xl', sub: 'text-xs' },
  };

  const { icon, text, sub } = sizeMap[size];

  return (
    <div className={`inline-flex items-center gap-3 select-none cursor-pointer group ${className}`}>
      {/* RCC Emblem SVG */}
      <div
        style={{ width: icon, height: icon }}
        className="relative rounded-full bg-[#0A0A0C] border border-[#262630] flex items-center justify-center p-1.5 shadow-lg group-hover:border-[#EDE8DB]/50 transition-all duration-300"
      >
        <svg viewBox="0 0 500 500" className="w-full h-full">
          <circle cx="250" cy="250" r="235" fill="#0A0A0C" />
          <path
            d="M 100 135 
               C 145 135, 335 135, 360 135 
               C 435 135, 450 245, 375 275 
               C 340 290, 290 295, 250 295 
               L 395 440 
               L 310 440 
               L 190 300 
               C 150 300, 130 330, 130 375 
               L 130 440 
               L 85 440 
               L 85 180 
               C 85 145, 105 135, 100 135 Z
               M 140 185
               L 140 250
               L 260 250
               C 310 250, 370 240, 365 195
               C 360 160, 305 185, 250 185
               Z"
            fill="#EDE8DB"
            className="group-hover:fill-white transition-colors duration-300"
          />
        </svg>
      </div>

      {/* Brand Name Typography */}
      <div className="flex flex-col">
        <span className={`font-extrabold tracking-wider text-white uppercase group-hover:text-[#EDE8DB] transition-colors leading-tight ${text}`}>
          RCC<span className="text-[#EDE8DB] font-light ml-1">SNEAKERS</span>
        </span>
        <span className={`tracking-[0.25em] text-[#8E8E9F] uppercase font-medium leading-none hidden sm:block ${sub}`}>
          SNEAKERS • STYLE • YOU
        </span>
      </div>
    </div>
  );
}
