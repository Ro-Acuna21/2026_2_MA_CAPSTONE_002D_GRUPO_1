import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  specialtyCatalogApi,
  specialtyCatalogError,
  type CenterCatalogSpecialty,
} from "@/services/specialty-catalog-api";

export function CenterSpecialtyCatalog() {
  const [items, setItems] = useState<CenterCatalogSpecialty[]>([]);
  const [status, setStatus] = useState<"loading" | "ready" | "error">("loading");
  const [error, setError] = useState("");
  const [reload, setReload] = useState(0);
  const [busyId, setBusyId] = useState<number | null>(null);

  useEffect(() => {
    let cancelled = false;
    specialtyCatalogApi.centerList()
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
  }, [reload]);

  async function setCenterActive(item: CenterCatalogSpecialty, active: boolean) {
    setBusyId(item.id);
    setError("");
    try {
      const response = await specialtyCatalogApi.centerSetActive(item.id, active);
      setItems((current) => current.map((entry) => entry.id === item.id ? response.data : entry));
      window.dispatchEvent(new Event("medsync:specialty-catalog-updated"));
    } catch (failure) {
      setError(specialtyCatalogError(failure));
    } finally {
      setBusyId(null);
    }
  }

  return <Card>
    <CardHeader><CardTitle>Especialidades que ofrece el centro</CardTitle><p className="text-sm text-muted-foreground">Selecciona especialidades del catálogo global. La desactivación de un centro no modifica el catálogo de MedSync ni la selección de otros centros.</p></CardHeader>
    <CardContent>
      {status === "loading" && <p role="status" className="text-sm text-muted-foreground">Cargando catálogo global…</p>}
      {status === "error" && <div className="space-y-3"><p role="alert" className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{error}</p><Button type="button" variant="outline" onClick={() => { setStatus("loading"); setReload((value) => value + 1); }}>Reintentar</Button></div>}
      {status === "ready" && <div className="grid gap-3">
        {error && <p role="alert" className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{error}</p>}
        {items.length === 0 && <p className="text-sm text-muted-foreground">Aún no hay especialidades en el catálogo global.</p>}
        {items.map((item) => <div key={item.id} className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="min-w-0"><p className="font-medium">{item.name}</p><p className="text-sm text-muted-foreground">{item.description || "Sin descripción"} · {item.is_active ? item.center_is_active === true ? "Activa en este centro" : item.center_is_active === false ? "Inactiva en este centro" : "No seleccionada" : "Inactiva en el catálogo global"}</p></div>
          <Button type="button" className="w-full shrink-0 sm:w-auto" variant="outline" disabled={!item.is_active || busyId === item.id} onClick={() => void setCenterActive(item, item.center_is_active !== true)}>{busyId === item.id ? "Guardando…" : item.center_is_active === true ? "Desactivar en mi centro" : item.center_is_active === false ? "Reactivar en mi centro" : "Ofrecer en mi centro"}</Button>
        </div>)}
      </div>}
    </CardContent>
  </Card>;
}
