import { useEffect } from "react";

import { Link, Navigate, useNavigate, useParams } from "react-router-dom";

import { toast } from "sonner";

import { ActionForm } from "@/components/action-form";

import { ConsentField, PatientFields } from "@/components/patient-fields";

import { Logo } from "@/components/logo";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

import { Field, Input } from "@/components/ui/form-controls";

import { ApiError, sanctum } from "@/services/http";

import { useClinic } from "@/state/clinic-store";

function firstApiError(error: ApiError) {
  const firstFieldMessage = Object.values(error.errors ?? {})
    .flat()
    .find(Boolean);

  return firstFieldMessage ?? error.message;
}

/*
 * REGISTRO REAL
 *
 * Ya no pasa por
 * useClinic().register().
 *
 * Se comunica directamente
 * con Laravel.
 */
async function registerWithBackend(values: Record<string, string>) {
  try {
    return await sanctum.register({
      first_name: values.firstName.trim(),

      last_name: values.lastName.trim(),

      rut: values.rut,

      birth_date: values.birthDate,

      email: values.email.trim(),

      phone: values.phone,

      health_insurance: values.healthInsurance,

      medical_insurance: values.medicalInsurance || null,

      address: values.address || null,

      password: values.password,

      password_confirmation: values.confirmation,

      consent: values.consent === "on",
    });
  } catch (error) {
    const detail =
      error instanceof ApiError
        ? firstApiError(error)
        : "No se pudo conectar con el backend.";

    console.error("No fue posible registrar la cuenta:", error);

    toast.error(detail);

    /*
     * Es importante volver a lanzar
     * el error.
     *
     * Así ActionForm sabe que el
     * registro falló y no continúa.
     */
    throw error;
  }
}

export function RegisterPage() {
  const {
    user,
    publicOrganizations,
    organization,
    logout,
    login,
    authLoading,
  } = useClinic();

  const navigate = useNavigate();

  const { centerSlug = "" } = useParams();

  const center = publicOrganizations.find((item) => item.slug === centerSlug);

  const base = `/centro/${centerSlug}`;

  const incompatibleSession = Boolean(
    user && (user.role === "SUPER_ADMIN" || organization?.id !== center?.id),
  );

  useEffect(() => {
    document.title = center?.name ?? "Portal médico";
  }, [center?.name]);

  useEffect(() => {
    if (incompatibleSession) {
      void logout();
    }
  }, [incompatibleSession, logout]);

  if (!center) {
    return <Navigate to="/" replace />;
  }

  if (authLoading || incompatibleSession) {
    return (
      <main className="grid min-h-screen place-items-center">
        <p>Cargando sesión…</p>
      </main>
    );
  }

  /*
   * Si ya existe una sesión Laravel,
   * no permitimos volver a crear
   * cuenta.
   */
  if (user) {
    return (
      <Navigate
        to={organization?.id === center.id ? base : `${base}/ingresar`}
        replace
      />
    );
  }

  return (
    <main className="min-h-screen bg-secondary p-5 sm:p-8">
      <div className="mx-auto max-w-3xl">
        <Logo name={center.name} />

        <Card className="mt-8">
          <CardHeader>
            <CardTitle>Crear cuenta de paciente</CardTitle>

            <p className="text-sm text-muted-foreground">
              Crea tu acceso privado a {center.name}.
            </p>

            <p className="text-sm text-muted-foreground">
              Si recepción ya creó tu ficha, usa el mismo RUT y correo para
              activar tu acceso sin duplicar tus datos.
            </p>
          </CardHeader>

          <CardContent>
            <ActionForm
              className="sm:grid-cols-2"
              label="Crear mi cuenta"
              onSave={async (values) => {
                // 1. Crear la cuenta en Laravel/PostgreSQL
                await registerWithBackend(values);

                // 2. Iniciar sesión automáticamente
                const loggedIn = await login(
                  values.email.trim(),
                  values.password,
                  "CENTER",
                  center.id,
                );

                // 3. Si por alguna razón el login automático falla,
                // la cuenta igual quedó creada.
                if (!loggedIn) {
                  toast.success(
                    "Cuenta creada correctamente. Inicia sesión para continuar.",
                  );

                  navigate(`${base}/ingresar`);
                  return;
                }

                // 4. Si el login fue correcto, entra directo al portal
                toast.success("Cuenta creada correctamente. Bienvenido.");

                navigate(base);
              }}
            >
              <PatientFields />

              <Field label="Contraseña">
                <Input
                  name="password"
                  type="password"
                  autoComplete="new-password"
                  minLength={8}
                  required
                />
              </Field>

              <Field label="Confirmar contraseña">
                <Input
                  name="confirmation"
                  type="password"
                  autoComplete="new-password"
                  minLength={8}
                  required
                />
              </Field>

              <ConsentField />

              <p className="col-span-full text-xs text-muted-foreground">
                Mínimo 8 caracteres, con mayúscula, minúscula y número.
              </p>
            </ActionForm>

            <Link
              className="mt-6 inline-block text-sm font-medium text-primary underline"
              to={`${base}/ingresar`}
            >
              Ya tengo cuenta · Ingresar
            </Link>
          </CardContent>
        </Card>
      </div>
    </main>
  );
}
