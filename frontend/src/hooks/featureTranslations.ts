import type { Language } from '@/i18n/translations';

const french: Record<string, string> = {
  Home: 'Accueil',
  Back: 'Retour',
  Invoice: 'Facture',
  Invoices: 'Factures',
  'Record payment': 'Enregistrer un paiement',
  'Cancel invoice': 'Annuler la facture',
  Total: 'Total',
  Paid: 'Payé',
  Balance: 'Solde',
  Status: 'Statut',
  'Line items': 'Détails de la facture',
  Description: 'Description',
  Qty: 'Qté',
  Unit: 'Unité',
  Subtotal: 'Sous-total',
  Discount: 'Remise',
  Payments: 'Paiements',
  'No payments yet.': 'Aucun paiement pour le moment.',
  Receipt: 'Reçu',
  Cancel: 'Annuler',
  'Date received': 'Date de réception',
  'Amount (XAF)': 'Montant (XAF)',
  'Payment method': 'Mode de paiement',
  'Reference (transaction ID, cheque number…)': 'Référence (ID de transaction, numéro de chèque…)',
  'Download payment receipt': 'Télécharger le reçu de paiement',
  'Download PDF': 'Télécharger le PDF',
  Language: 'Langue',
  'Number of copies': 'Nombre de copies',
  Original: 'Original',
  'Original + duplicate': 'Original + duplicata',
  'Original + duplicate + triplicate': 'Original + duplicata + triplicata',
  'Receipt downloaded.': 'Reçu téléchargé.',
  'Could not download the receipt.': 'Impossible de télécharger le reçu.',
  'Could not record payment.': 'Impossible d’enregistrer le paiement.',
  'Could not cancel.': 'Impossible d’annuler la facture.',
  'Invoice cancelled.': 'Facture annulée.',
};

export function translateFeature(language: Language, key: string): string | undefined {
  return language === 'fr' ? french[key] : undefined;
}
