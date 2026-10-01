export type PublicCenter = { slug: string; name: string; description: string; address: string; location: string; phone: string; hours: string; specialties: string[]; services: string[] };

export const publicCenters: PublicCenter[] = [
  { slug: "clinica-horizonte", name: "Clínica Horizonte", description: "Atención cercana, coordinada y centrada en las personas, con especialidades para acompañarte en cada etapa.", address: "Av. Horizonte 123, Santiago", location: "Santiago Centro", phone: "+56 2 2345 6789", hours: "Lunes a viernes · 08:00 a 19:00", specialties: ["Medicina general", "Cardiología", "Kinesiología"], services: ["Consulta médica", "Controles", "Reserva de horas online"] },
  { slug: "centro-medico-alameda", name: "Centro Médico Alameda", description: "Servicios ambulatorios para una atención simple y oportuna, cerca de quienes transitan por el centro de Santiago.", address: "Alameda 850, Santiago", location: "Estación Central", phone: "+56 2 2456 7890", hours: "Lunes a sábado · 08:30 a 18:30", specialties: ["Medicina familiar", "Pediatría", "Nutrición"], services: ["Consulta médica", "Atención preventiva", "Teleorientación"] },
];
