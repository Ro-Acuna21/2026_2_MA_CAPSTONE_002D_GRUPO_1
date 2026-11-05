import { useEffect, useState } from "react";
import { Navigate } from "react-router-dom";
import { ActionForm } from "@/components/action-form";
import { PageHeading } from "@/components/page-heading";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, Input } from "@/components/ui/form-controls";
import { useClinic } from "@/state/clinic-store";
import {
  specialtyCatalogApi,
  specialtyCatalogError,
  type GlobalSpecialty,
} from "@/services/specialty-catalog-api";

export function PlatformSpecialtiesPage() {
  const { user } = useClinic();
  const [items, setItems] = useState<GlobalSpecialty[]>([]);
  const [status, setStatus] = useState<"loading" | "ready" | "error">("loading");
  const [error, setError] = useState("");
  const [reload, setReload] = useState(0);
  const [busyId, setBusyId] = useState<number | null>(null);

  useEffect(() => {
    if (user?.role !== "SUPER_ADMIN") return;
    let cancelled = false;
    specialtyCatalogApi.globalList()
      .then((response) => {
        if (cancelled) return;
        setItems(response.data);
        setStatus("ready");
      })
      .catch((failure) => {
        if (cancelled) return;
        setError(specialtyCatalogError(failure));
        setStatus("error");
      });
    return () => { cancelled = true; };
  }, [user?.role, reload]);

  if (user?.role !== "SUPER_ADMIN") return <Navigate to="/" replace />;

  async function create(values: Record<string, string>) {
    const response = await specialtyCatalogApi.globalCreate({
      name: values.name.trim(),
      description: values.description.trim() || null,
    }).catch((failure: unknown) => { throw new Error(specialtyCatalogError(failure)); });
    setItems((current) => [...current, response.data].sort((a, b) => a.name.localeCompare(b.name, "es")));
  }

  async function update(item: GlobalSpecialty, values: Record<string, string>) {
    const response = await specialtyCatalogApi.globalUpdate(item.id, {
      name: values.name.trim(),
      description: values.description.trim() || null,
    }).catch((failure: unknown) => { throw new Error(specialtyCatalogError(failure)); });
    setItems((current) => current.map((entry) => entry.id === item.id ? response.data : entry));
  }

  async function setActive(item: GlobalSpecialty) {
    setBusyId(item.id);
    setError("");
    try {
      const response = await specialtyCatalogApi.globalUpdate(item.id, { is_active: !item.is_active });
      setItems((current) => current.map((entry) => entry.id === item.id ? response.data : entry));
    } catch (failure) {
      setError(specialtyCatalogError(failure));
    } finally {
      setBusyId(null);
    }
  }

  return <>
    <PageHeading title="Catálogo global de especialidades" description="Administración de plataforma. Cada centro elige cuáles especialidades ofrece; aquí no se accede a datos clínicos." />
    {status === "loading" && <p role="status" className="rounded-lg border bg-card p-5">Cargando catálogo global…</p>}
    {status === "error" && <Card><CardContent className="space-y-3 p-5"><p role="alert">{error}</p><Button type="button" variant="outline" onClick={() => { setStatus("loading"); setReload((value) => value + 1); }}>Reintentar</Button></CardContent></Card>}
    {status === "ready" && <div className="grid gap-5">
      {error && <p role="alert" className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</p>}
      <Card><CardHeader><CardTitle>Nueva especialidad global</CardTitle></CardHeader><CardContent><ActionForm onSave={create} className="sm:grid-cols-2" label="Crear especialidad"><Field label="Nombre"><Input name="name" required maxLength={120} /></Field><Field label="Descripción"><Input name="description" maxLength={2000} /></Field></ActionForm></CardContent></Card>
      <Card><CardHeader><CardTitle>Especialidades registradas</CardTitle></CardHeader><CardContent className="grid gap-3">
        {items.length === 0 && <p className="text-sm text-muted-foreground">Todavía no hay especialidades globales.</p>}
        {items.map((item) => <details key={item.id} className="rounded-lg border p-4"><summary className="cursor-pointer font-medium">{item.name} · {item.is_active ? "Activa" : "Inactiva"}</summary><div className="mt-4 grid gap-4"><ActionForm reset={false} onSave={(values) => update(item, values)} className="sm:grid-cols-2" label="Guardar cambios"><Field label="Nombre"><Input name="name" defaultValue={item.name} required maxLength={120} /></Field><Field label="Descripción"><Input name="description" defaultValue={item.description ?? ""} maxLength={2000} /></Field></ActionForm><Button type="button" variant="outline" className="w-fit" disabled={busyId === item.id} onClick={() => void setActive(item)}>{busyId === item.id ? "Guardando…" : item.is_active ? "Desactivar globalmente" : "Reactivar globalmente"}</Button></div></details>)}
      </CardContent></Card>
    </div>}
  </>;
}
