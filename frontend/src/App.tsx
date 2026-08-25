import React from 'react';
import Logo from './components/ui/Logo';

export default function App() {
  return (
    <div className="min-h-screen bg-[#0A0A0C] text-white flex flex-col items-center justify-center p-6 text-center">
      {/* Brand Logo */}
      <Logo size="lg" className="mb-6" />

      {/* Main Title */}
      <h1 className="text-3xl sm:text-5xl font-black uppercase tracking-tight text-white mb-3">
        RCC <span className="text-[#EDE8DB]">SNEAKERS</span>
      </h1>

      <p className="text-[#8E8E9F] text-sm max-w-md mb-8 font-medium">
        Architecture React + PHP REST API + MySQL initialisée avec succès !
      </p>

      {/* Status Badge */}
      <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-[#181820] border border-[#D4AF37]/40 text-[#EDE8DB] text-xs font-semibold uppercase tracking-wider">
        <span className="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse" />
        <span>Vite + React Active (HTTP 200 OK)</span>
      </div>
    </div>
  );
}
