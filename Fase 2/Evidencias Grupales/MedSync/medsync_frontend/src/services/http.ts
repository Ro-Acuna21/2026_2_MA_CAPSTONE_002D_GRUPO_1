import type { Role } from "@/domain/types";

const API_URL = import.meta.env.VITE_API_URL ?? "http://localhost:8000";

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

export interface PatientRegisterPayload {
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

export const sanctum = {
  csrf: () => apiRequest<void>("/sanctum/csrf-cookie"),

  login: async (email: string, password: string) => {
    await sanctum.csrf();

    return apiRequest<AuthResponse>("/api/login", {
      method: "POST",
      body: JSON.stringify({
        email,
        password,
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
};
