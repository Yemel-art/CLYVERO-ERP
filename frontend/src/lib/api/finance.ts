import { apiClient, unwrap } from './client';
import type { ApiResponse, ApiMeta } from '@/types/api';
import type { FeeStructure, FinanceSummary, Invoice, InvoiceStatus, PaymentMethod, PaymentRecord } from '@/types/finance';

interface PaginatedInvoices {
  data: Invoice[];
  meta: Required<Pick<ApiMeta, 'page' | 'per_page' | 'total' | 'last_page'>>;
}

export const financeApi = {
  async summary() {
    const { data } = await apiClient.get<ApiResponse<FinanceSummary>>('/finance/summary');
    return unwrap(data);
  },
  // Fees
  async listFees(params: { academic_year_id?: string; category?: string } = {}) {
    const { data } = await apiClient.get<ApiResponse<FeeStructure[]>>('/finance/fees', { params });
    return unwrap(data);
  },
  async createFee(payload: Partial<FeeStructure>) {
    const { data } = await apiClient.post<ApiResponse<FeeStructure>>('/finance/fees', payload);
    return unwrap(data);
  },
  async updateFee(id: string, payload: Partial<FeeStructure>) {
    const { data } = await apiClient.patch<ApiResponse<FeeStructure>>(`/finance/fees/${id}`, payload);
    return unwrap(data);
  },
  async deleteFee(id: string) {
    const { data } = await apiClient.delete<ApiResponse<null>>(`/finance/fees/${id}`);
    if (!data.success) throw new Error(data.message);
  },

  // Invoices
  async listInvoices(params: { q?: string; status?: InvoiceStatus; student_id?: string; page?: number; per_page?: number } = {}): Promise<PaginatedInvoices> {
    const { data } = await apiClient.get<ApiResponse<Invoice[]>>('/finance/invoices', { params });
    if (!data.success) throw new Error(data.message);
    return {
      data: data.data ?? [],
      meta: {
        page: Number(data.meta?.page ?? 1),
        per_page: Number(data.meta?.per_page ?? 25),
        total: Number(data.meta?.total ?? 0),
        last_page: Number(data.meta?.last_page ?? 1),
      },
    };
  },
  async getInvoice(id: string) {
    const { data } = await apiClient.get<ApiResponse<Invoice>>(`/finance/invoices/${id}`);
    return unwrap(data);
  },
  async generateInvoice(payload: { student_id: string; academic_year_id: string; due_at?: string }) {
    const { data } = await apiClient.post<ApiResponse<Invoice>>('/finance/invoices/generate', payload);
    return unwrap(data);
  },
  async cancelInvoice(id: string) {
    const { data } = await apiClient.post<ApiResponse<Invoice>>(`/finance/invoices/${id}/cancel`);
    return unwrap(data);
  },
  async recordPayment(invoiceId: string, payload: { paid_at: string; amount: number; method: PaymentMethod; reference?: string; notes?: string }) {
    const { data } = await apiClient.post<ApiResponse<PaymentRecord>>(`/finance/invoices/${invoiceId}/payments`, payload);
    return unwrap(data);
  },
  async downloadReceipt(paymentId: string, copies: 1 | 2 | 3, language: 'fr' | 'en') {
    const { data } = await apiClient.get<Blob>(`/finance/payments/${paymentId}/receipt`, {
      params: { copies, language },
      responseType: 'blob',
    });
    return data;
  },
  async studentBalance(studentId: string): Promise<{ balance: number }> {
    const { data } = await apiClient.get<ApiResponse<{ balance: number }>>(`/finance/students/${studentId}/balance`);
    return unwrap(data);
  },
};
