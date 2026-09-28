import { FormEvent, useMemo, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { ApiError, sanctum } from "@/services/http";

function getApiErrorMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return "No fue posible activar la cuenta. Inténtalo nuevamente.";
  }

  if (error.errors) {
    const firstError = Object.values(error.errors).flat().find(Boolean);

    if (firstError) {
      return firstError;
    }
  }

  return error.message || "No fue posible activar la cuenta.";
}

export function ActivateAccountPage() {
  const [searchParams] = useSearchParams();

  const email = useMemo(
    () => searchParams.get("email")?.trim() ?? "",
    [searchParams],
  );

  const token = useMemo(
    () => searchParams.get("token")?.trim() ?? "",
    [searchParams],
  );

  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [activated, setActivated] = useState(false);

  const validInvitation = Boolean(email && token);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    setError("");

    if (!validInvitation) {
      setError("El enlace de activación no es válido.");
      return;
    }

    if (password.length < 8) {
      setError("La contraseña debe tener al menos 8 caracteres.");
      return;
    }

    if (!/[a-z]/.test(password)) {
      setError("La contraseña debe contener al menos una letra minúscula.");
      return;
    }

    if (!/[A-Z]/.test(password)) {
      setError("La contraseña debe contener al menos una letra mayúscula.");
      return;
    }

    if (!/\d/.test(password)) {
      setError("La contraseña debe contener al menos un número.");
      return;
    }

    if (password !== passwordConfirmation) {
      setError("Las contraseñas no coinciden.");
      return;
    }

    try {
      setSubmitting(true);

      await sanctum.activateAccount({
        email,
        token,
        password,
        password_confirmation: passwordConfirmation,
      });

      setActivated(true);
      setPassword("");
      setPasswordConfirmation("");
    } catch (requestError) {
      setError(getApiErrorMessage(requestError));
    } finally {
      setSubmitting(false);
    }
  }

  if (activated) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-muted/30 px-4">
        <section className="w-full max-w-md rounded-xl border bg-background p-8 shadow-sm">
          <div className="text-center">
            <div className="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-2xl text-emerald-700">
              ✓
            </div>

            <h1 className="text-2xl font-bold">Cuenta activada</h1>

            <p className="mt-3 text-sm text-muted-foreground">
              Tu contraseña fue creada correctamente. Ya puedes ingresar a
              MedSync con tu correo electrónico.
            </p>

            <Link
              to="/ingresar"
              className="mt-6 inline-flex h-10 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
            >
              Ir al inicio de sesión
            </Link>
          </div>
        </section>
      </main>
    );
  }

  if (!validInvitation) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-muted/30 px-4">
        <section className="w-full max-w-md rounded-xl border bg-background p-8 shadow-sm">
          <div className="text-center">
            <h1 className="text-2xl font-bold">Enlace no válido</h1>

            <p className="mt-3 text-sm text-muted-foreground">
              El enlace de activación está incompleto o no contiene la
              información necesaria.
            </p>

            <Link
              to="/ingresar"
              className="mt-6 inline-flex h-10 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
            >
              Ir al inicio de sesión
            </Link>
          </div>
        </section>
      </main>
    );
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-10">
      <section className="w-full max-w-md rounded-xl border bg-background p-8 shadow-sm">
        <div className="mb-6">
          <h1 className="text-2xl font-bold">Activa tu cuenta</h1>

          <p className="mt-2 text-sm text-muted-foreground">
            Crea una contraseña para comenzar a utilizar tu cuenta profesional
            en MedSync.
          </p>
        </div>

        <div className="mb-6 rounded-md border bg-muted/40 px-4 py-3">
          <p className="text-xs font-medium text-muted-foreground">Cuenta</p>

          <p className="mt-1 break-all text-sm font-medium">{email}</p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-5">
          <div className="space-y-2">
            <label htmlFor="password" className="text-sm font-medium">
              Nueva contraseña
            </label>

            <input
              id="password"
              type="password"
              autoComplete="new-password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              disabled={submitting}
              className="flex h-10 w-full rounded-md border bg-background px-3 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"
              placeholder="Ingresa tu nueva contraseña"
            />

            <p className="text-xs text-muted-foreground">
              Debe tener al menos 8 caracteres, una mayúscula, una minúscula y
              un número.
            </p>
          </div>

          <div className="space-y-2">
            <label
              htmlFor="password_confirmation"
              className="text-sm font-medium"
            >
              Confirmar contraseña
            </label>

            <input
              id="password_confirmation"
              type="password"
              autoComplete="new-password"
              value={passwordConfirmation}
              onChange={(event) => setPasswordConfirmation(event.target.value)}
              disabled={submitting}
              className="flex h-10 w-full rounded-md border bg-background px-3 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"
              placeholder="Repite tu nueva contraseña"
            />
          </div>

          {error && (
            <div
              role="alert"
              className="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
              {error}
            </div>
          )}

          <button
            type="submit"
            disabled={submitting}
            className="inline-flex h-10 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {submitting ? "Activando cuenta..." : "Crear contraseña"}
          </button>
        </form>
      </section>
    </main>
  );
}
