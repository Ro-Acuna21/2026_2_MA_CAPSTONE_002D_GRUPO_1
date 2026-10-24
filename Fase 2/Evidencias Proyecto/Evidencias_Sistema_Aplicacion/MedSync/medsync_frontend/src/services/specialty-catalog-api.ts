import { ApiError, apiRequest, firstApiErrorMessage } from "./http";

/** Contrato propuesto: estas rutas aún no existen en Laravel. */
export interface GlobalSpecialty {
  id: number;
  name: string;
  description: string | null;
  is_active: boolean;
}

/** null indica que el centro todavía no seleccionó la especialidad. */
export interface CenterCatalogSpecialty extends GlobalSpecialty {
  center_is_active: boolean | null;
}

interface ItemResponse<T> { data: T }
interface ListResponse<T> { data: T[] }

export const proposedSpecialtyCatalogApi = {
  globalList: () => apiRequest<ListResponse<GlobalSpecialty>>("/api/v1/platform/specialties"),
  globalCreate: (input: { name: string; description: string | null }) =>
    apiRequest<ItemResponse<GlobalSpecialty>>("/api/v1/platform/specialties", {
      method: "POST", body: JSON.stringify(input),
    }),
  globalUpdate: (id: number, input: { name?: string; description?: string | null; is_active?: boolean }) =>
    apiRequest<ItemResponse<GlobalSpecialty>>(`/api/v1/platform/specialties/${id}`, {
      method: "PATCH", body: JSON.stringify(input),
    }),
  centerList: () => apiRequest<ListResponse<CenterCatalogSpecialty>>("/api/v1/center/specialties"),
  centerSetActive: (id: number, is_active: boolean) =>
    apiRequest<ItemResponse<CenterCatalogSpecialty>>(`/api/v1/center/specialties/${id}`, {
      method: "PUT", body: JSON.stringify({ is_active }),
    }),
};

export function specialtyCatalogError(error: unknown): string {
  if (error instanceof ApiError && [404, 405, 501].includes(error.status)) {
    return "El catálogo global aún no está disponible en el backend. La selección y los cambios están pendientes de integración.";
  }
  return firstApiErrorMessage(error, "No fue posible consultar el catálogo global.");
}
