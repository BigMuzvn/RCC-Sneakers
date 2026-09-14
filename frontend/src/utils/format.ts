const xof = new Intl.NumberFormat('fr-FR', {
  style: 'currency',
  currency: 'XOF',
  maximumFractionDigits: 0,
});

export const formatXof = (amount: number) => xof.format(amount);
