import { apiRequest } from './http'

export interface CenterAccess {
  id: number
  user_id: number
  name: string
  email: string
  role: 'ADMIN' | 'RECEPCIONISTA'
  is_active: boolean
  invitation_pending: boolean
  invitation_sent_at: string | null
}

const base = (platform: boolean, centerId: string) => platform
  ? `/api/v1/platform/centers/${centerId}/administrators`
  : '/api/v1/center/receptionists'

export const centerAccessApi = {
  staff: (centerId: string) => apiRequest<{ data: CenterAccess[] }>(`/api/v1/platform/centers/${centerId}/staff`),
  changeStaffRole: (centerId: string, membershipId: number, role: CenterAccess['role']) =>
    apiRequest<{ data: CenterAccess }>(`/api/v1/platform/centers/${centerId}/staff/${membershipId}/role`, {
      method: 'PATCH', body: JSON.stringify({ role }),
    }),
  list: (platform: boolean, centerId: string) => apiRequest<{ data: CenterAccess[] }>(base(platform, centerId)),
  assign: (platform: boolean, centerId: string, input: { name: string; email: string }) =>
    apiRequest<{ data: CenterAccess; message: string; invitation_sent: boolean; account_created: boolean }>(base(platform, centerId), {
      method: 'POST', body: JSON.stringify(input),
    }),
  setActive: (platform: boolean, centerId: string, membershipId: number, isActive: boolean) =>
    apiRequest<{ data: CenterAccess }>(`${base(platform, centerId)}/${membershipId}`, {
      method: 'PATCH', body: JSON.stringify({ is_active: isActive }),
    }),
  resend: (platform: boolean, centerId: string, membershipId: number) =>
    apiRequest<{ data: CenterAccess; message: string; invitation_sent: boolean }>(`${base(platform, centerId)}/${membershipId}/resend-invitation`, {
      method: 'POST',
    }),
}
