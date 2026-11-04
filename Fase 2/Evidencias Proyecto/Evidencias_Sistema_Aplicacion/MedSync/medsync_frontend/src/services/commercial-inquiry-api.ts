import { apiRequest } from '@/services/http'

export interface CommercialInquiry {
  id: number
  contact_name: string
  center_name: string
  email: string
  phone: string | null
  message: string | null
  created_at: string
  plan: { id: number; name: string; code: string } | null
}

export const commercialInquiryApi = {
  create: (input: { plan_id: number | null; contact_name: string; center_name: string; email: string; phone: string | null; message: string | null }) =>
    apiRequest<{ message: string }>('/api/public/commercial-inquiries', { method: 'POST', body: JSON.stringify(input) }),
  list: () => apiRequest<{ data: CommercialInquiry[] }>('/api/v1/platform/commercial-inquiries'),
}
