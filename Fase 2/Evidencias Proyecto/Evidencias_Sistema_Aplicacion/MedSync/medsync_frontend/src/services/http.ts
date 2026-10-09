import type { AppointmentStatus, Role } from "@/domain/types";

// En desarrollo, API y Vite deben usar el mismo host (localhost o
// 127.0.0.1) para que el navegador pueda leer la cookie XSRF de Sanctum.
const API_URL =
  import.meta.env.VITE_API_URL ??
  `${window.location.protocol}//${window.location.hostname}:8000`;

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors?: Record<string, string[]>,
  ) {
    super(message);
  }
}

// Laravel/Sanctum deja el token CSRF en la cookie XSRF-TOKEN.
// Como usamos fetch(), debemos enviarlo manualmente en el header.
function xsrfHeader(): Record<string, string> {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

  return match
    ? {
        "X-XSRF-TOKEN": decodeURIComponent(match[1]),
      }
    : {};
}

export async function apiRequest<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    ...init,
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...xsrfHeader(),
      ...init.headers,
    },
  });

  if (!response.ok) {
    const body = await response.json().catch(() => ({
      message: "No fue posible completar la solicitud.",
    }));

    throw new ApiError(response.status, body.message, body.errors);
  }

  return response.status === 204 ? (undefined as T) : response.json();
}

/**
 * Extrae el primer mensaje de validación de un ApiError, o su mensaje
 * general si no trae errores de campo. Útil para mostrar un solo
 * mensaje de error en toasts/formularios.
 */
export function firstApiErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    const firstField = error.errors ? Object.values(error.errors)[0] : undefined;

    return firstField?.[0] ?? error.message ?? fallback;
  }

  return error instanceof Error ? error.message : fallback;
}

export interface PatientRegisterPayload {
  center_slug: string;
  first_name: string;
  last_name: string;
  rut: string;
  birth_date: string;
  email: string;
  phone: string;
  health_insurance: string;
  medical_insurance?: string | null;
  address?: string | null;
  password: string;
  password_confirmation: string;
  consent: boolean;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: Role | null;

  medical_center: {
    id: number;
    name: string;
    slug: string;
  } | null;

  patient: {
    id: number;
  } | null;

  professional: {
    id: number;
  } | null;
}

export interface AuthResponse {
  data: AuthUser;
}
export interface ProfessionalPayload {
  first_name: string;
  last_name: string;
  rut: string;
  email: string;
  phone: string;
  is_active?: boolean;
}

export interface BackendProfessional {
  id: number;
  user_id: number | null;
  first_name: string;
  last_name: string;
  rut: string;
  email: string;
  phone: string;
  is_active: boolean;
}

export interface ProfessionalResponse {
  data: BackendProfessional;
}

export interface ProfessionalsResponse {
  data: BackendProfessional[];
}
export interface ProfessionalAccessResponse {
  message: string;

  data: {
    professional_id: number;
    user_id: number;
    role: "PROFESIONAL";
    account_created: boolean;
    invitation_sent: boolean;
  };
}
export interface ActivateAccountPayload {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}

export interface MessageResponse {
  message: string;
}
export interface PublicCentersResponse {
  data: { id: number; name: string; slug: string }[];
}
export const publicCenterApi = {
  list: () => apiRequest<PublicCentersResponse>("/api/public/centers"),
};
export const sanctum = {
  csrf: () => apiRequest<void>("/sanctum/csrf-cookie"),
  activateAccount: async (payload: ActivateAccountPayload) => {
    await sanctum.csrf();

    return apiRequest<MessageResponse>("/api/auth/activate-account", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },

  login: async (email: string, password: string, centerSlug?: string) => {
    await sanctum.csrf();

    return apiRequest<AuthResponse>("/api/login", {
      method: "POST",
      body: JSON.stringify({
        email,
        password,
        ...(centerSlug ? { center_slug: centerSlug } : {}),
      }),
    });
  },

  logout: () =>
    apiRequest<void>("/api/logout", {
      method: "POST",
    }),

  me: () => apiRequest<AuthResponse>("/api/v1/me"),

  register: async (payload: PatientRegisterPayload) => {
    await sanctum.csrf();

    return apiRequest<AuthResponse>("/api/register", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },
};
export const professionalApi = {
  list: () => apiRequest<ProfessionalsResponse>("/api/v1/professionals"),

  create: (payload: ProfessionalPayload) =>
    apiRequest<ProfessionalResponse>("/api/v1/professionals", {
      method: "POST",
      body: JSON.stringify(payload),
    }),

  enableAccess: async (professionalId: number) => {
    await sanctum.csrf();

    return apiRequest<ProfessionalAccessResponse>(
      `/api/v1/professionals/${professionalId}/enable-access`,
      {
        method: "POST",
      },
    );
  },
};

/*
 * Reservas y disponibilidad (README_RESERVAS_DISPONIBILIDAD_BD.md).
 *
 * Por ahora estos endpoints solo cubren la reserva realizada por el
 * propio paciente autenticado (POST /api/v1/appointments resuelve el
 * paciente desde la sesión). La agenda de recepción/profesional y la
 * administración del catálogo (especialidades, prestaciones,
 * disponibilidad) todavía no están conectadas al backend.
 */
export interface BackendSpecialtySummary {
  id: number;
  name: string;
}

export interface BackendService {
  id: number;
  name: string;
  description: string | null;
  duration_minutes: number;
  specialty: BackendSpecialtySummary | null;
}

export interface ServicesResponse {
  data: BackendService[];
}

export interface BackendProfessionalSummary {
  id: number;
  first_name: string;
  last_name: string;
}

export interface ServiceProfessionalsResponse {
  data: BackendProfessionalSummary[];
}

export interface BackendPatientSummary {
  id: number;
  first_name: string;
  last_name: string;
  rut: string;
}

export interface BackendPatient extends BackendPatientSummary {
  birth_date: string | null;
  email: string;
  phone: string;
  health_insurance: string | null;
  medical_insurance: string | null;
  address: string | null;
  consent: boolean;
  is_active: boolean;
  has_account: boolean;
}

export interface PatientPayload {
  first_name: string;
  last_name: string;
  rut: string;
  birth_date: string;
  email: string;
  phone: string;
  health_insurance: string;
  medical_insurance?: string | null;
  address?: string | null;
  consent: boolean;
}

export type PatientUpdatePayload = Partial<Omit<PatientPayload, 'rut' | 'consent'>>;

export const patientApi = {
  list: () => apiRequest<{ data: BackendPatient[] }>("/api/v1/patients"),
  show: (id: number) => apiRequest<{ data: BackendPatient }>(`/api/v1/patients/${id}`),
  create: (payload: PatientPayload) => apiRequest<{ data: BackendPatient }>("/api/v1/patients", {
    method: "POST", body: JSON.stringify(payload),
  }),
  update: (id: number, payload: PatientUpdatePayload) => apiRequest<{ data: BackendPatient }>(`/api/v1/patients/${id}`, {
    method: "PATCH", body: JSON.stringify(payload),
  }),
};

export interface PatientsResponse {
  data: BackendPatientSummary[];
}

export interface AvailableSlotsResponse {
  data: {
    date: string;
    professional_id: number;
    service_id: number;
    duration_minutes: number;
    slots: string[];
  };
}

export interface BackendAppointment {
  id: number;
  appointment_date: string;
  start_time: string;
  end_time: string;
  status: AppointmentStatus;
  source: "WEB" | "RECEPCION" | "DEMO";
  overbook: boolean;
  note: string | null;

  patient: {
    id: number;
    first_name: string;
    last_name: string;
  } | null;

  service: {
    id: number;
    name: string;
    duration_minutes: number;
    specialty: BackendSpecialtySummary | null;
  } | null;

  professional: {
    id: number;
    first_name: string;
    last_name: string;
  } | null;
}

export interface AppointmentResponse {
  message: string;
  data: BackendAppointment;
}

export interface AppointmentsResponse {
  data: BackendAppointment[];
}

export interface CreateAppointmentPayload {
  service_id: number;
  professional_id: number;
  appointment_date: string;
  start_time: string;
  patient_id?: number;
}

export interface RescheduleAppointmentPayload {
  service_id?: number;
  professional_id?: number;
  reassignment_reason?: string;
  appointment_date: string;
  start_time: string;
}

export const appointmentApi = {
  services: () => apiRequest<ServicesResponse>("/api/v1/services"),

  professionalsForService: (serviceId: number) =>
    apiRequest<ServiceProfessionalsResponse>(
      `/api/v1/services/${serviceId}/professionals`,
    ),

  availableSlots: (serviceId: number, professionalId: number, date: string) =>
    apiRequest<AvailableSlotsResponse>(
      `/api/v1/appointments/available-slots?service_id=${serviceId}&professional_id=${professionalId}&date=${date}`,
    ),

  my: () => apiRequest<AppointmentsResponse>("/api/v1/appointments/my"),

  list: () => apiRequest<AppointmentsResponse>("/api/v1/appointments"),

  patients: () => apiRequest<PatientsResponse>("/api/v1/patients"),

  create: (payload: CreateAppointmentPayload) =>
    apiRequest<AppointmentResponse>("/api/v1/appointments", {
      method: "POST",
      body: JSON.stringify(payload),
    }),

  reschedule: (id: number, payload: RescheduleAppointmentPayload) =>
    apiRequest<AppointmentResponse>(`/api/v1/appointments/${id}/reschedule`, {
      method: "PATCH",
      body: JSON.stringify(payload),
    }),

  cancel: (id: number, reason?: string) =>
    apiRequest<AppointmentResponse>(`/api/v1/appointments/${id}/cancel`, {
      method: "PATCH",
      body: JSON.stringify({ reason }),
    }),
};
