import React, { useState } from 'react';
import { useCart } from '../../context/CartContext';
import { formatPrice, getPaymentMethodDetails } from '../../utils/format';
import { submitOrder } from '../../services/api';
import type { OrderResponse } from '../../types';
import {
  X,
  CreditCard,
  Phone,
  User,
  MapPin,
  Mail,
  CheckCircle,
  Loader2,
  Lock,
  MessageCircle,
} from 'lucide-react';

interface CheckoutModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export default function CheckoutModal({ isOpen, onClose }: CheckoutModalProps) {
  const { cart, subtotal, clearCart } = useCart();

  const [step, setStep] = useState<'DETAILS' | 'PAYMENT' | 'SUCCESS'>('DETAILS');
  const [loading, setLoading] = useState(false);

  // Form State
  const [customerName, setCustomerName] = useState('Kouassi Jean-Marc');
  const [customerEmail, setCustomerEmail] = useState('jean.marc@gmail.com');
  const [customerPhone, setCustomerPhone] = useState('+225 07 08 09 10 11');
  const [deliveryAddress, setDeliveryAddress] = useState('Abidjan, Cocody Deux-Plateaux Vallon');
  const [paymentMethod, setPaymentMethod] = useState('WAVE');

  // Simulated Mobile Money Phone Number
  const [paymentPhone, setPaymentPhone] = useState('+225 07 08 09 10 11');
  const [orderResult, setOrderResult] = useState<OrderResponse | null>(null);

  if (!isOpen) return null;

  const handleNextToPayment = (e: React.FormEvent) => {
    e.preventDefault();
    if (!customerName || !customerPhone || !deliveryAddress) {
      alert('Veuillez remplir vos coordonnées de livraison.');
      return;
    }
    setStep('PAYMENT');
  };

  const handleSimulatePaymentAndSubmit = async () => {
    setLoading(true);
    try {
      const response = await submitOrder({
        customerName,
        customerEmail,
        customerPhone,
        deliveryAddress,
        paymentMethod,
        items: cart,
      });

      if (response.success) {
        setOrderResult(response);
        clearCart();
        setStep('SUCCESS');
      }
    } catch (err) {
      console.error(err);
      alert('Erreur lors du traitement du paiement.');
    } finally {
      setLoading(false);
    }
  };

  const paymentMethodsList = [
    { id: 'WAVE', name: 'Wave Mobile Money', icon: '🌊', color: 'border-cyan-500 bg-cyan-950/20' },
    { id: 'ORANGE_MONEY', name: 'Orange Money', icon: '🍊', color: 'border-orange-500 bg-orange-950/20' },
    { id: 'MTN_MOMO', name: 'MTN Mobile Money', icon: '💛', color: 'border-yellow-500 bg-yellow-950/20' },
    { id: 'MOOV_MONEY', name: 'Moov Money', icon: '💙', color: 'border-emerald-500 bg-emerald-950/20' },
    { id: 'CARD', name: 'Carte Visa / Mastercard', icon: '💳', color: 'border-[#D4AF37] bg-[#D4AF37]/10' },
    { id: 'COD', name: 'Paiement à la Livraison', icon: '💵', color: 'border-white/20 bg-white/5' },
  ];

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
      <div className="relative w-full max-w-2xl bg-[#121216] border border-[#262630] rounded-3xl p-6 sm:p-8 text-white shadow-2xl overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-[#262630] pb-4 mb-6">
          <div>
            <span className="text-[10px] font-black text-[#D4AF37] uppercase tracking-widest">
              RCC SNEAKERS • CHECKOUT
            </span>
            <h2 className="text-xl sm:text-2xl font-black uppercase text-white">
              {step === 'DETAILS' && '1. Coordonnées de Livraison'}
              {step === 'PAYMENT' && '2. Paiement Sécurisé'}
              {step === 'SUCCESS' && '3. Confirmation de Commande'}
            </h2>
          </div>

          <button
            onClick={onClose}
            className="p-2 rounded-full text-[#8E8E9F] hover:text-white hover:bg-[#181820]"
          >
            <X className="w-6 h-6" />
          </button>
        </div>

        {/* STEP 1: CUSTOMER DETAILS */}
        {step === 'DETAILS' && (
          <form onSubmit={handleNextToPayment} className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-[#8E8E9F] mb-1">
                Nom complet *
              </label>
              <div className="relative">
                <User className="absolute left-3.5 top-3 w-4 h-4 text-[#8E8E9F]" />
                <input
                  type="text"
                  required
                  value={customerName}
                  onChange={(e) => setCustomerName(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 rounded-xl bg-[#0A0A0C] border border-[#262630] text-sm text-white focus:border-[#EDE8DB] outline-none"
                  placeholder="Jean-Marc Kouassi"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-bold text-[#8E8E9F] mb-1">
                  Téléphone (Mobile Money) *
                </label>
                <div className="relative">
                  <Phone className="absolute left-3.5 top-3 w-4 h-4 text-[#8E8E9F]" />
                  <input
                    type="tel"
                    required
                    value={customerPhone}
                    onChange={(e) => setCustomerPhone(e.target.value)}
                    className="w-full pl-10 pr-4 py-2.5 rounded-xl bg-[#0A0A0C] border border-[#262630] text-sm text-white focus:border-[#EDE8DB] outline-none"
                    placeholder="+225 07 00 00 00 00"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-[#8E8E9F] mb-1">
                  Adresse Email (Reçu)
                </label>
                <div className="relative">
                  <Mail className="absolute left-3.5 top-3 w-4 h-4 text-[#8E8E9F]" />
                  <input
                    type="email"
                    value={customerEmail}
                    onChange={(e) => setCustomerEmail(e.target.value)}
                    className="w-full pl-10 pr-4 py-2.5 rounded-xl bg-[#0A0A0C] border border-[#262630] text-sm text-white focus:border-[#EDE8DB] outline-none"
                    placeholder="client@gmail.com"
                  />
                </div>
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold text-[#8E8E9F] mb-1">
                Adresse précise de livraison *
              </label>
              <div className="relative">
                <MapPin className="absolute left-3.5 top-3 w-4 h-4 text-[#8E8E9F]" />
                <textarea
                  required
                  rows={2}
                  value={deliveryAddress}
                  onChange={(e) => setDeliveryAddress(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 rounded-xl bg-[#0A0A0C] border border-[#262630] text-sm text-white focus:border-[#EDE8DB] outline-none"
                  placeholder="Quartier, Rue, Porte ou Repère"
                />
              </div>
            </div>

            {/* Order Summary Box */}
            <div className="p-4 rounded-2xl bg-[#181820] border border-[#262630] flex items-center justify-between text-sm">
              <span className="text-[#8E8E9F] font-medium">Total de la commande :</span>
              <span className="text-lg font-black text-[#EDE8DB]">{formatPrice(subtotal)}</span>
            </div>

            <button
              type="submit"
              className="w-full py-3.5 rounded-xl bg-[#EDE8DB] text-[#0A0A0C] font-extrabold text-xs uppercase tracking-wider hover:bg-white transition-all shadow-xl"
            >
              Continuer vers le paiement
            </button>
          </form>
        )}

        {/* STEP 2: PAYMENT METHOD SELECTION & SIMULATION */}
        {step === 'PAYMENT' && (
          <div className="space-y-5">
            <div>
              <label className="block text-xs font-bold text-[#8E8E9F] mb-2">
                Sélectionnez votre moyen de paiement :
              </label>
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                {paymentMethodsList.map((m) => (
                  <button
                    key={m.id}
                    type="button"
                    onClick={() => setPaymentMethod(m.id)}
                    className={`p-3 rounded-2xl border flex flex-col items-center text-center transition-all ${
                      paymentMethod === m.id
                        ? `${m.color} border-2 shadow-lg scale-102`
                        : 'border-[#262630] bg-[#0A0A0C] hover:bg-[#181820]'
                    }`}
                  >
                    <span className="text-2xl mb-1">{m.icon}</span>
                    <span className="text-xs font-bold text-white leading-tight">
                      {m.name}
                    </span>
                  </button>
                ))}
              </div>
            </div>

            {/* Simulated Payment Prompt */}
            {paymentMethod !== 'COD' && (
              <div className="p-4 rounded-2xl bg-[#181820] border border-[#262630] space-y-2">
                <div className="flex items-center gap-2 text-xs font-bold text-[#D4AF37]">
                  <Lock className="w-4 h-4" />
                  <span>Validation du paiement Mobile Money</span>
                </div>
                <p className="text-xs text-[#8E8E9F]">
                  Entrez le numéro sur lequel la demande de confirmation sera envoyée :
                </p>
                <input
                  type="tel"
                  value={paymentPhone}
                  onChange={(e) => setPaymentPhone(e.target.value)}
                  className="w-full px-4 py-2 rounded-xl bg-[#0A0A0C] border border-[#262630] text-sm font-bold text-white outline-none"
                />
              </div>
            )}

            <div className="flex items-center justify-between text-sm pt-2">
              <button
                type="button"
                onClick={() => setStep('DETAILS')}
                className="text-xs font-bold text-[#8E8E9F] hover:text-white"
              >
                ← Retour aux coordonnées
              </button>

              <div className="text-right">
                <div className="text-xs text-[#8E8E9F]">Montant à débiter</div>
                <div className="text-lg font-black text-white">{formatPrice(subtotal)}</div>
              </div>
            </div>

            <button
              type="button"
              disabled={loading}
              onClick={handleSimulatePaymentAndSubmit}
              className="w-full py-4 rounded-xl bg-emerald-500 text-white font-extrabold text-xs uppercase tracking-wider flex items-center justify-center gap-2 hover:bg-emerald-400 transition-all shadow-xl active:scale-98 disabled:opacity-50"
            >
              {loading ? (
                <>
                  <Loader2 className="w-5 h-5 animate-spin" />
                  <span>Traitement sécurisé en cours...</span>
                </>
              ) : (
                <>
                  <CreditCard className="w-5 h-5" />
                  <span>
                    {paymentMethod === 'COD'
                      ? 'Confirmer la commande (Paiement à la livraison)'
                      : `Payer ${formatPrice(subtotal)} maintenant`}
                  </span>
                </>
              )}
            </button>
          </div>
        )}

        {/* STEP 3: SUCCESS CONFIRMATION RECEIPT */}
        {step === 'SUCCESS' && orderResult && (
          <div className="text-center py-4 space-y-5">
            <div className="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 flex items-center justify-center mx-auto">
              <CheckCircle className="w-10 h-10" />
            </div>

            <div>
              <span className="inline-block px-3 py-1 rounded-full text-[10px] font-extrabold uppercase bg-emerald-950 text-emerald-300 border border-emerald-800 mb-2">
                PAIEMENT CONFIRMÉ • RCC SNEAKERS
              </span>
              <h3 className="text-2xl font-black uppercase text-white">
                Merci pour ta commande !
              </h3>
              <p className="text-xs text-[#8E8E9F] mt-1">
                Un SMS et un e-mail de confirmation t'ont été envoyés.
              </p>
            </div>

            {/* Receipt Details Box */}
            <div className="p-5 rounded-2xl bg-[#0A0A0C] border border-[#262630] text-left space-y-3">
              <div className="flex justify-between items-center text-xs border-b border-[#262630] pb-2">
                <span className="text-[#8E8E9F] font-medium">N° de commande :</span>
                <span className="font-mono font-extrabold text-[#D4AF37]">
                  {orderResult.orderNumber}
                </span>
              </div>

              <div className="flex justify-between items-center text-xs border-b border-[#262630] pb-2">
                <span className="text-[#8E8E9F] font-medium">Client :</span>
                <span className="font-bold text-white">{customerName}</span>
              </div>

              <div className="flex justify-between items-center text-xs border-b border-[#262630] pb-2">
                <span className="text-[#8E8E9F] font-medium">Mode de paiement :</span>
                <span className="font-bold text-white">
                  {getPaymentMethodDetails(orderResult.paymentMethod).label}
                </span>
              </div>

              <div className="flex justify-between items-center text-sm pt-1">
                <span className="text-[#8E8E9F] font-bold">Total payé :</span>
                <span className="text-lg font-black text-emerald-400">
                  {formatPrice(orderResult.totalAmount)}
                </span>
              </div>
            </div>

            <div className="flex flex-col sm:flex-row gap-3 pt-2">
              <a
                href={`https://wa.me/2250700000000?text=Bonjour%20RCC%20Sneakers,%20je%20suis%20${encodeURIComponent(
                  customerName
                )},%20je%20suis%20la%20commande%20${orderResult.orderNumber}`}
                target="_blank"
                rel="noreferrer"
                className="flex-1 py-3 rounded-xl bg-emerald-600 text-white font-extrabold text-xs uppercase flex items-center justify-center gap-2 hover:bg-emerald-500 transition-all shadow-lg"
              >
                <MessageCircle className="w-4 h-4" />
                <span>Suivre sur WhatsApp</span>
              </a>

              <button
                type="button"
                onClick={onClose}
                className="flex-1 py-3 rounded-xl bg-[#EDE8DB] text-[#0A0A0C] font-extrabold text-xs uppercase hover:bg-white transition-all shadow-lg"
              >
                Fermer
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
