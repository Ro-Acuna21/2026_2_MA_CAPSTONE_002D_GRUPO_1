import { apiRequest } from '@/services/http'

export interface Plan {
  id: number; name: string; code: string; description: string | null; monthly_price_clp: number
  professional_limit: number | null; user_limit: number | null; features: string[] | null; is_active: boolean
}
export interface Payment {
  id: number; amount_clp: number; status: 'PENDING' | 'PAID' | 'OVERDUE'
  due_at: string; paid_at: string | null; provider_reference: string | null; recorded_at: string
}
export interface Subscription {
  id: number; status: 'TRIAL' | 'ACTIVE' | 'EXPIRED' | 'SUSPENDED'; started_at: string; renews_at: string | null
  ended_at: string | null; notes: string | null; monthly_price_clp_snapshot: number | null
  plan: Pick<Plan, 'id' | 'code' | 'name' | 'monthly_price_clp' | 'professional_limit' | 'user_limit'> | null
  payments: Payment[]
}
export interface CenterSubscription { center_id: number; current: Subscription | null; history: Subscription[]; payment_mode: 'MANUAL' }
export type PlanInput = Omit<Plan, 'id'>
export const planApi = {
  publicList: () => apiRequest<{ data: Plan[] }>('/api/public/plans'),
  list: () => apiRequest<{ data: Plan[] }>('/api/v1/platform/plans'),
  create: (input: PlanInput) => apiRequest<{ data: Plan }>('/api/v1/platform/plans', { method: 'POST', body: JSON.stringify(input) }),
  update: (id: number, input: Partial<PlanInput>) => apiRequest<{ data: Plan }>(`/api/v1/platform/plans/${id}`, { method: 'PATCH', body: JSON.stringify(input) }),
}
export const subscriptionApi = {
  platformShow: (centerId: number) => apiRequest<{ data: CenterSubscription }>(`/api/v1/platform/centers/${centerId}/subscription`),
  centerShow: () => apiRequest<{ data: CenterSubscription }>('/api/v1/center/subscription'),
  create: (centerId: number, input: { plan_id: number; status: Subscription['status']; started_at: string; renews_at: string | null; notes?: string | null }) =>
    apiRequest<{ data: CenterSubscription }>(`/api/v1/platform/centers/${centerId}/subscriptions`, { method: 'POST', body: JSON.stringify(input) }),
  update: (centerId: number, id: number, input: { status?: Subscription['status']; renews_at?: string | null; notes?: string | null }) =>
    apiRequest<{ data: CenterSubscription }>(`/api/v1/platform/centers/${centerId}/subscriptions/${id}`, { method: 'PATCH', body: JSON.stringify(input) }),
  recordPayment: (centerId: number, id: number, input: { amount_clp: number; status: Payment['status']; due_at: string; paid_at?: string | null; provider_reference?: string | null }) =>
    apiRequest<{ data: Payment }>(`/api/v1/platform/centers/${centerId}/subscriptions/${id}/payments`, { method: 'POST', body: JSON.stringify(input) }),
}
