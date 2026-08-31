export function formatPrice(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
  })
    .format(amount)
    .replace('XOF', 'FCFA');
}

export function getPaymentMethodDetails(method: string) {
  switch (method.toUpperCase()) {
    case 'WAVE':
      return { label: 'Wave Mobile Money', color: '#1BA5E0', icon: '🌊' };
    case 'ORANGE_MONEY':
      return { label: 'Orange Money', color: '#FF7900', icon: '🍊' };
    case 'MTN_MOMO':
      return { label: 'MTN Mobile Money', color: '#FFCC00', icon: '💛' };
    case 'MOOV_MONEY':
      return { label: 'Moov Money', color: '#00A859', icon: '💙' };
    case 'CARD':
      return { label: 'Carte Bancaire (Visa/Mastercard)', color: '#D4AF37', icon: '💳' };
    case 'COD':
      return { label: 'Paiement à la livraison', color: '#EDE8DB', icon: '💵' };
    default:
      return { label: method, color: '#EDE8DB', icon: '📱' };
  }
}
