export type FeeCategory = 'tuition' | 'cafeteria' | 'uniform' | 'transport' | 'exam' | 'other';
export type FeeFrequency = 'one_time' | 'monthly' | 'termly' | 'annual';
export type InvoiceStatus = 'draft' | 'issued' | 'partially_paid' | 'paid' | 'cancelled' | 'overdue';
export type PaymentMethod = 'cash' | 'bank_transfer' | 'mtn_momo' | 'orange_money' | 'cheque' | 'other';

export interface FeeStructure {
  id: string;
  academic_year_id: string;
  class_id: string | null;
  name: string;
  category: FeeCategory;
  amount: number;
  frequency: FeeFrequency;
  is_required: boolean;
  description: string | null;
  school_class?: { id: string; name: string } | null;
}

export interface InvoiceItem {
  id: string;
  description: string;
  quantity: number;
  unit_amount: number;
  line_total: number;
}

export interface PaymentRecord {
  id: string;
  receipt_number: string;
  paid_at: string;
  amount: number;
  method: PaymentMethod;
  reference: string | null;
  voided_at?: string | null;
  void_reason?: string | null;
}

export interface FinanceSummary {
  date: string;
  collected_today: number;
  total_collected: number;
  total_outstanding: number;
  total_overdue: number;
  students_registered_today: number;
  payments_received_today: number;
  recent_payments: Array<{
    id: string;
    receipt_number: string;
    amount: number;
    paid_at: string;
    invoice_id: string;
    invoice_balance: number;
    student: { id: string; full_name: string; admission_number: string } | null;
  }>;
}

export interface Invoice {
  id: string;
  invoice_number: string;
  student_id: string;
  academic_year_id: string;
  issued_at: string;
  due_at: string;
  subtotal: number;
  discount: number;
  total: number;
  paid: number;
  balance: number;
  status: InvoiceStatus;
  notes: string | null;
  student?: { id: string; full_name: string; admission_number: string; photo_url: string | null };
  items?: InvoiceItem[];
  payments?: PaymentRecord[];
}

export const FEE_CATEGORIES: { value: FeeCategory; label: string }[] = [
  { value: 'tuition',   label: 'Tuition' },
  { value: 'cafeteria', label: 'Cafeteria' },
  { value: 'uniform',   label: 'Uniform' },
  { value: 'transport', label: 'Transport' },
  { value: 'exam',      label: 'Exam' },
  { value: 'other',     label: 'Other' },
];

export const PAYMENT_METHODS: { value: PaymentMethod; label: string }[] = [
  { value: 'cash',          label: 'Cash' },
  { value: 'mtn_momo',      label: 'MTN MoMo' },
  { value: 'orange_money',  label: 'Orange Money' },
  { value: 'bank_transfer', label: 'Bank transfer' },
  { value: 'cheque',        label: 'Cheque' },
  { value: 'other',         label: 'Other' },
];
