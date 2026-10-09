import { Navigate } from "react-router-dom";
import { useEffect, useState, type MouseEvent } from "react";
import { PageHeading } from "@/components/page-heading";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, Input, Select } from "@/components/ui/form-controls";
import { ActionForm } from "@/components/action-form";
import { AccessForm } from "@/components/access-form";
import { useClinic } from "@/state/clinic-store";
import {
  ApiError,
  professionalApi,
  type BackendProfessional,
} from "@/services/http";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";

import type {
  Professional,
  Specialty,
  Service,
  Availability,
} from "@/domain/types";

const days = [
  "Domingo",
  "Lunes",
  "Martes",
  "Miércoles",
  "Jueves",
  "Viernes",
  "Sábado",
];
function ActiveField({ active = true }: { active?: boolean }) {
  return (
    <Field label="Estado">
      <Select name="active" defaultValue={String(active)}>
        <option value="true">Activo</option>
        <option value="false">Inactivo</option>
      </Select>
    </Field>
  );
}
function ProfessionalForm({ item }: { item?: Professional }) {
  const { data, saveProfessional } = useClinic();
  return (
    <ActionForm
      reset={!item}
      className="sm:grid-cols-2"
      onSave={(v) =>
        saveProfessional(
          {
            name: v.name,
            rut: v.rut,
            email: v.email.trim().toLowerCase(),
            phone: v.phone,
            specialtyIds: [v.specialtyId],
            description: v.description,
            active: v.active === "true",
          },
          item?.id,
        )
      }
    >
      <Field label="Nombre del profesional">
        <Input name="name" defaultValue={item?.name} required />
      </Field>
      <Field label="RUT">
        <Input name="rut" defaultValue={item?.rut} required />
      </Field>
      <Field label="Correo">
        <Input name="email" type="email" defaultValue={item?.email} required />
      </Field>
      <Field label="Teléfono">
        <Input name="phone" defaultValue={item?.phone} required />
      </Field>
      <Field label="Especialidad">
        <Select
          name="specialtyId"
          defaultValue={item?.specialtyIds[0] ?? ""}
          required
        >
          <option value="">Seleccionar</option>
          {data.specialties.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </Select>
      </Field>
      <Field label="Descripción">
        <Input name="description" defaultValue={item?.description} />
      </Field>
      <ActiveField active={item?.active} />
    </ActionForm>
  );
}
function CatalogForm({
  kind,
  item,
}: {
  kind: "specialty" | "service" | "availability";
  item?: Specialty | Service | Availability;
}) {
  const { data, saveCatalog } = useClinic();
  const availability = item && "weekday" in item ? item : undefined;
  const service = item && "duration" in item ? item : undefined;
  return (
    <ActionForm
      reset={!item}
      className="sm:grid-cols-2"
      onSave={(v) => saveCatalog(kind, v, item?.id)}
    >
      {kind !== "availability" ? (
        <Field label="Nombre">
          <Input
            name="name"
            defaultValue={item && "name" in item ? item.name : ""}
            required
          />
        </Field>
      ) : (
        <>
          <Field label="Profesional">
            <Select
              name="professionalId"
              defaultValue={availability?.professionalId ?? ""}
              required
            >
              <option value="">Seleccionar</option>
              {data.professionals.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Día de la semana">
            <Select name="weekday" defaultValue={availability?.weekday ?? 1}>
              {days.map((d, index) => (
                <option key={d} value={index}>
                  {d}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Desde">
            <Input
              name="start"
              type="time"
              defaultValue={availability?.start}
              required
            />
          </Field>
          <Field label="Hasta">
            <Input
              name="end"
              type="time"
              defaultValue={availability?.end}
              required
            />
          </Field>
        </>
      )}
      {kind === "specialty" && (
        <Field label="Descripción">
          <Input
            name="description"
            defaultValue={item && "description" in item ? item.description : ""}
          />
        </Field>
      )}
      {kind === "service" && (
        <>
          <Field label="Especialidad">
            <Select
              name="specialtyId"
              defaultValue={service?.specialtyId ?? ""}
              required
            >
              <option value="">Seleccionar</option>
              {data.specialties.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Duración en minutos">
            <Input
              name="duration"
              type="number"
              min={5}
              max={480}
              defaultValue={service?.duration ?? 30}
              required
            />
          </Field>
        </>
      )}
      <ActiveField active={item?.active} />
    </ActionForm>
  );
}
function ProfessionalAccess({
  professionalId,
  professionalName,
  backendProfessional,
  loading,
  onAccessEnabled,
}: {
  professionalId: number;
  professionalName: string;
  backendProfessional?: BackendProfessional;
  loading: boolean;
  onAccessEnabled: (professionalId: number, userId: number) => void;
}) {
  const [submitting, setSubmitting] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);

  const hasAccess = backendProfessional?.user_id != null;
  const accessKnown = backendProfessional !== undefined;

  async function handleEnableAccess() {
    try {
      setSubmitting(true);

      const response = await professionalApi.enableAccess(professionalId);

      onAccessEnabled(professionalId, response.data.user_id);

      if (response.data.invitation_sent) {
        toast.success(
          "Acceso habilitado. La invitación fue generada correctamente.",
        );
      } else {
        toast.success("El profesional fue vinculado correctamente a MedSync.");
      }
    } catch (error) {
      if (error instanceof ApiError) {
        const firstValidationError = error.errors
          ? Object.values(error.errors).flat()[0]
          : undefined;

        toast.error(
          firstValidationError ??
            error.message ??
            "No fue posible habilitar el acceso.",
        );

        return;
      }

      toast.error("No fue posible habilitar el acceso del profesional.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="my-4 rounded-lg border bg-muted/30 p-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p className="text-sm font-medium">Acceso al sistema</p>

          <p className="mt-1 text-xs text-muted-foreground">
            {loading
              ? "Consultando estado de acceso..."
              : !accessKnown
                ? "No fue posible determinar el estado de acceso."
                : hasAccess
                  ? "Este profesional ya tiene una cuenta vinculada a MedSync."
                  : "Este profesional todavía no tiene una cuenta de acceso."}
          </p>
        </div>

        {!loading && accessKnown && hasAccess && (
          <span className="inline-flex w-fit rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">
            Acceso habilitado
          </span>
        )}

        {!loading && accessKnown && !hasAccess && (
          <>
            <Button
              type="button"
              onClick={() => setConfirmOpen(true)}
              disabled={submitting}
            >
              Habilitar acceso
            </Button>

            <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>
                    Habilitar acceso al sistema
                  </AlertDialogTitle>

                  <AlertDialogDescription>
                    Se creará el acceso a MedSync para{" "}
                    <strong>{professionalName}</strong> y se generará una
                    invitación para crear su contraseña.
                  </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                  <AlertDialogCancel disabled={submitting}>
                    Cancelar
                  </AlertDialogCancel>

                  <AlertDialogAction
                    disabled={submitting}
                    onClick={(event: MouseEvent<HTMLButtonElement>) => {
                      event.preventDefault();
                      void handleEnableAccess();
                    }}
                  >
                    {submitting ? "Habilitando..." : "Habilitar acceso"}
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          </>
        )}
      </div>
    </div>
  );
}
export function AdminPage() {
  const { data, user, organization } = useClinic();
  const [backendProfessionals, setBackendProfessionals] = useState<
    Record<number, BackendProfessional>
  >({});

  const [loadingProfessionalAccess, setLoadingProfessionalAccess] =
    useState(true);
  useEffect(() => {
    if (user?.role !== "ADMIN") {
      setLoadingProfessionalAccess(false);
      return;
    }

    let cancelled = false;

    async function loadProfessionals() {
      try {
        setLoadingProfessionalAccess(true);

        const response = await professionalApi.list();

        if (cancelled) {
          return;
        }

        const professionalsById = response.data.reduce<
          Record<number, BackendProfessional>
        >((result, professional) => {
          result[professional.id] = professional;
          return result;
        }, {});

        setBackendProfessionals(professionalsById);
      } catch {
        if (!cancelled) {
          toast.error(
            "No fue posible consultar el estado de acceso de los profesionales.",
          );
        }
      } finally {
        if (!cancelled) {
          setLoadingProfessionalAccess(false);
        }
      }
    }

    void loadProfessionals();

    return () => {
      cancelled = true;
    };
  }, [user?.role]);
  function handleAccessEnabled(professionalId: number, userId: number) {
    setBackendProfessionals((current) => {
      const professional = current[professionalId];

      if (!professional) {
        return current;
      }

      return {
        ...current,
        [professionalId]: {
          ...professional,
          user_id: userId,
        },
      };
    });
  }
  if (user?.role !== "ADMIN") return <Navigate to="/" replace />;
  return (
    <>
      <PageHeading
        title="Administración del centro"
        description="Usuarios, profesionales y configuración general del centro médico."
      />
      <div className="grid gap-5">
        <Card>
          <CardHeader>
            <CardTitle>Profesionales</CardTitle>
          </CardHeader>
          <CardContent>
            <details className="mb-5">
              <summary className="cursor-pointer font-medium text-primary">
                Registrar profesional
              </summary>
              <div className="mt-4">
                <ProfessionalForm />
              </div>
            </details>
            <div className="grid gap-3">
              {data.professionals.map((p) => {
                const backendProfessional = backendProfessionals[Number(p.id)];

                return (
                  <details className="rounded-lg border p-4" key={p.id}>
                    <summary className="cursor-pointer">
                      {p.name} · {p.active ? "Activo" : "Inactivo"}
                    </summary>

                    <p className="my-3 text-xs text-muted-foreground">
                      La ficha no crea una cuenta de acceso automáticamente.
                    </p>

                    <ProfessionalAccess
                      professionalId={Number(p.id)}
                      professionalName={p.name}
                      backendProfessional={backendProfessional}
                      loading={loadingProfessionalAccess}
                      onAccessEnabled={handleAccessEnabled}
                    />

                    <ProfessionalForm item={p} />
                  </details>
                );
              })}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Horarios base de atención</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="mb-4 text-sm text-muted-foreground">
              Define la disponibilidad habitual. La agenda diaria y sus reservas
              pertenecen a recepción.
            </p>
            <details className="mb-4">
              <summary className="cursor-pointer font-medium text-primary">
                Agregar horario base
              </summary>
              <div className="mt-4">
                <CatalogForm kind="availability" />
              </div>
            </details>
            {data.availability.map((a) => (
              <details className="mb-2 rounded-lg border p-3" key={a.id}>
                <summary className="cursor-pointer text-sm">
                  {
                    data.professionals.find((p) => p.id === a.professionalId)
                      ?.name
                  }{" "}
                  · {days[a.weekday]} · {a.start}–{a.end}{" "}
                  {a.active ? "" : "· Inactivo"}
                </summary>
                <div className="mt-4">
                  <CatalogForm kind="availability" item={a} />
                </div>
              </details>
            ))}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Especialidades y prestaciones</CardTitle>
          </CardHeader>
          <CardContent>
            <details className="mb-4">
              <summary className="cursor-pointer font-medium text-primary">
                Nueva especialidad
              </summary>
              <div className="mt-4">
                <CatalogForm kind="specialty" />
              </div>
            </details>
            <details className="mb-4">
              <summary className="cursor-pointer font-medium text-primary">
                Nueva prestación
              </summary>
              <div className="mt-4">
                <CatalogForm kind="service" />
              </div>
            </details>
            {data.specialties.map((s) => (
              <details className="mb-2 rounded-lg border p-3" key={s.id}>
                <summary className="cursor-pointer">
                  Especialidad · {s.name}
                </summary>
                <div className="mt-4">
                  <CatalogForm kind="specialty" item={s} />
                </div>
              </details>
            ))}
            {data.services.map((s) => (
              <details className="mb-2 rounded-lg border p-3" key={s.id}>
                <summary className="cursor-pointer">
                  Prestación · {s.name} · {s.duration} min
                </summary>
                <div className="mt-4">
                  <CatalogForm kind="service" item={s} />
                </div>
              </details>
            ))}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Usuarios y permisos del centro</CardTitle>
          </CardHeader>
          <CardContent>
            <AccessForm centerId={organization!.id} />
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-5">
            <p>
              Plan {organization?.plan} · Suscripción:{" "}
              {organization?.subscription ?? "ACTIVE"}
            </p>
            <p className="mt-2 text-sm text-muted-foreground">
              La administración de plataforma gestiona los cambios de
              suscripción.
            </p>
          </CardContent>
        </Card>
      </div>
    </>
  );
}
