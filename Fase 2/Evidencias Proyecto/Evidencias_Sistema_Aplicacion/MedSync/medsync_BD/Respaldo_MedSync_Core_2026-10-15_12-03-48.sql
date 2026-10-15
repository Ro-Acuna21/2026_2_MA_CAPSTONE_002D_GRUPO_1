--
-- PostgreSQL database dump
--

\restrict zqfvXWtwXqLc889fZalX7dRQsVIWVh12hRKlzdf1pedA9kaT93fdcc02e5KCDaN

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
-- Name: cache; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache OWNER TO postgres;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO postgres;

--
-- Name: center_users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.center_users (
    id bigint NOT NULL,
    medical_center_id bigint NOT NULL,
    user_id bigint NOT NULL,
    role character varying(255) NOT NULL,
    patient_id bigint,
    professional_id bigint,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT center_users_role_check CHECK (((role)::text = ANY ((ARRAY['ADMIN'::character varying, 'RECEPCIONISTA'::character varying, 'PROFESIONAL'::character varying, 'PACIENTE'::character varying])::text[])))
);


ALTER TABLE public.center_users OWNER TO postgres;

--
-- Name: center_users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.center_users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.center_users_id_seq OWNER TO postgres;

--
-- Name: center_users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.center_users_id_seq OWNED BY public.center_users.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO postgres;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO postgres;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO postgres;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO postgres;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO postgres;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: medical_center_addresses; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.medical_center_addresses (
    id bigint NOT NULL,
    medical_center_id bigint NOT NULL,
    address_line character varying(200) NOT NULL,
    commune character varying(100),
    region character varying(100),
    postal_code character varying(20),
    reference character varying(200),
    is_primary boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.medical_center_addresses OWNER TO postgres;

--
-- Name: medical_center_addresses_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.medical_center_addresses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.medical_center_addresses_id_seq OWNER TO postgres;

--
-- Name: medical_center_addresses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.medical_center_addresses_id_seq OWNED BY public.medical_center_addresses.id;


--
-- Name: medical_centers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.medical_centers (
    id bigint NOT NULL,
    name character varying(150) NOT NULL,
    slug character varying(150) NOT NULL,
    database_name character varying(100) NOT NULL,
    rut character varying(12) NOT NULL,
    phone character varying(20),
    email character varying(150),
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.medical_centers OWNER TO postgres;

--
-- Name: medical_centers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.medical_centers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.medical_centers_id_seq OWNER TO postgres;

--
-- Name: medical_centers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.medical_centers_id_seq OWNED BY public.medical_centers.id;


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
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.password_reset_tokens (
    email character varying(150) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO postgres;

--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.personal_access_tokens OWNER TO postgres;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.personal_access_tokens_id_seq OWNER TO postgres;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(150) NOT NULL,
    email character varying(150) NOT NULL,
    password character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    system_role character varying(50)
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: center_users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.center_users ALTER COLUMN id SET DEFAULT nextval('public.center_users_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: medical_center_addresses id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.medical_center_addresses ALTER COLUMN id SET DEFAULT nextval('public.medical_center_addresses_id_seq'::regclass);


--
-- Name: medical_centers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.medical_centers ALTER COLUMN id SET DEFAULT nextval('public.medical_centers_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: center_users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.center_users (id, medical_center_id, user_id, role, patient_id, professional_id, is_active, created_at, updated_at) FROM stdin;
1	1	2	ADMIN	\N	\N	t	2026-09-22 21:13:24	2026-09-22 21:13:24
2	1	3	PACIENTE	1	\N	t	2026-09-22 21:13:25	2026-09-22 21:13:25
3	1	4	PACIENTE	2	\N	t	2026-09-22 21:13:25	2026-09-22 21:13:25
4	1	5	PACIENTE	3	\N	t	2026-09-22 21:13:25	2026-09-22 21:13:25
6	1	7	PACIENTE	5	\N	t	2026-09-24 17:38:11	2026-09-24 17:38:11
14	1	15	RECEPCIONISTA	\N	\N	t	2026-10-07 19:51:53	2026-10-07 19:51:53
15	1	16	PROFESIONAL	\N	16	t	2026-10-07 21:17:02	2026-10-07 21:17:02
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: medical_center_addresses; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.medical_center_addresses (id, medical_center_id, address_line, commune, region, postal_code, reference, is_primary, created_at, updated_at) FROM stdin;
1	1	Av. Siempre Viva 123	Maipú	Región Metropolitana	\N	\N	t	2026-09-15 23:14:46	2026-09-15 23:14:46
\.


--
-- Data for Name: medical_centers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.medical_centers (id, name, slug, database_name, rut, phone, email, is_active, created_at, updated_at) FROM stdin;
1	Clínica Horizonte	clinica-horizonte	medsync_clinica_horizonte	76123456-7	+56912345678	contacto@clinicahorizonte.cl	t	2026-09-15 23:14:46	2026-09-15 23:14:46
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000001_create_cache_table	1
2	0001_01_01_000002_create_jobs_table	1
3	2026_09_04_164340_create_personal_access_tokens_table	1
4	2026_09_06_225034_create_medical_centers_table	1
5	2026_09_06_225035_create_users_table	1
6	2026_09_06_225036_create_center_users_table	1
7	2026_09_06_225040_create_health_insurances_table	1
8	2026_09_06_225045_create_patients_table	1
9	2026_09_12_212901_create_professionals_table	1
10	2026_09_12_232029_add_system_role_to_users_table	1
11	2026_09_13_010609_create_medical_center_addresses_table	1
12	2026_09_13_010619_create_patient_addresses_table	1
13	2026_09_28_223740_add_unique_normalized_email_to_users_table	2
14	2026_09_28_203420_create_password_reset_tokens_table	3
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, name, email, password, email_verified_at, is_active, remember_token, created_at, updated_at, deleted_at, system_role) FROM stdin;
1	Admin MedSync	admin@medsync.cl	$2y$12$TieGFNOrV2HFk.AHjsxpFemMqWYgKhmpVl4Zj25I/EN0xwItuOWSO	\N	t	\N	2026-09-22 21:13:24	2026-09-22 21:13:24	\N	SUPER_ADMIN
2	Administrador Clínica Horizonte	admin@clinicahorizonte.cl	$2y$12$I6nEcu244C71un/GooqWoubmlJYDQ7CnqJaS7ru9V23/GogHzb9zW	\N	t	\N	2026-09-22 21:13:24	2026-09-22 21:13:24	\N	\N
3	Paciente Prueba A	paciente.a@medsync.test	$2y$12$VGjs8cuqGi0XuKezbpqojOJ4YnVLenck9qssOT1ADqFlxwH1P6JJW	\N	t	\N	2026-09-22 21:13:25	2026-09-22 21:13:25	\N	\N
4	Paciente Prueba B	paciente.b@medsync.test	$2y$12$mXFWdOWpSBTDnd4aCPAsfOJwdvLDh1wNnOJv16oSibwbQf08olTwK	\N	t	\N	2026-09-22 21:13:25	2026-09-22 21:13:25	\N	\N
5	Paciente Prueba C	paciente.c@medsync.test	$2y$12$KmeGfIqikGL5cxcd4uklO.OqBXdJLuKW3LdeYkrSO9dUjamsR.l82	\N	t	\N	2026-09-22 21:13:25	2026-09-22 21:13:25	\N	\N
7	Hugo Ruiz	hugo@duocuc.cl	$2y$12$MHolFZdmdBkiknyY0X3XE.7U/KEnhfAbLMF/OQblyS4BmUM5IGq.O	\N	t	\N	2026-09-24 17:38:11	2026-09-24 17:38:11	\N	\N
15	Recepción Clínica Horizonte	recepcion@clinicahorizonte.cl	$2y$12$bVSZd22LwCTr.qUUoGj/LuqYNyhsQaKvlGsyoFSNCmsPAMMb7rvCK	\N	t	\N	2026-10-07 19:51:53	2026-10-07 19:51:53	\N	\N
16	Roberto Acuña Briceño	ro.acuna@duocuc.cl	$2y$12$GbeNXfn8uSDNu2Mx1VLhEe/mT3IrEFk5aOcPc2ZGez.El/oxLqRNK	2026-10-07 21:17:47	t	\N	2026-10-07 21:17:02	2026-10-07 21:17:47	\N	\N
\.


--
-- Name: center_users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.center_users_id_seq', 18, true);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: medical_center_addresses_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.medical_center_addresses_id_seq', 1, true);


--
-- Name: medical_centers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.medical_centers_id_seq', 1, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.migrations_id_seq', 14, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 1, false);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_id_seq', 19, true);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: center_users center_users_medical_center_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.center_users
    ADD CONSTRAINT center_users_medical_center_id_user_id_unique UNIQUE (medical_center_id, user_id);


--
-- Name: center_users center_users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.center_users
    ADD CONSTRAINT center_users_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: medical_center_addresses medical_center_addresses_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.medical_center_addresses
    ADD CONSTRAINT medical_center_addresses_pkey PRIMARY KEY (id);


--
-- Name: medical_centers medical_centers_database_name_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.medical_centers
    ADD CONSTRAINT medical_centers_database_name_unique UNIQUE (database_name);


--
-- Name: medical_centers medical_centers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.medical_centers
    ADD CONSTRAINT medical_centers_pkey PRIMARY KEY (id);


--
-- Name: medical_centers medical_centers_rut_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.medical_centers
    ADD CONSTRAINT medical_centers_rut_unique UNIQUE (rut);


--
-- Name: medical_centers medical_centers_slug_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.medical_centers
    ADD CONSTRAINT medical_centers_slug_unique UNIQUE (slug);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: center_users_medical_center_id_role_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX center_users_medical_center_id_role_index ON public.center_users USING btree (medical_center_id, role);


--
-- Name: center_users_patient_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX center_users_patient_id_index ON public.center_users USING btree (patient_id);


--
-- Name: center_users_professional_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX center_users_professional_id_index ON public.center_users USING btree (professional_id);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: medical_center_addresses_commune_region_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX medical_center_addresses_commune_region_index ON public.medical_center_addresses USING btree (commune, region);


--
-- Name: medical_center_addresses_medical_center_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX medical_center_addresses_medical_center_id_index ON public.medical_center_addresses USING btree (medical_center_id);


--
-- Name: medical_centers_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX medical_centers_is_active_index ON public.medical_centers USING btree (is_active);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: users_email_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_email_index ON public.users USING btree (email);


--
-- Name: users_email_unique_ci; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX users_email_unique_ci ON public.users USING btree (lower(btrim((email)::text))) WHERE (deleted_at IS NULL);


--
-- Name: users_system_role_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_system_role_index ON public.users USING btree (system_role);


--
-- Name: center_users center_users_medical_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.center_users
    ADD CONSTRAINT center_users_medical_center_id_foreign FOREIGN KEY (medical_center_id) REFERENCES public.medical_centers(id) ON DELETE CASCADE;


--
-- Name: center_users center_users_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.center_users
    ADD CONSTRAINT center_users_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: medical_center_addresses medical_center_addresses_medical_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.medical_center_addresses
    ADD CONSTRAINT medical_center_addresses_medical_center_id_foreign FOREIGN KEY (medical_center_id) REFERENCES public.medical_centers(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict zqfvXWtwXqLc889fZalX7dRQsVIWVh12hRKlzdf1pedA9kaT93fdcc02e5KCDaN

