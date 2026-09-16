import { LockKeyhole, ShieldCheck, Sparkles } from "lucide-react";

import { useEffect, useState, type FormEvent } from "react";

import { Link, Navigate, useNavigate, useParams } from "react-router-dom";

import { toast } from "sonner";

import { Logo } from "@/components/logo";

import { Button } from "@/components/ui/button";

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

import { Field, Input } from "@/components/ui/form-controls";

import { useClinic } from "@/state/clinic-store";

export function LoginPage({ platform = false }: { platform?: boolean }) {
  const {
    user,
    organization,
    login,
    logout,
    publicOrganizations,
    authLoading,
  } = useClinic();

  const navigate = useNavigate();

  const { centerSlug = "" } = useParams();

  const center = platform
    ? null
    : publicOrganizations.find((item) => item.slug === centerSlug);

  const base = center ? `/centro/${center.slug}` : "";

  const [email, setEmail] = useState("");

  const [password, setPassword] = useState("");

  const [error, setError] = useState("");

  const incompatibleSession = Boolean(
    user &&
    (platform
      ? user.role !== "SUPER_ADMIN"
      : user.role === "SUPER_ADMIN" || organization?.id !== center?.id),
  );

  useEffect(() => {
    document.title = platform
      ? "MedSync · Plataforma"
      : (center?.name ?? "Portal médico");
  }, [center?.name, platform]);

  useEffect(() => {
    if (incompatibleSession) {
      void logout();
    }
  }, [incompatibleSession, logout]);

  if (!platform && !center) {
    return <Navigate to="/" replace />;
  }

  /*
   * Esperamos a que Laravel responda
   * /api/v1/me antes de decidir si
   * redirigir al usuario.
   */
  if (authLoading || incompatibleSession) {
    return (
      <main className="grid min-h-screen place-items-center">
        <p>Cargando sesión…</p>
      </main>
    );
  }

  /*
   * Si Laravel ya tiene una sesión,
   * no mostramos nuevamente el login.
   */
  if (user) {
    return (
      <Navigate
        to={
          user.role === "SUPER_ADMIN"
            ? "/plataforma"
            : organization
              ? `/centro/${organization.slug}`
              : `${base}/ingresar`
        }
        replace
      />
    );
  }

  const submit = async (event: FormEvent) => {
    event.preventDefault();

    setError("");

    try {
      /*
       * Este login ahora llama a
       * Laravel mediante ClinicProvider.
       */
      const ok = await login(
        email,
        password,

        platform ? "PLATFORM" : "CENTER",

        center?.id,
      );

      if (!ok) {
        setError(
          platform
            ? "Las credenciales no corresponden a una cuenta de plataforma."
            : `El correo o la contraseña no corresponden a ${center?.name}.`,
        );

        return;
      }

      toast.success("Sesión iniciada correctamente.");

      navigate(platform ? "/plataforma" : base);
    } catch (error) {
      console.error("Error al iniciar sesión:", error);

      setError("No fue posible ingresar. Intenta nuevamente.");
    }
  };

  return (
    <main className="min-h-screen bg-[radial-gradient(circle_at_top_left,_#d7f0e8,_transparent_45%),linear-gradient(135deg,#f4faf8,#eef6f5)] p-5 sm:p-8">
      <div className="mx-auto max-w-6xl">
        <Logo name={platform ? "MedSync" : center?.name} />

        <div className="grid min-h-[78vh] items-center gap-16 lg:grid-cols-[1fr_440px]">
          <section className="hidden lg:block">
            <span className="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-primary">
              <Sparkles className="size-3.5" />
              Agenda inteligente
            </span>

            <h1 className="mt-6 max-w-xl text-6xl font-bold leading-[1.04] tracking-tight text-[#17373d]">
              Atención simple, agenda ordenada.
            </h1>

            <p className="mt-6 max-w-lg text-lg leading-8 text-muted-foreground">
              Una experiencia clara para pacientes, profesionales y equipos
              administrativos.
            </p>

            <div className="mt-9 flex gap-5 text-sm font-medium text-primary">
              <span className="flex items-center gap-2">
                <ShieldCheck className="size-4" />
                Acceso por roles
              </span>

              <span className="flex items-center gap-2">
                <LockKeyhole className="size-4" />
                Tu espacio de atención
              </span>
            </div>
          </section>

          <Card className="border-white/80 bg-white/95">
            <CardHeader>
              <CardTitle className="text-2xl">
                {platform ? "Acceso de plataforma" : `Acceso a ${center?.name}`}
              </CardTitle>

              <CardDescription>
                {platform
                  ? "Ingreso reservado para la administración de MedSync."
                  : "Ingresa a tu cuenta de este centro médico."}
              </CardDescription>
            </CardHeader>

            <CardContent>
              {!platform && (
                <Button asChild variant="outline" className="mb-5 w-full">
                  <Link to={`${base}/crear-cuenta`}>
                    Crear cuenta de paciente
                  </Link>
                </Button>
              )}

              <form className="grid gap-5" onSubmit={submit}>
                <Field label="Correo electrónico">
                  <Input
                    type="email"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    autoComplete="email"
                    required
                  />
                </Field>

                <Field label="Contraseña">
                  <Input
                    type="password"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    autoComplete="current-password"
                    required
                  />
                </Field>

                {error && (
                  <p
                    role="alert"
                    className="rounded-lg bg-rose-50 p-3 text-sm text-rose-700"
                  >
                    {error}
                  </p>
                )}

                <Button size="lg">
                  {platform ? "Ingresar a la plataforma" : "Ingresar"}
                </Button>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </main>
  );
}
