/* eslint-disable react-refresh/only-export-components */

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react";

import { toast } from "sonner";

import { createMockData } from "@/data/mock-data";
import { permissionsFor } from "@/domain/permissions";

import type {
  AppointmentStatus,
  BookingInput,
  ClinicData,
  Organization,
  Patient,
  Slot,
  Role,
  User,
  Professional,
  Membership,
  MedicalResult,
  Permission,
} from "@/domain/types";

import { membershipsFor, scopeData } from "@/domain/access";

import {
  formatRut,
  normalizeRut,
  validatePatient,
  validatePassword,
  hashPassword,
  validEmail,
  validPhone,
} from "@/domain/validation";

import { dateFromToday, toMinutes, toTime, uid } from "@/lib/utils";

import {
  canPatientModifyAppointment,
  canSetAppointmentStatus,
} from "@/domain/appointment-rules";

import { professionalApi, sanctum, type AuthUser } from "@/services/http";

const DATA_KEY = "clinica_horizonte_react_v4";

const ORGANIZATION_KEY = "clinica_horizonte_organization";

const ACTIVE: AppointmentStatus[] = [
  "PENDIENTE",
  "CONFIRMADA",
  "ATENDIDA",
  "NO_SHOW",
];

function loadData(): ClinicData {
  try {
    const value =
      localStorage.getItem(DATA_KEY) ??
      localStorage.getItem("clinica_horizonte_react_v3") ??
      localStorage.getItem("clinica_horizonte_react_v2");

    if (!value) {
      return createMockData();
    }

    const old = JSON.parse(value) as ClinicData;

    const withPlatform = old.users.some((user) => user.role === "SUPER_ADMIN")
      ? old.users
      : [...old.users, createMockData().users[0]];

    const tenantUsers = withPlatform.flatMap((account) => {
      if (account.role === "SUPER_ADMIN") {
        return [account];
      }

      const memberships = membershipsFor(account);

      return memberships.map((membership, index) => ({
        ...account,

        id:
          index === 0
            ? account.id
            : `${account.id}_${membership.organizationId}`,

        role: membership.role,

        organizationIds: [membership.organizationId],

        memberships: [membership],

        patientId: membership.patientId,

        professionalId: membership.professionalId,

        permissions: permissionsFor(membership.role),
      }));
    });

    return {
      ...old,

      organizations: old.organizations.map((organization) => ({
        ...organization,
        subscription: organization.subscription ?? "ACTIVE",
      })),

      users: tenantUsers,

      resultTypes: old.resultTypes ?? [],

      results: old.results ?? [],
    };
  } catch {
    return createMockData();
  }
}

interface ClinicContextValue {
  data: ClinicData;

  user: User | null;

  organization: Organization | null;

  authLoading: boolean;

  login(
    email: string,
    password: string,
    portal?: "CENTER" | "PLATFORM",
    centerId?: string,
  ): Promise<boolean>;

  logout(): Promise<void>;

  reset(): void;

  publicOrganizations: Organization[];

  register(
    input: Omit<Patient, "id" | "active" | "organizationId">,
    password: string,
    confirmation: string,
    centerId: string,
  ): Promise<void>;

  saveOrganization(input: Organization): void;

  saveProfessional(
    input: Omit<Professional, "id" | "organizationId">,
    id?: string,
  ): Promise<void>;

  saveCatalog(
    kind: "specialty" | "service" | "availability",
    input: Record<string, string>,
    id?: string,
  ): void;

  assignAccess(
    email: string,
    name: string,
    membership: Membership,
    password?: string,
  ): Promise<void>;

  addResultType(name: string): void;

  addResult(
    input: Omit<
      MedicalResult,
      "id" | "organizationId" | "createdBy" | "status"
    >,
  ): void;

  publishResult(id: string): void;

  deleteResult(id: string): void;

  slots(
    professionalId: string,
    serviceId: string,
    date: string,
    excludeId?: string,
  ): Slot[];

  createAppointment(input: BookingInput): void;

  changeStatus(id: string, status: AppointmentStatus): void;

  reschedule(id: string, date: string, time: string): void;

  createPatient(input: Omit<Patient, "id" | "active" | "organizationId">): void;

  updateProfile(
    input: Pick<Patient, "name" | "email" | "phone" | "address">,
  ): void;
}

const ClinicContext = createContext<ClinicContextValue | null>(null);

export function ClinicProvider({ children }: { children: ReactNode }) {
  /*
   * Los datos de agenda, profesionales,
   * reservas, etc. siguen siendo mock
   * temporalmente.
   */
  const [allData, setAllData] = useState(loadData);

  /*
   * El usuario autenticado YA NO sale
   * de allData.users.
   *
   * Ahora viene desde Laravel.
   */
  const [user, setUser] = useState<User | null>(null);

  const [organizationId, setOrganizationId] = useState<string | null>(null);

  /*
   * Mientras Laravel responde /api/v1/me
   * evitamos que React redireccione antes
   * de saber si existe una sesión.
   */
  const [authLoading, setAuthLoading] = useState(true);

  const availableOrganizations = allData.organizations.filter(
    (item) =>
      user &&
      user.role !== "SUPER_ADMIN" &&
      membershipsFor(user).some(
        (membership) => membership.organizationId === item.id,
      ) &&
      item.active,
  );

  const organization =
    availableOrganizations.find((item) => item.id === organizationId) ?? null;

  const data = scopeData(allData, user, organization?.id);

  const publicOrganizations = allData.organizations.filter(
    (organization) => organization.active,
  );

  /*
   * Laravel y el frontend utilizan
   * estructuras de User distintas.
   *
   * Esta función transforma el usuario
   * que devuelve Laravel al formato que
   * ya espera el frontend.
   */
  const applyBackendUser = useCallback(
    (backendUser: AuthUser): User => {
      if (!backendUser.role) {
        throw new Error("La cuenta no tiene un rol asignado.");
      }

      const role: Role = backendUser.role;

      /*
       * Buscamos el centro frontend
       * utilizando el slug.
       *
       * Laravel:
       * clinica-horizonte
       *
       * Front:
       * organization con slug
       * clinica-horizonte
       */
      const center = backendUser.medical_center
        ? (allData.organizations.find(
            (item) => item.slug === backendUser.medical_center?.slug,
          ) ?? null)
        : null;

      const patientId =
        backendUser.patient?.id != null
          ? String(backendUser.patient.id)
          : undefined;

      const professionalId =
        backendUser.professional?.id != null
          ? String(backendUser.professional.id)
          : undefined;

      const memberships: Membership[] =
        role !== "SUPER_ADMIN" && center
          ? [
              {
                organizationId: center.id,

                role,

                patientId: role === "PACIENTE" ? patientId : undefined,

                professionalId:
                  role === "PROFESIONAL" ? professionalId : undefined,
              },
            ]
          : [];

      const mappedUser: User = {
        id: String(backendUser.id),

        name: backendUser.name,

        email: backendUser.email,

        role,

        permissions: permissionsFor(role),

        organizationIds: memberships.map(
          (membership) => membership.organizationId,
        ),

        memberships,

        patientId: role === "PACIENTE" ? patientId : undefined,

        professionalId: role === "PROFESIONAL" ? professionalId : undefined,
      };

      setUser(mappedUser);

      const nextOrganizationId = center?.id ?? null;

      setOrganizationId(nextOrganizationId);

      if (nextOrganizationId) {
        sessionStorage.setItem(ORGANIZATION_KEY, nextOrganizationId);
      } else {
        sessionStorage.removeItem(ORGANIZATION_KEY);
      }

      return mappedUser;
    },
    [allData.organizations],
  );

  /*
   * Al abrir o recargar la aplicación
   * preguntamos a Laravel si existe una
   * sesión activa.
   *
   * Esto reemplaza SESSION_KEY del mock.
   */
  useEffect(() => {
    let mounted = true;

    sanctum
      .me()
      .then((response) => {
        if (!mounted) {
          return;
        }

        applyBackendUser(response.data);
      })
      .catch(() => {
        if (!mounted) {
          return;
        }

        setUser(null);

        setOrganizationId(null);

        sessionStorage.removeItem(ORGANIZATION_KEY);
      })
      .finally(() => {
        if (mounted) {
          setAuthLoading(false);
        }
      });

    return () => {
      mounted = false;
    };
  }, [applyBackendUser]);
  useEffect(() => {
    if (authLoading || !user || !organization) {
      return;
    }

    if (user.role !== "ADMIN") {
      return;
    }

    professionalApi
      .list()
      .then((response) => {
        const realProfessionals: Professional[] = response.data.map(
          (professional) => ({
            id: String(professional.id),

            organizationId: organization.id,

            name: `${professional.first_name} ${professional.last_name}`,

            rut: professional.rut,

            email: professional.email,

            phone: professional.phone,

            /*
             * Especialidades todavía siguen
             * temporalmente en el mock.
             */
            specialtyIds: [],

            description: "",

            active: professional.is_active,
          }),
        );

        setAllData((current) => ({
          ...current,

          /*
           * Eliminamos los profesionales mock
           * de este centro.
           *
           * Después agregamos los profesionales
           * reales obtenidos desde PostgreSQL.
           */
          professionals: [
            ...current.professionals.filter(
              (professional) => professional.organizationId !== organization.id,
            ),

            ...realProfessionals,
          ],
        }));
      })
      .catch((error) => {
        console.error("No fue posible cargar los profesionales reales:", error);
      });
  }, [authLoading, user, organization]);

  const commit = useCallback(
    (updater: (current: ClinicData) => ClinicData) => {
      const next = updater(allData);

      try {
        localStorage.setItem(DATA_KEY, JSON.stringify(next));
      } catch {
        throw new Error(
          "No hay espacio en el navegador. Usa un documento más pequeño.",
        );
      }

      setAllData(next);
    },
    [allData],
  );

  const requirePermission = (permission: Permission) => {
    if (
      !user ||
      !organization ||
      user.role === "SUPER_ADMIN" ||
      !user.permissions.includes(permission)
    ) {
      throw new Error(
        "No tienes permiso para realizar esta acción en este centro.",
      );
    }
  };

  /*
   * LOGIN REAL
   *
   * Ya no busca el correo en
   * allData.users.
   *
   * Ahora llama directamente a Laravel.
   */
  const login = async (
    email: string,
    password: string,
    portal: "CENTER" | "PLATFORM" = "CENTER",
    centerId?: string,
  ) => {
    try {
      const response = await sanctum.login(email, password);

      const backendUser = response.data;

      /*
       * Un usuario debe tener un rol.
       */
      if (!backendUser.role) {
        await sanctum.logout();

        return false;
      }

      const isSuperAdmin = backendUser.role === "SUPER_ADMIN";

      /*
       * Si intentan usar el portal
       * SUPER_ADMIN con una cuenta de
       * centro, rechazamos el acceso.
       */
      if (portal === "PLATFORM" && !isSuperAdmin) {
        await sanctum.logout();

        return false;
      }

      /*
       * Un SUPER_ADMIN tampoco debe
       * entrar por el portal del centro.
       */
      if (portal === "CENTER" && isSuperAdmin) {
        await sanctum.logout();

        return false;
      }

      /*
       * Para cuentas de centro
       * comprobamos que el centro de
       * Laravel sea el mismo centro de
       * la URL.
       */
      if (portal === "CENTER") {
        if (!centerId) {
          await sanctum.logout();

          return false;
        }

        const frontendCenter = allData.organizations.find(
          (item) => item.slug === backendUser.medical_center?.slug,
        );

        if (!frontendCenter || frontendCenter.id !== centerId) {
          await sanctum.logout();

          return false;
        }
      }

      /*
       * Guardamos en React el usuario
       * REAL devuelto por Laravel.
       */
      applyBackendUser(backendUser);

      setAuthLoading(false);

      return true;
    } catch (error) {
      console.error("Error en login de Laravel:", error);

      return false;
    }
  };

  /*
   * LOGOUT REAL
   *
   * Laravel destruye la sesión y luego
   * React limpia su usuario local.
   */
  const logout = useCallback(async () => {
    try {
      await sanctum.logout();
    } catch (error) {
      console.warn("No fue posible cerrar la sesión en Laravel:", error);
    } finally {
      setUser(null);

      setOrganizationId(null);

      sessionStorage.removeItem(ORGANIZATION_KEY);
    }
  }, []);

  /*
   * Esto sigue sirviendo solamente
   * para los datos DEMO de módulos que
   * todavía usan mock.
   */
  const reset = () => {
    const next = createMockData();

    localStorage.setItem(DATA_KEY, JSON.stringify(next));

    setAllData(next);

    void logout();

    toast.success("Datos demo restablecidos");
  };

  const slots = (
    professionalId: string,
    serviceId: string,
    date: string,
    excludeId?: string,
  ) => {
    const service = data.services.find((item) => item.id === serviceId);

    if (!service || !date) {
      return [];
    }

    const day = new Date(`${date}T12:00`).getDay();

    return data.availability
      .filter(
        (rule) =>
          rule.active &&
          rule.professionalId === professionalId &&
          rule.weekday === day,
      )
      .flatMap((rule) => {
        const result: Slot[] = [];

        for (
          let start = toMinutes(rule.start);
          start + service.duration <= toMinutes(rule.end);
          start += service.duration
        ) {
          const time = toTime(start);

          const end = toTime(start + service.duration);

          if (
            date === dateFromToday() &&
            new Date(`${date}T${time}:00`) <= new Date()
          ) {
            continue;
          }

          const conflict = allData.appointments.some(
            (appointment) =>
              appointment.organizationId === organization?.id &&
              appointment.id !== excludeId &&
              appointment.professionalId === professionalId &&
              appointment.date === date &&
              ACTIVE.includes(appointment.status) &&
              !appointment.overbook &&
              start < toMinutes(appointment.end) &&
              toMinutes(appointment.time) < start + service.duration,
          );

          if (!conflict) {
            result.push({
              time,
              end,
            });
          }
        }

        return result;
      });
  };

  const createAppointment = (input: BookingInput) => {
    requirePermission("appointments.create");

    if (!user || !organization) {
      return;
    }

    const service = data.services.find((item) => item.id === input.serviceId);

    const patientId =
      user.role === "PACIENTE" ? user.patientId : input.patientId;

    if (
      !service ||
      !patientId ||
      !data.patients.some(
        (patient) => patient.id === patientId && patient.active,
      ) ||
      !data.professionals.some(
        (professional) =>
          professional.id === input.professionalId &&
          professional.active &&
          professional.specialtyIds.includes(input.specialtyId),
      ) ||
      service.specialtyId !== input.specialtyId
    ) {
      throw new Error(
        "Selecciona paciente, profesional y prestación del centro.",
      );
    }

    if (
      input.date < dateFromToday() ||
      !slots(input.professionalId, input.serviceId, input.date).some(
        (slot) => slot.time === input.time,
      )
    ) {
      throw new Error("El horario ya no está disponible.");
    }

    if (input.overbook && user.role !== "RECEPCIONISTA") {
      throw new Error("No puedes crear sobreturnos.");
    }

    commit((current) => {
      const appointment = {
        ...input,

        id: uid("a"),

        organizationId: organization.id,

        patientId,

        end: toTime(toMinutes(input.time) + service.duration),

        status: "PENDIENTE" as const,

        source:
          user.role === "PACIENTE" ? ("WEB" as const) : ("RECEPCION" as const),

        overbook: !!input.overbook,

        createdBy: user.id,
      };

      return {
        ...current,

        appointments: [...current.appointments, appointment],

        history: [
          ...current.history,

          {
            id: uid("h"),

            appointmentId: appointment.id,

            type: "CREACION",

            to: "PENDIENTE",

            newDate: `${input.date} ${input.time}`,

            userId: user.id,

            at: new Date().toISOString(),
          },
        ],
      };
    });

    toast.success("Reserva creada correctamente");
  };

  const changeStatus = (id: string, status: AppointmentStatus) => {
    requirePermission("appointments.update");

    const target = data.appointments.find(
      (appointment) => appointment.id === id,
    );

    if (
      !target ||
      ["CANCELADA", "ATENDIDA"].includes(target.status) ||
      (user?.role === "PACIENTE" &&
        (status !== "CANCELADA" || !canPatientModifyAppointment(target))) ||
      (user?.role === "PROFESIONAL" &&
        !["ATENDIDA", "NO_SHOW"].includes(status))
    ) {
      throw new Error("No puedes realizar este cambio de estado.");
    }

    if (!canSetAppointmentStatus(target, status)) {
      throw new Error(
        status === "NO_SHOW"
          ? "Solo puedes marcar inasistencia después de la hora de término."
          : "Solo puedes marcar una cita como atendida desde su hora de inicio.",
      );
    }

    if (!user || !organization) {
      return;
    }

    commit((current) => {
      const appointment = current.appointments.find(
        (item) => item.id === id && item.organizationId === organization.id,
      );

      if (!appointment) {
        return current;
      }

      return {
        ...current,

        appointments: current.appointments.map((item) =>
          item.id === id
            ? {
                ...item,
                status,
              }
            : item,
        ),

        history: [
          ...current.history,

          {
            id: uid("h"),

            appointmentId: id,

            type: status === "CANCELADA" ? "CANCELACION" : "CAMBIO_ESTADO",

            from: appointment.status,

            to: status,

            userId: user.id,

            at: new Date().toISOString(),
          },
        ],
      };
    });

    toast.success("Estado de la cita actualizado");
  };

  const reschedule = (id: string, date: string, time: string) => {
    requirePermission("appointments.update");

    const target = data.appointments.find(
      (appointment) => appointment.id === id,
    );

    if (
      !target ||
      ["CANCELADA", "ATENDIDA"].includes(target.status) ||
      user?.role === "PROFESIONAL" ||
      (user?.role === "PACIENTE" && !canPatientModifyAppointment(target)) ||
      date < dateFromToday() ||
      !slots(target.professionalId, target.serviceId, date, id).some(
        (slot) => slot.time === time,
      )
    ) {
      throw new Error(
        "La reprogramación no está permitida o el horario no está disponible.",
      );
    }

    if (!user || !organization) {
      return;
    }

    commit((current) => {
      const appointment = current.appointments.find(
        (item) => item.id === id && item.organizationId === organization.id,
      );

      const service = current.services.find(
        (item) => item.id === appointment?.serviceId,
      );

      if (!appointment || !service) {
        return current;
      }

      return {
        ...current,

        appointments: current.appointments.map((item) =>
          item.id === id
            ? {
                ...item,

                date,

                time,

                end: toTime(toMinutes(time) + service.duration),

                status: "PENDIENTE",
              }
            : item,
        ),

        history: [
          ...current.history,

          {
            id: uid("h"),

            appointmentId: id,

            type: "REPROGRAMACION",

            to: "PENDIENTE",

            oldDate: `${appointment.date} ${appointment.time}`,

            newDate: `${date} ${time}`,

            userId: user.id,

            at: new Date().toISOString(),
          },
        ],
      };
    });

    toast.success("Cita reprogramada");
  };

  const createPatient = (
    input: Omit<Patient, "id" | "active" | "organizationId">,
  ) => {
    requirePermission("patients.manage");

    validatePatient(input);

    if (!input.consent) {
      throw new Error("Confirma el consentimiento del paciente.");
    }

    if (!organization) {
      return;
    }

    if (
      data.patients.some(
        (patient) =>
          normalizeRut(patient.rut) === normalizeRut(input.rut) ||
          patient.email.toLowerCase() === input.email.trim().toLowerCase(),
      )
    ) {
      throw new Error(
        "Ya existe un paciente con ese RUT o correo en este centro.",
      );
    }

    const patient = {
      ...input,

      rut: formatRut(input.rut),

      email: input.email.trim().toLowerCase(),

      id: uid("c"),

      organizationId: organization.id,

      active: true,
    };

    commit((current) => ({
      ...current,

      patients: [...current.patients, patient],
    }));

    toast.success("Ficha registrada. El acceso se gestiona por separado.");
  };

  const updateProfile = (
    input: Pick<Patient, "name" | "email" | "phone" | "address">,
  ) => {
    if (!user?.patientId) {
      return;
    }

    if (
      input.name.trim().length < 3 ||
      !validEmail(input.email) ||
      !validPhone(input.phone)
    ) {
      throw new Error("Revisa nombre, correo y teléfono.");
    }

    if (
      allData.users.some(
        (mockUser) =>
          mockUser.id !== user.id &&
          mockUser.email.toLowerCase() === input.email.trim().toLowerCase() &&
          membershipsFor(mockUser).some(
            (membership) => membership.organizationId === organization?.id,
          ),
      )
    ) {
      throw new Error("El correo ya tiene una cuenta en este centro.");
    }

    commit((current) => ({
      ...current,

      patients: current.patients.map((patient) =>
        patient.id === user.patientId
          ? {
              ...patient,
              ...input,
            }
          : patient,
      ),

      users: current.users.map((mockUser) =>
        mockUser.id === user.id
          ? {
              ...mockUser,

              name: input.name,

              email: input.email,
            }
          : mockUser,
      ),
    }));

    toast.success("Datos actualizados");
  };

  /*
   * Este register antiguo se mantiene
   * temporalmente porque ClinicProvider
   * todavía tiene funcionalidades mock.
   *
   * IMPORTANTE:
   * RegisterPage YA NO LLAMA A ESTA
   * FUNCIÓN.
   *
   * Crear cuenta utiliza directamente
   * sanctum.register().
   */
  const register: ClinicContextValue["register"] = async (
    input,
    password,
    confirmation,
    centerId,
  ) => {
    validatePatient(input);

    validatePassword(password, confirmation);

    if (!input.consent) {
      throw new Error(
        "Debes aceptar el tratamiento de los datos para crear tu cuenta.",
      );
    }

    if (
      !publicOrganizations.some((organization) => organization.id === centerId)
    ) {
      throw new Error("Selecciona un centro disponible.");
    }

    const email = input.email.trim().toLowerCase();

    if (
      allData.users.some(
        (mockUser) =>
          mockUser.email.toLowerCase() === email &&
          membershipsFor(mockUser).some(
            (membership) => membership.organizationId === centerId,
          ),
      )
    ) {
      throw new Error(
        "El correo ya tiene una cuenta en este centro. Ingresa con ella.",
      );
    }

    const patientByRut = allData.patients.find(
      (patient) =>
        patient.organizationId === centerId &&
        normalizeRut(patient.rut) === normalizeRut(input.rut),
    );

    const patientByEmail = allData.patients.find(
      (patient) =>
        patient.organizationId === centerId &&
        patient.email.toLowerCase() === email,
    );

    if (
      (patientByRut || patientByEmail) &&
      (!patientByRut || patientByRut.id !== patientByEmail?.id)
    ) {
      throw new Error(
        "El RUT o correo ya está asociado a otra ficha del centro. Contacta a recepción para corregir tus datos.",
      );
    }

    const salt = crypto.randomUUID();

    const passwordHash = await hashPassword(password, salt);

    const patient: Patient = patientByRut ?? {
      ...input,

      name: input.name.trim(),

      email,

      rut: formatRut(input.rut),

      id: uid("c"),

      organizationId: centerId,

      active: true,
    };

    const newUser: User = {
      id: uid("u"),

      name: patient.name,

      email,

      role: "PACIENTE",

      patientId: patient.id,

      organizationIds: [centerId],

      permissions: permissionsFor("PACIENTE"),

      memberships: [
        {
          organizationId: centerId,

          role: "PACIENTE",

          patientId: patient.id,
        },
      ],

      passwordSalt: salt,

      passwordHash,
    };

    commit((current) => ({
      ...current,

      patients: patientByRut
        ? current.patients
        : [...current.patients, patient],

      users: [...current.users, newUser],
    }));
  };

  const saveOrganization = (input: Organization) => {
    if (user?.role !== "SUPER_ADMIN") {
      throw new Error(
        "Solo la administración de plataforma puede gestionar centros.",
      );
    }

    if (
      input.name.trim().length < 3 ||
      !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(input.slug)
    ) {
      throw new Error(
        "Revisa nombre y dirección del centro (letras minúsculas, números y guiones).",
      );
    }

    if (
      allData.organizations.some(
        (organization) =>
          organization.id !== input.id && organization.slug === input.slug,
      )
    ) {
      throw new Error("La dirección del centro ya existe.");
    }

    commit((current) => ({
      ...current,

      organizations: current.organizations.some(
        (organization) => organization.id === input.id,
      )
        ? current.organizations.map((organization) =>
            organization.id === input.id ? input : organization,
          )
        : [
            ...current.organizations,
            {
              ...input,
              id: uid("org"),
            },
          ],
    }));

    toast.success("Centro actualizado");
  };

  const saveProfessional: ClinicContextValue["saveProfessional"] = async (
    input,
    id,
  ) => {
    requirePermission("catalog.manage");

    // Por ahora solo conectamos
    // la creación real.
    if (id) {
      throw new Error(
        "La edición de profesionales todavía no está conectada al backend.",
      );
    }

    if (!organization) {
      throw new Error("No hay un centro médico seleccionado.");
    }

    /*
     * El frontend maneja:
     *
     * name = "Camila Rojas"
     *
     * pero PostgreSQL tiene:
     *
     * first_name
     * last_name
     */
    const nameParts = input.name.trim().split(/\s+/);

    if (nameParts.length < 2) {
      throw new Error("Ingresa nombre y apellido del profesional.");
    }

    const firstName = nameParts.shift() ?? "";

    const lastName = nameParts.join(" ");

    /*
     * ESTA PARTE GUARDA REALMENTE
     * EN POSTGRESQL.
     */
    const response = await professionalApi.create({
      first_name: firstName,

      last_name: lastName,

      rut: input.rut,

      email: input.email.trim().toLowerCase(),

      phone: input.phone,

      is_active: input.active,
    });

    const created = response.data;

    /*
     * Convertimos la respuesta
     * de Laravel al formato que
     * usa actualmente el frontend.
     */
    const professional: Professional = {
      id: String(created.id),

      organizationId: organization.id,

      name: `${created.first_name} ${created.last_name}`,

      rut: created.rut,

      email: created.email,

      phone: created.phone,

      /*
       * Especialidades todavía
       * siguen siendo mock.
       */
      specialtyIds: input.specialtyIds,

      description: input.description,

      active: created.is_active,
    };

    /*
     * Esto actualiza la pantalla.
     *
     * El profesional YA fue guardado
     * en PostgreSQL arriba.
     */
    commit((current) => ({
      ...current,

      professionals: [
        ...current.professionals.filter((item) => item.id !== professional.id),

        professional,
      ],
    }));

    toast.success("Profesional registrado en la base de datos.");
  };

  const saveCatalog: ClinicContextValue["saveCatalog"] = (kind, input, id) => {
    requirePermission("catalog.manage");

    if (kind !== "availability" && user?.role !== "ADMIN") {
      throw new Error(
        "Solo el administrador gestiona especialidades y prestaciones.",
      );
    }

    const base = {
      id: id ?? uid(kind),

      organizationId: organization!.id,

      active: input.active !== "false",
    };

    if (kind === "specialty") {
      if (!input.name?.trim()) {
        throw new Error("Ingresa el nombre.");
      }

      if (id && !data.specialties.some((specialty) => specialty.id === id)) {
        throw new Error("Especialidad fuera del centro.");
      }

      const item = {
        ...base,

        name: input.name.trim(),

        description: input.description ?? "",
      };

      commit((current) => ({
        ...current,

        specialties: id
          ? current.specialties.map((specialty) =>
              specialty.id === id ? item : specialty,
            )
          : [...current.specialties, item],
      }));
    } else if (kind === "service") {
      if (
        !input.name?.trim() ||
        !data.specialties.some(
          (specialty) => specialty.id === input.specialtyId,
        ) ||
        !Number.isInteger(Number(input.duration)) ||
        Number(input.duration) < 5 ||
        Number(input.duration) > 480
      ) {
        throw new Error(
          "Revisa nombre, especialidad y duración (5 a 480 minutos).",
        );
      }

      if (id && !data.services.some((service) => service.id === id)) {
        throw new Error("Prestación fuera del centro.");
      }

      const item = {
        ...base,

        name: input.name.trim(),

        specialtyId: input.specialtyId,

        duration: Number(input.duration),
      };

      commit((current) => ({
        ...current,

        services: id
          ? current.services.map((service) =>
              service.id === id ? item : service,
            )
          : [...current.services, item],
      }));
    } else {
      if (
        !data.professionals.some(
          (professional) => professional.id === input.professionalId,
        ) ||
        !/^[0-6]$/.test(input.weekday) ||
        !/^([01]\d|2[0-3]):[0-5]\d$/.test(input.start) ||
        !/^([01]\d|2[0-3]):[0-5]\d$/.test(input.end) ||
        input.start >= input.end
      ) {
        throw new Error("Revisa profesional, día y horas de inicio y término.");
      }

      if (
        id &&
        !data.availability.some((availability) => availability.id === id)
      ) {
        throw new Error("Horario fuera del centro.");
      }

      if (
        base.active &&
        data.availability.some(
          (availability) =>
            availability.id !== id &&
            availability.active &&
            availability.professionalId === input.professionalId &&
            availability.weekday === Number(input.weekday) &&
            input.start < availability.end &&
            availability.start < input.end,
        )
      ) {
        throw new Error("Este horario se superpone con otro del profesional.");
      }

      const item = {
        ...base,

        professionalId: input.professionalId,

        weekday: Number(input.weekday),

        start: input.start,

        end: input.end,
      };

      commit((current) => ({
        ...current,

        availability: id
          ? current.availability.map((availability) =>
              availability.id === id ? item : availability,
            )
          : [...current.availability, item],
      }));
    }

    toast.success("Configuración guardada");
  };

  const assignAccess: ClinicContextValue["assignAccess"] = async (
    emailValue,
    name,
    membership,
    password,
  ) => {
    const platform = user?.role === "SUPER_ADMIN";

    if (!platform) {
      requirePermission("users.manage");
    }

    if (
      !allData.organizations.some(
        (organization) => organization.id === membership.organizationId,
      ) ||
      (platform
        ? membership.role !== "ADMIN"
        : membership.organizationId !== organization?.id)
    ) {
      throw new Error("El acceso solicitado no está permitido.");
    }

    const email = emailValue.trim().toLowerCase();

    if (!validEmail(email) || name.trim().length < 3) {
      throw new Error("Revisa nombre y correo.");
    }

    const existing = allData.users.find(
      (mockUser) =>
        mockUser.email.toLowerCase() === email &&
        membershipsFor(mockUser).some(
          (existingMembership) =>
            existingMembership.organizationId === membership.organizationId,
        ),
    );

    if (existing?.role === "SUPER_ADMIN") {
      throw new Error("La cuenta de plataforma no puede operar en centros.");
    }

    if (
      existing &&
      membership.role !== "ADMIN" &&
      membershipsFor(existing).some(
        (existingMembership) =>
          existingMembership.organizationId === membership.organizationId &&
          existingMembership.role === "ADMIN",
      ) &&
      !allData.users.some(
        (mockUser) =>
          mockUser.id !== existing.id &&
          membershipsFor(mockUser).some(
            (existingMembership) =>
              existingMembership.organizationId === membership.organizationId &&
              existingMembership.role === "ADMIN",
          ),
      )
    ) {
      throw new Error(
        "Asigna otro administrador antes de cambiar el rol del último administrador del centro.",
      );
    }

    if (
      allData.users.some(
        (mockUser) =>
          mockUser.id !== existing?.id &&
          membershipsFor(mockUser).some(
            (existingMembership) =>
              existingMembership.organizationId === membership.organizationId &&
              ((membership.role === "PACIENTE" &&
                existingMembership.patientId === membership.patientId) ||
                (membership.role === "PROFESIONAL" &&
                  existingMembership.professionalId ===
                    membership.professionalId)),
          ),
      )
    ) {
      throw new Error("La ficha ya está vinculada a otra cuenta.");
    }

    if (
      membership.role === "PACIENTE" &&
      !allData.patients.some(
        (patient) =>
          patient.organizationId === membership.organizationId &&
          patient.id === membership.patientId &&
          patient.email.toLowerCase() === email,
      )
    ) {
      throw new Error("Selecciona una ficha de paciente con el mismo correo.");
    }

    if (
      membership.role === "PROFESIONAL" &&
      !allData.professionals.some(
        (professional) =>
          professional.organizationId === membership.organizationId &&
          professional.id === membership.professionalId &&
          professional.email.toLowerCase() === email,
      )
    ) {
      throw new Error("Selecciona una ficha profesional con el mismo correo.");
    }

    const clean: Membership = {
      organizationId: membership.organizationId,

      role: membership.role,

      patientId:
        membership.role === "PACIENTE" ? membership.patientId : undefined,

      professionalId:
        membership.role === "PROFESIONAL"
          ? membership.professionalId
          : undefined,

      extraPermissions:
        membership.role === "RECEPCIONISTA" &&
        membership.extraPermissions?.includes("results.upload")
          ? ["results.upload"]
          : [],
    };

    let credential = {};

    if (!existing) {
      validatePassword(password ?? "", password ?? "");

      const passwordSalt = crypto.randomUUID();

      credential = {
        passwordSalt,

        passwordHash: await hashPassword(password!, passwordSalt),
      };
    }

    const memberships = [
      ...(existing
        ? membershipsFor(existing).filter(
            (existingMembership) =>
              existingMembership.organizationId !== clean.organizationId,
          )
        : []),

      clean,
    ];

    const next: User = {
      ...(existing ?? {
        id: uid("u"),

        name: name.trim(),

        email,

        role: clean.role,

        permissions: permissionsFor(clean.role),
      }),

      ...credential,

      memberships,

      organizationIds: memberships.map((item) => item.organizationId),
    };

    commit((current) => ({
      ...current,

      users: existing
        ? current.users.map((mockUser) =>
            mockUser.id === existing.id ? next : mockUser,
          )
        : [...current.users, next],
    }));

    toast.success("Acceso del centro guardado");
  };

  const addResultType = (name: string) => {
    requirePermission("results.types");

    if (
      !name.trim() ||
      data.resultTypes.some(
        (type) => type.name.toLowerCase() === name.trim().toLowerCase(),
      )
    ) {
      throw new Error("Ingresa un nombre de tipo nuevo.");
    }

    commit((current) => ({
      ...current,

      resultTypes: [
        ...current.resultTypes,

        {
          id: uid("rt"),

          organizationId: organization!.id,

          name: name.trim(),
        },
      ],
    }));
  };

  const addResult: ClinicContextValue["addResult"] = (input) => {
    requirePermission("results.upload");

    if (
      !data.patients.some((patient) => patient.id === input.patientId) ||
      !data.professionals.some(
        (professional) => professional.id === input.professionalId,
      ) ||
      !data.resultTypes.some((type) => type.id === input.typeId) ||
      (user?.role === "PROFESIONAL" &&
        input.professionalId !== user.professionalId)
    ) {
      throw new Error("Selecciona paciente, responsable y tipo del centro.");
    }

    if (
      input.appointmentId &&
      !data.appointments.some(
        (appointment) =>
          appointment.id === input.appointmentId &&
          appointment.patientId === input.patientId &&
          appointment.professionalId === input.professionalId,
      )
    ) {
      throw new Error("La atención no corresponde al paciente y profesional.");
    }

    if (
      !input.performedAt ||
      input.performedAt > dateFromToday() ||
      !input.filename ||
      !/^data:(application\/pdf|image\/png|image\/jpeg|text\/plain)[;,]/.test(
        input.content,
      ) ||
      input.content.length > 750000
    ) {
      throw new Error(
        "Revisa la fecha y el documento (PDF, PNG, JPEG o TXT; hasta 500 KB).",
      );
    }

    commit((current) => ({
      ...current,

      results: [
        ...current.results,

        {
          ...input,

          id: uid("r"),

          organizationId: organization!.id,

          createdBy: user!.id,

          status: "DRAFT",

          publishedAt: undefined,
        },
      ],
    }));

    toast.success("Borrador cargado. Pendiente de publicación profesional.");
  };

  const publishResult = (id: string) => {
    requirePermission("results.publish");

    if (
      !data.results.some(
        (result) =>
          result.id === id &&
          result.professionalId === user?.professionalId &&
          result.status === "DRAFT",
      )
    ) {
      throw new Error(
        "Solo el profesional responsable puede publicar este borrador.",
      );
    }

    commit((current) => ({
      ...current,

      results: current.results.map((result) =>
        result.id === id
          ? {
              ...result,

              status: "PUBLISHED",

              publishedAt: new Date().toISOString(),
            }
          : result,
      ),
    }));

    toast.success("Resultado publicado para el paciente");
  };

  const deleteResult = (id: string) => {
    requirePermission("results.publish");

    if (
      user?.role !== "PROFESIONAL" ||
      !data.results.some(
        (result) =>
          result.id === id && result.professionalId === user.professionalId,
      )
    ) {
      throw new Error(
        "Solo el profesional responsable puede eliminar este informe.",
      );
    }

    commit((current) => ({
      ...current,

      results: current.results.filter((result) => result.id !== id),
    }));

    toast.success("Informe eliminado");
  };

  const value = {
    data,

    user,

    organization,

    authLoading,

    publicOrganizations,

    register,

    saveOrganization,

    saveProfessional,

    saveCatalog,

    assignAccess,

    addResultType,

    addResult,

    publishResult,

    deleteResult,

    login,

    logout,

    reset,

    slots,

    createAppointment,

    changeStatus,

    reschedule,

    createPatient,

    updateProfile,
  };

  return (
    <ClinicContext.Provider value={value}>{children}</ClinicContext.Provider>
  );
}

export function useClinic() {
  const context = useContext(ClinicContext);

  if (!context) {
    throw new Error("useClinic debe usarse dentro de ClinicProvider");
  }

  return context;
}
