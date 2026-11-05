--
-- PostgreSQL database dump
--

\restrict 6DZCfBmYZhA7ehVTxj69XDpD2WqHWhTw3TdXy4R8Lud1k5rxndVYYgSAEhfGoyT

-- Dumped from database version 18.4
-- Dumped by pg_dump version 18.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: appointment_history; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.appointment_history (
    id bigint NOT NULL,
    appointment_id bigint NOT NULL,
    actor_user_id bigint,
    event_type character varying(30) NOT NULL,
    previous_status character varying(20),
    new_status character varying(20),
    old_date date,
    old_start_time time(0) without time zone,
    old_end_time time(0) without time zone,
    new_date date,
    new_start_time time(0) without time zone,
    new_end_time time(0) without time zone,
    old_professional_id bigint,
    new_professional_id bigint,
    old_service_id bigint,
    new_service_id bigint,
    reason text,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT appointment_history_new_status CHECK (((new_status IS NULL) OR ((new_status)::text = ANY ((ARRAY['PENDIENTE'::character varying, 'CONFIRMADA'::character varying, 'ATENDIDA'::character varying, 'CANCELADA'::character varying, 'NO_SHOW'::character varying])::text[])))),
    CONSTRAINT appointment_history_previous_status CHECK (((previous_status IS NULL) OR ((previous_status)::text = ANY ((ARRAY['PENDIENTE'::character varying, 'CONFIRMADA'::character varying, 'ATENDIDA'::character varying, 'CANCELADA'::character varying, 'NO_SHOW'::character varying])::text[])))),
    CONSTRAINT appointment_history_valid_event CHECK (((event_type)::text = ANY ((ARRAY['CREACION'::character varying, 'CAMBIO_ESTADO'::character varying, 'REPROGRAMACION'::character varying, 'REASIGNACION'::character varying, 'MODIFICACION'::character varying, 'CANCELACION'::character varying])::text[])))
);


ALTER TABLE public.appointment_history OWNER TO postgres;

--
-- Name: appointment_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.appointment_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.appointment_history_id_seq OWNER TO postgres;

--
-- Name: appointment_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.appointment_history_id_seq OWNED BY public.appointment_history.id;


--
-- Name: appointments; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.appointments (
    id bigint NOT NULL,
    patient_id bigint NOT NULL,
    professional_id bigint NOT NULL,
    service_id bigint NOT NULL,
    appointment_date date NOT NULL,
    start_time time(0) without time zone NOT NULL,
    end_time time(0) without time zone NOT NULL,
    status character varying(20) DEFAULT 'PENDIENTE'::character varying NOT NULL,
    source character varying(20) NOT NULL,
    note text,
    overbook boolean DEFAULT false NOT NULL,
    created_by bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT appointments_valid_source CHECK (((source)::text = ANY ((ARRAY['WEB'::character varying, 'RECEPCION'::character varying, 'DEMO'::character varying])::text[]))),
    CONSTRAINT appointments_valid_status CHECK (((status)::text = ANY ((ARRAY['PENDIENTE'::character varying, 'CONFIRMADA'::character varying, 'ATENDIDA'::character varying, 'CANCELADA'::character varying, 'NO_SHOW'::character varying])::text[]))),
    CONSTRAINT appointments_valid_time CHECK ((start_time < end_time))
);


ALTER TABLE public.appointments OWNER TO postgres;

--
-- Name: appointments_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.appointments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.appointments_id_seq OWNER TO postgres;

--
-- Name: appointments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.appointments_id_seq OWNED BY public.appointments.id;


--
-- Name: availabilities; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.availabilities (
    id bigint NOT NULL,
    professional_id bigint NOT NULL,
    weekday smallint NOT NULL,
    start_time time(0) without time zone NOT NULL,
    end_time time(0) without time zone NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT availabilities_valid_time CHECK ((start_time < end_time)),
    CONSTRAINT availabilities_valid_weekday CHECK (((weekday >= 1) AND (weekday <= 7)))
);


ALTER TABLE public.availabilities OWNER TO postgres;

--
-- Name: availabilities_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.availabilities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.availabilities_id_seq OWNER TO postgres;

--
-- Name: availabilities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.availabilities_id_seq OWNED BY public.availabilities.id;


--
-- Name: health_insurances; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.health_insurances (
    id bigint NOT NULL,
    name character varying(100) NOT NULL,
    type character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT health_insurances_type_check CHECK (((type)::text = ANY ((ARRAY['FONASA'::character varying, 'ISAPRE'::character varying, 'PARTICULAR'::character varying, 'OTHER'::character varying])::text[])))
);


ALTER TABLE public.health_insurances OWNER TO postgres;

--
-- Name: health_insurances_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.health_insurances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.health_insurances_id_seq OWNER TO postgres;

--
-- Name: health_insurances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.health_insurances_id_seq OWNED BY public.health_insurances.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: patient_addresses; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.patient_addresses (
    id bigint NOT NULL,
    patient_id bigint NOT NULL,
    address_line character varying(200) NOT NULL,
    commune character varying(100),
    region character varying(100),
    postal_code character varying(20),
    reference character varying(200),
    is_primary boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.patient_addresses OWNER TO postgres;

--
-- Name: patient_addresses_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.patient_addresses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.patient_addresses_id_seq OWNER TO postgres;

--
-- Name: patient_addresses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.patient_addresses_id_seq OWNED BY public.patient_addresses.id;


--
-- Name: patients; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.patients (
    id bigint NOT NULL,
    user_id bigint,
    health_insurance_id bigint,
    first_name character varying(100) NOT NULL,
    last_name character varying(100) NOT NULL,
    rut character varying(12) NOT NULL,
    birth_date date,
    email character varying(150),
    phone character varying(20),
    medical_insurance character varying(100),
    consent_at timestamp(0) without time zone,
    consent_version character varying(20),
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.patients OWNER TO postgres;

--
-- Name: patients_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.patients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.patients_id_seq OWNER TO postgres;

--
-- Name: patients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.patients_id_seq OWNED BY public.patients.id;


--
-- Name: professional_specialty; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.professional_specialty (
    professional_id bigint NOT NULL,
    specialty_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.professional_specialty OWNER TO postgres;

--
-- Name: professionals; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.professionals (
    id bigint NOT NULL,
    user_id bigint,
    first_name character varying(100) NOT NULL,
    last_name character varying(100) NOT NULL,
    rut character varying(12) NOT NULL,
    email character varying(150),
    phone character varying(20),
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    description text
);


ALTER TABLE public.professionals OWNER TO postgres;

--
-- Name: professionals_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.professionals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.professionals_id_seq OWNER TO postgres;

--
-- Name: professionals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.professionals_id_seq OWNED BY public.professionals.id;


--
-- Name: services; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.services (
    id bigint NOT NULL,
    specialty_id bigint NOT NULL,
    name character varying(120) NOT NULL,
    description text,
    duration_minutes smallint NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT services_duration_positive CHECK ((duration_minutes > 0)),
    CONSTRAINT services_name_not_blank CHECK ((btrim((name)::text) <> ''::text))
);


ALTER TABLE public.services OWNER TO postgres;

--
-- Name: services_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.services_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.services_id_seq OWNER TO postgres;

--
-- Name: services_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.services_id_seq OWNED BY public.services.id;


--
-- Name: specialties; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.specialties (
    id bigint NOT NULL,
    name character varying(120) NOT NULL,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT specialties_name_not_blank CHECK ((btrim((name)::text) <> ''::text))
);


ALTER TABLE public.specialties OWNER TO postgres;

--
-- Name: specialties_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.specialties_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.specialties_id_seq OWNER TO postgres;

--
-- Name: specialties_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.specialties_id_seq OWNED BY public.specialties.id;


--
-- Name: appointment_history id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointment_history ALTER COLUMN id SET DEFAULT nextval('public.appointment_history_id_seq'::regclass);


--
-- Name: appointments id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointments ALTER COLUMN id SET DEFAULT nextval('public.appointments_id_seq'::regclass);


--
-- Name: availabilities id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.availabilities ALTER COLUMN id SET DEFAULT nextval('public.availabilities_id_seq'::regclass);


--
-- Name: health_insurances id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.health_insurances ALTER COLUMN id SET DEFAULT nextval('public.health_insurances_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: patient_addresses id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.patient_addresses ALTER COLUMN id SET DEFAULT nextval('public.patient_addresses_id_seq'::regclass);


--
-- Name: patients id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.patients ALTER COLUMN id SET DEFAULT nextval('public.patients_id_seq'::regclass);


--
-- Name: professionals id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.professionals ALTER COLUMN id SET DEFAULT nextval('public.professionals_id_seq'::regclass);


--
-- Name: services id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.services ALTER COLUMN id SET DEFAULT nextval('public.services_id_seq'::regclass);


--
-- Name: specialties id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.specialties ALTER COLUMN id SET DEFAULT nextval('public.specialties_id_seq'::regclass);


--
-- Data for Name: appointment_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.appointment_history (id, appointment_id, actor_user_id, event_type, previous_status, new_status, old_date, old_start_time, old_end_time, new_date, new_start_time, new_end_time, old_professional_id, new_professional_id, old_service_id, new_service_id, reason, created_at) FROM stdin;
1	1	1	CREACION	\N	PENDIENTE	\N	\N	\N	2026-10-10	10:00:00	10:30:00	\N	1	\N	1	Creación de prueba.	2026-09-25 17:17:29
2	1	1	REPROGRAMACION	\N	\N	2026-10-10	10:00:00	10:30:00	2026-10-11	11:00:00	11:30:00	\N	\N	\N	\N	Prueba de reprogramación.	2026-09-25 17:20:56
3	1	1	CANCELACION	PENDIENTE	CANCELADA	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	Cancelación realizada como prueba.	2026-09-25 17:23:14
46	26	15	CREACION	\N	PENDIENTE	\N	\N	\N	2026-10-13	09:00:00	09:30:00	\N	2	\N	1	\N	2026-10-09 21:53:59
47	26	15	CAMBIO_ESTADO	PENDIENTE	CONFIRMADA	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	2026-10-09 21:54:40
51	28	15	CREACION	\N	PENDIENTE	\N	\N	\N	2026-10-12	09:00:00	09:30:00	\N	3	\N	8	\N	2026-10-09 22:28:16
52	28	15	CAMBIO_ESTADO	PENDIENTE	CONFIRMADA	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	2026-10-09 22:28:33
53	28	15	REASIGNACION	\N	\N	2026-10-12	09:00:00	09:30:00	2026-10-14	17:00:00	17:30:00	3	14	\N	\N	Profesional no disponible	2026-10-09 22:29:11
54	28	15	CANCELACION	CONFIRMADA	CANCELADA	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	2026-10-09 22:30:14
60	30	15	CREACION	\N	PENDIENTE	\N	\N	\N	2026-10-14	12:00:00	12:30:00	\N	11	\N	4	\N	2026-10-13 16:26:05
61	26	15	CAMBIO_ESTADO	CONFIRMADA	NO_SHOW	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	2026-10-13 16:27:16
62	30	15	CAMBIO_ESTADO	PENDIENTE	NO_SHOW	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	2026-10-14 16:16:01
66	32	15	CREACION	\N	PENDIENTE	\N	\N	\N	2026-10-19	09:30:00	10:00:00	\N	16	\N	4	\N	2026-10-14 22:54:34
\.


--
-- Data for Name: appointments; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.appointments (id, patient_id, professional_id, service_id, appointment_date, start_time, end_time, status, source, note, overbook, created_by, created_at, updated_at) FROM stdin;
32	2	16	4	2026-10-19	09:30:00	10:00:00	PENDIENTE	RECEPCION	\N	f	15	2026-10-14 22:54:34	2026-10-14 22:54:34
1	1	1	1	2026-10-11	11:00:00	11:30:00	CANCELADA	DEMO	Reserva de prueba para validar el modelo.	f	1	2026-09-25 17:16:05	2026-09-25 17:23:14
16	1	1	5	2026-10-12	09:00:00	09:30:00	CONFIRMADA	RECEPCION	\N	f	15	2026-10-07 19:52:03	2026-10-07 19:52:03
17	2	1	1	2026-10-12	10:00:00	10:30:00	CONFIRMADA	RECEPCION	\N	f	15	2026-10-07 19:52:03	2026-10-07 19:52:03
18	3	3	8	2026-10-12	11:00:00	11:30:00	CONFIRMADA	RECEPCION	\N	f	15	2026-10-07 19:52:03	2026-10-07 19:52:03
28	1	14	8	2026-10-14	17:00:00	17:30:00	CANCELADA	RECEPCION	\N	f	15	2026-10-09 22:28:16	2026-10-09 22:30:14
26	5	2	1	2026-10-13	09:00:00	09:30:00	NO_SHOW	RECEPCION	\N	f	15	2026-10-09 21:53:59	2026-10-13 16:27:16
30	5	11	4	2026-10-14	12:00:00	12:30:00	NO_SHOW	RECEPCION	\N	f	15	2026-10-13 16:26:05	2026-10-14 16:16:01
\.


--
-- Data for Name: availabilities; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.availabilities (id, professional_id, weekday, start_time, end_time, is_active, created_at, updated_at) FROM stdin;
1	1	1	09:00:00	13:00:00	t	2026-09-25 17:49:29	2026-09-25 17:49:29
2	1	1	14:00:00	18:00:00	t	2026-09-25 17:49:56	2026-09-25 17:49:56
9	1	2	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
10	1	2	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
11	1	3	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
12	1	3	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
13	1	4	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
14	1	4	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
15	1	5	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
16	1	5	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
17	10	1	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
18	10	1	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
19	10	2	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
20	10	2	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
21	10	3	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
22	10	3	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
23	10	4	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
24	10	4	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
25	10	5	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
26	10	5	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
27	11	1	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
28	11	1	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
29	11	2	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
30	11	2	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
31	11	3	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
32	11	3	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
33	11	4	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
34	11	4	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
35	11	5	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
36	11	5	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
37	2	1	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
38	2	1	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
39	2	2	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
40	2	2	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
41	2	3	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
42	2	3	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
43	2	4	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
44	2	4	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
45	2	5	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
46	2	5	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
47	12	1	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
48	12	1	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
49	12	2	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
50	12	2	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
51	12	3	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
52	12	3	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
53	12	4	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
54	12	4	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
55	12	5	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
56	12	5	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
57	13	1	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
58	13	1	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
59	13	2	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
60	13	2	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
61	13	3	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
62	13	3	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
63	13	4	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
64	13	4	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
65	13	5	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
66	13	5	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
67	3	1	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
68	3	1	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
69	3	2	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
70	3	2	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
71	3	3	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
72	3	3	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
73	3	4	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
74	3	4	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
75	3	5	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
76	3	5	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
77	14	1	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
78	14	1	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
79	14	2	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
80	14	2	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
81	14	3	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
82	14	3	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
83	14	4	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
84	14	4	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
85	14	5	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
86	14	5	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
87	15	1	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
88	15	1	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
89	15	2	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
90	15	2	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
91	15	3	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
92	15	3	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
93	15	4	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
94	15	4	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
95	15	5	09:00:00	13:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
96	15	5	14:00:00	18:00:00	t	2026-10-07 19:52:02	2026-10-07 19:52:02
97	16	1	09:00:00	18:00:00	t	2026-10-14 19:53:08	2026-10-14 19:53:08
98	16	3	09:00:00	18:00:00	t	2026-10-14 19:53:08	2026-10-14 19:53:08
\.


--
-- Data for Name: health_insurances; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.health_insurances (id, name, type, is_active, created_at, updated_at) FROM stdin;
1	Fonasa	FONASA	t	2026-09-22 21:13:24	2026-09-22 21:13:24
2	Isapre	ISAPRE	t	2026-09-22 21:13:24	2026-09-22 21:13:24
3	Particular	PARTICULAR	t	2026-09-22 21:13:24	2026-09-22 21:13:24
4	Otra	OTHER	t	2026-09-22 21:13:24	2026-09-22 21:13:24
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2026_09_06_225040_create_health_insurances_table	1
2	2026_09_06_225045_create_patients_table	1
3	2026_09_12_212901_create_professionals_table	1
4	2026_09_13_010619_create_patient_addresses_table	1
5	2026_09_25_152349_create_specialties_table	2
6	2026_09_25_152503_create_professional_specialty_table	2
7	2026_09_25_165308_create_services_table	3
8	2026_09_25_170008_create_appointments_table	4
9	2026_09_25_170047_create_appointment_history_table	4
10	2026_09_25_174713_create_availabilities_table	5
11	2026_10_06_000001_add_reassignment_event_to_appointment_history_table	6
12	2026_10_10_000001_add_description_to_professionals_table	7
\.


--
-- Data for Name: patient_addresses; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.patient_addresses (id, patient_id, address_line, commune, region, postal_code, reference, is_primary, created_at, updated_at, deleted_at) FROM stdin;
1	1	Av. Paciente 111	Maipú	Región Metropolitana	\N	\N	t	2026-09-22 21:13:25	2026-09-22 21:13:25	\N
2	2	Av. Paciente 222	Maipú	Región Metropolitana	\N	\N	t	2026-09-22 21:13:25	2026-09-22 21:13:25	\N
3	3	Av. Paciente 333	Maipú	Región Metropolitana	\N	\N	t	2026-09-22 21:13:25	2026-09-22 21:13:25	\N
\.


--
-- Data for Name: patients; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.patients (id, user_id, health_insurance_id, first_name, last_name, rut, birth_date, email, phone, medical_insurance, consent_at, consent_version, is_active, created_at, updated_at, deleted_at) FROM stdin;
1	3	1	Paciente	Prueba A	11111111-1	2000-01-10	paciente.a@medsync.test	+56911111111	\N	2026-09-22 21:13:25	v1	t	2026-09-22 21:13:25	2026-09-22 21:13:25	\N
2	4	1	Paciente	Prueba B	22222222-2	1998-05-15	paciente.b@medsync.test	+56922222222	\N	2026-09-22 21:13:25	v1	t	2026-09-22 21:13:25	2026-09-22 21:13:25	\N
3	5	1	Paciente	Prueba C	33333333-3	1995-09-20	paciente.c@medsync.test	+56933333333	\N	2026-09-22 21:13:25	v1	t	2026-09-22 21:13:25	2026-09-22 21:13:25	\N
5	7	2	Hugo	Ruiz	20906423-5	2001-10-26	hugo@duocuc.cl	923323874	\N	2026-09-24 17:38:11	v1	t	2026-09-24 17:38:11	2026-09-24 17:38:11	\N
\.


--
-- Data for Name: professional_specialty; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.professional_specialty (professional_id, specialty_id, created_at, updated_at) FROM stdin;
1	1	2026-09-25 15:42:32	2026-09-25 15:42:32
1	4	2026-09-25 15:46:14	2026-09-25 15:46:14
1	5	2026-10-07 19:52:02	2026-10-07 19:52:02
2	1	2026-10-07 19:52:02	2026-10-07 19:52:02
13	1	2026-10-07 19:52:02	2026-10-07 19:52:02
3	6	2026-10-07 19:52:02	2026-10-07 19:52:02
14	6	2026-10-07 19:52:02	2026-10-07 19:52:02
15	6	2026-10-07 19:52:02	2026-10-07 19:52:02
10	1	2026-10-10 21:22:13	2026-10-10 21:22:13
11	4	2026-10-10 21:26:10	2026-10-10 21:26:10
12	5	2026-10-10 22:50:15	2026-10-10 22:50:15
16	4	2026-10-10 23:24:16	2026-10-10 23:24:16
\.


--
-- Data for Name: professionals; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.professionals (id, user_id, first_name, last_name, rut, email, phone, is_active, created_at, updated_at, deleted_at, description) FROM stdin;
1	\N	Ana	Profesional A	44444444-4	profesional.a@medsync.test	+56944444444	t	2026-09-22 21:13:25	2026-09-22 21:13:25	\N	\N
2	\N	Bruno	Profesional B	55555555-5	profesional.b@medsync.test	+56955555555	t	2026-09-22 21:13:25	2026-09-22 21:13:25	\N	\N
3	\N	Carla	Profesional C	66666666-6	profesional.c@medsync.test	+56966666666	t	2026-09-22 21:13:25	2026-09-22 21:13:25	\N	\N
13	\N	Helena	Cardióloga	85555555-5	helena.cardio@medsync.test	+56985555555	t	2026-10-07 19:52:02	2026-10-07 19:52:02	\N	\N
14	\N	Ignacio	Pediatra	76666666-6	ignacio.pediatria@medsync.test	+56976666666	t	2026-10-07 19:52:02	2026-10-07 19:52:02	\N	\N
15	\N	Julieta	Pediatra	86666666-6	julieta.pediatria@medsync.test	+56986666666	t	2026-10-07 19:52:02	2026-10-07 19:52:02	\N	\N
10	\N	Diego	Maradona Medico General	74444444-4	diego.general@medsync.test	+56933220551	t	2026-10-07 19:52:01	2026-10-10 21:22:13	\N	Profesional con especialidades en cardiologia y medicina general
11	\N	Fernanda	Gonzalez	84444444-4	fernanda.general@medsync.test	+56922440793	t	2026-10-07 19:52:01	2026-10-10 21:26:10	\N	Profesional especializada en dermatologia
12	\N	Juan	Gabriel	75555555-5	gabriel.cardio@medsync.test	+56975333213	t	2026-10-07 19:52:02	2026-10-10 22:50:15	\N	asignacion de nueva especialidad
16	16	Roberto	Briceño	20826267-K	ro.acuna@duocuc.cl	+56937020728	t	2026-10-07 21:16:41	2026-10-10 23:24:16	\N	asignacion de nueva especialidad
\.


--
-- Data for Name: services; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.services (id, specialty_id, name, description, duration_minutes, is_active, created_at, updated_at) FROM stdin;
4	4	Consulta dermatológica	Consulta general de dermatología	30	t	2026-09-30 23:41:42	2026-09-30 23:41:42
5	5	Consulta general	\N	30	t	2026-10-07 19:52:01	2026-10-07 19:52:01
6	5	Control de salud	\N	20	t	2026-10-07 19:52:01	2026-10-07 19:52:01
1	1	Consulta cardiológica	\N	30	t	2026-09-25 16:55:52	2026-10-07 19:52:02
7	1	Electrocardiograma	\N	20	t	2026-10-07 19:52:02	2026-10-07 19:52:02
8	6	Consulta pediátrica	\N	30	t	2026-10-07 19:52:02	2026-10-07 19:52:02
\.


--
-- Data for Name: specialties; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.specialties (id, name, description, is_active, created_at, updated_at) FROM stdin;
4	Dermatología	Especialidad relacionada con enfermedades de la piel.	t	2026-09-25 15:45:58	2026-09-25 15:45:58
5	Medicina General	Consultas y controles generales.	t	2026-10-07 19:52:01	2026-10-07 19:52:01
1	Cardiología	Evaluación y control cardiovascular.	t	2026-09-25 15:38:58	2026-10-07 19:52:02
6	Pediatría	Atención médica infantil.	t	2026-10-07 19:52:02	2026-10-07 19:52:02
\.


--
-- Name: appointment_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.appointment_history_id_seq', 66, true);


--
-- Name: appointments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.appointments_id_seq', 32, true);


--
-- Name: availabilities_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.availabilities_id_seq', 98, true);


--
-- Name: health_insurances_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.health_insurances_id_seq', 4, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.migrations_id_seq', 12, true);


--
-- Name: patient_addresses_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.patient_addresses_id_seq', 7, true);


--
-- Name: patients_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.patients_id_seq', 9, true);


--
-- Name: professionals_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.professionals_id_seq', 16, true);


--
-- Name: services_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.services_id_seq', 8, true);


--
-- Name: specialties_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.specialties_id_seq', 6, true);


--
-- Name: appointment_history appointment_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointment_history
    ADD CONSTRAINT appointment_history_pkey PRIMARY KEY (id);


--
-- Name: appointments appointments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_pkey PRIMARY KEY (id);


--
-- Name: availabilities availabilities_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.availabilities
    ADD CONSTRAINT availabilities_pkey PRIMARY KEY (id);


--
-- Name: health_insurances health_insurances_name_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.health_insurances
    ADD CONSTRAINT health_insurances_name_unique UNIQUE (name);


--
-- Name: health_insurances health_insurances_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.health_insurances
    ADD CONSTRAINT health_insurances_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: patient_addresses patient_addresses_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.patient_addresses
    ADD CONSTRAINT patient_addresses_pkey PRIMARY KEY (id);


--
-- Name: patients patients_email_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.patients
    ADD CONSTRAINT patients_email_unique UNIQUE (email);


--
-- Name: patients patients_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.patients
    ADD CONSTRAINT patients_pkey PRIMARY KEY (id);


--
-- Name: patients patients_rut_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.patients
    ADD CONSTRAINT patients_rut_unique UNIQUE (rut);


--
-- Name: professional_specialty professional_specialty_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.professional_specialty
    ADD CONSTRAINT professional_specialty_pkey PRIMARY KEY (professional_id, specialty_id);


--
-- Name: professionals professionals_email_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.professionals
    ADD CONSTRAINT professionals_email_unique UNIQUE (email);


--
-- Name: professionals professionals_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.professionals
    ADD CONSTRAINT professionals_pkey PRIMARY KEY (id);


--
-- Name: professionals professionals_rut_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.professionals
    ADD CONSTRAINT professionals_rut_unique UNIQUE (rut);


--
-- Name: services services_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.services
    ADD CONSTRAINT services_pkey PRIMARY KEY (id);


--
-- Name: specialties specialties_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.specialties
    ADD CONSTRAINT specialties_pkey PRIMARY KEY (id);


--
-- Name: appointment_history_appointment_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointment_history_appointment_date_index ON public.appointment_history USING btree (appointment_id, created_at);


--
-- Name: appointment_history_reassignment_professionals_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointment_history_reassignment_professionals_index ON public.appointment_history USING btree (old_professional_id, new_professional_id);


--
-- Name: appointments_patient_schedule_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointments_patient_schedule_index ON public.appointments USING btree (patient_id, appointment_date, start_time);


--
-- Name: appointments_professional_schedule_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointments_professional_schedule_index ON public.appointments USING btree (professional_id, appointment_date, start_time);


--
-- Name: appointments_service_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointments_service_date_index ON public.appointments USING btree (service_id, appointment_date);


--
-- Name: appointments_status_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointments_status_date_index ON public.appointments USING btree (status, appointment_date);


--
-- Name: availabilities_professional_weekday_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX availabilities_professional_weekday_index ON public.availabilities USING btree (professional_id, weekday, is_active, start_time);


--
-- Name: patient_addresses_commune_region_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX patient_addresses_commune_region_index ON public.patient_addresses USING btree (commune, region);


--
-- Name: patient_addresses_patient_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX patient_addresses_patient_id_index ON public.patient_addresses USING btree (patient_id);


--
-- Name: patients_health_insurance_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX patients_health_insurance_id_index ON public.patients USING btree (health_insurance_id);


--
-- Name: patients_user_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX patients_user_id_index ON public.patients USING btree (user_id);


--
-- Name: professional_specialty_specialty_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX professional_specialty_specialty_index ON public.professional_specialty USING btree (specialty_id);


--
-- Name: professionals_user_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX professionals_user_id_index ON public.professionals USING btree (user_id);


--
-- Name: services_specialty_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX services_specialty_active_index ON public.services USING btree (specialty_id, is_active);


--
-- Name: services_specialty_name_unique_ci; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX services_specialty_name_unique_ci ON public.services USING btree (specialty_id, lower(btrim((name)::text)));


--
-- Name: specialties_name_unique_ci; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX specialties_name_unique_ci ON public.specialties USING btree (lower(btrim((name)::text)));


--
-- Name: appointment_history appointment_history_appointment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointment_history
    ADD CONSTRAINT appointment_history_appointment_id_foreign FOREIGN KEY (appointment_id) REFERENCES public.appointments(id) ON DELETE RESTRICT;


--
-- Name: appointment_history appointment_history_new_professional_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointment_history
    ADD CONSTRAINT appointment_history_new_professional_id_foreign FOREIGN KEY (new_professional_id) REFERENCES public.professionals(id) ON DELETE RESTRICT;


--
-- Name: appointment_history appointment_history_new_service_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointment_history
    ADD CONSTRAINT appointment_history_new_service_id_foreign FOREIGN KEY (new_service_id) REFERENCES public.services(id) ON DELETE RESTRICT;


--
-- Name: appointment_history appointment_history_old_professional_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointment_history
    ADD CONSTRAINT appointment_history_old_professional_id_foreign FOREIGN KEY (old_professional_id) REFERENCES public.professionals(id) ON DELETE RESTRICT;


--
-- Name: appointment_history appointment_history_old_service_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointment_history
    ADD CONSTRAINT appointment_history_old_service_id_foreign FOREIGN KEY (old_service_id) REFERENCES public.services(id) ON DELETE RESTRICT;


--
-- Name: appointments appointments_patient_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_patient_id_foreign FOREIGN KEY (patient_id) REFERENCES public.patients(id) ON DELETE RESTRICT;


--
-- Name: appointments appointments_professional_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_professional_id_foreign FOREIGN KEY (professional_id) REFERENCES public.professionals(id) ON DELETE RESTRICT;


--
-- Name: appointments appointments_service_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_service_id_foreign FOREIGN KEY (service_id) REFERENCES public.services(id) ON DELETE RESTRICT;


--
-- Name: availabilities availabilities_professional_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.availabilities
    ADD CONSTRAINT availabilities_professional_id_foreign FOREIGN KEY (professional_id) REFERENCES public.professionals(id) ON DELETE CASCADE;


--
-- Name: patient_addresses patient_addresses_patient_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.patient_addresses
    ADD CONSTRAINT patient_addresses_patient_id_foreign FOREIGN KEY (patient_id) REFERENCES public.patients(id) ON DELETE CASCADE;


--
-- Name: patients patients_health_insurance_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.patients
    ADD CONSTRAINT patients_health_insurance_id_foreign FOREIGN KEY (health_insurance_id) REFERENCES public.health_insurances(id) ON DELETE SET NULL;


--
-- Name: professional_specialty professional_specialty_professional_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.professional_specialty
    ADD CONSTRAINT professional_specialty_professional_fk FOREIGN KEY (professional_id) REFERENCES public.professionals(id) ON DELETE RESTRICT;


--
-- Name: professional_specialty professional_specialty_specialty_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.professional_specialty
    ADD CONSTRAINT professional_specialty_specialty_fk FOREIGN KEY (specialty_id) REFERENCES public.specialties(id) ON DELETE RESTRICT;


--
-- Name: services services_specialty_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.services
    ADD CONSTRAINT services_specialty_id_foreign FOREIGN KEY (specialty_id) REFERENCES public.specialties(id) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

\unrestrict 6DZCfBmYZhA7ehVTxj69XDpD2WqHWhTw3TdXy4R8Lud1k5rxndVYYgSAEhfGoyT

