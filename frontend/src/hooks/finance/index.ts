'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { financeApi } from '@/lib/api/finance';
import type { FeeStructure, InvoiceStatus, PaymentMethod } from '@/types/finance';

export function useFees(params: { academic_year_id?: string; category?: string } = {}, enabled = true) {
  return useQuery({ queryKey: ['finance', 'fees', params], queryFn: () => financeApi.listFees(params), enabled });
}

export function useFinanceSummary() {
  return useQuery({
    queryKey: ['finance', 'summary'],
    queryFn: () => financeApi.summary(),
    staleTime: 30_000,
  });
}

export function useCreateFee() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<FeeStructure>) => financeApi.createFee(payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['finance', 'fees'] }); },
  });
}

export function useUpdateFee(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<FeeStructure>) => financeApi.updateFee(id, payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['finance', 'fees'] }); },
  });
}

export function useDeleteFee() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => financeApi.deleteFee(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['finance', 'fees'] }); },
  });
}

export function useInvoices(filters: { q?: string; status?: InvoiceStatus; student_id?: string; page?: number; per_page?: number } = {}) {
  return useQuery({
    queryKey: ['finance', 'invoices', filters],
    queryFn: () => financeApi.listInvoices(filters),
    placeholderData: (p) => p,
  });
}

export function useInvoice(id: string | undefined) {
  return useQuery({
    queryKey: ['finance', 'invoices', 'detail', id],
    queryFn: () => financeApi.getInvoice(id as string),
    enabled: Boolean(id),
  });
}

export function useGenerateInvoice() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { student_id: string; academic_year_id: string; due_at?: string }) => financeApi.generateInvoice(payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['finance', 'invoices'] }); },
  });
}

export function useCancelInvoice() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => financeApi.cancelInvoice(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['finance'] }); },
  });
}

export function useRecordPayment(invoiceId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { paid_at: string; amount: number; method: PaymentMethod; reference?: string; notes?: string }) =>
      financeApi.recordPayment(invoiceId, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['finance', 'invoices'] });
      void qc.invalidateQueries({ queryKey: ['finance', 'summary'] });
      void qc.invalidateQueries({ queryKey: ['finance', 'balance'] });
      void qc.invalidateQueries({ queryKey: ['dashboard'] });
      void qc.invalidateQueries({ queryKey: ['notifications'] });
    },
  });
}

export function useStudentBalance(studentId: string | undefined) {
  return useQuery({
    queryKey: ['finance', 'balance', studentId],
    queryFn: () => financeApi.studentBalance(studentId as string),
    enabled: Boolean(studentId),
  });
}
