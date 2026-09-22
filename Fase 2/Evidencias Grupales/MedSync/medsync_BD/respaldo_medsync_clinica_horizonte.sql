--
-- PostgreSQL database dump
--

\restrict OUGBRv7Zn5rAONXbEhwFrUenB0RFEYxcknsRFEeJkn7XcRanduuThvT6lsE3OTq

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
-- Name: health_insurances; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: health_insurances_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.health_insurances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: health_insurances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.health_insurances_id_seq OWNED BY public.health_insurances.id;


--
-- Name: patient_addresses; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: patient_addresses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.patient_addresses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: patient_addresses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.patient_addresses_id_seq OWNED BY public.patient_addresses.id;


--
-- Name: patients; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: patients_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.patients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: patients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.patients_id_seq OWNED BY public.patients.id;


--
-- Name: professionals; Type: TABLE; Schema: public; Owner: -
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
    deleted_at timestamp(0) without time zone
);


--
-- Name: professionals_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.professionals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: professionals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.professionals_id_seq OWNED BY public.professionals.id;


--
-- Name: health_insurances id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.health_insurances ALTER COLUMN id SET DEFAULT nextval('public.health_insurances_id_seq'::regclass);


--
-- Name: patient_addresses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patient_addresses ALTER COLUMN id SET DEFAULT nextval('public.patient_addresses_id_seq'::regclass);


--
-- Name: patients id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patients ALTER COLUMN id SET DEFAULT nextval('public.patients_id_seq'::regclass);


--
-- Name: professionals id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professionals ALTER COLUMN id SET DEFAULT nextval('public.professionals_id_seq'::regclass);


--
-- Name: health_insurances health_insurances_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.health_insurances
    ADD CONSTRAINT health_insurances_name_unique UNIQUE (name);


--
-- Name: health_insurances health_insurances_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.health_insurances
    ADD CONSTRAINT health_insurances_pkey PRIMARY KEY (id);


--
-- Name: patient_addresses patient_addresses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patient_addresses
    ADD CONSTRAINT patient_addresses_pkey PRIMARY KEY (id);


--
-- Name: patients patients_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patients
    ADD CONSTRAINT patients_email_unique UNIQUE (email);


--
-- Name: patients patients_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patients
    ADD CONSTRAINT patients_pkey PRIMARY KEY (id);


--
-- Name: patients patients_rut_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patients
    ADD CONSTRAINT patients_rut_unique UNIQUE (rut);


--
-- Name: professionals professionals_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professionals
    ADD CONSTRAINT professionals_email_unique UNIQUE (email);


--
-- Name: professionals professionals_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professionals
    ADD CONSTRAINT professionals_pkey PRIMARY KEY (id);


--
-- Name: professionals professionals_rut_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professionals
    ADD CONSTRAINT professionals_rut_unique UNIQUE (rut);


--
-- Name: patient_addresses_commune_region_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX patient_addresses_commune_region_index ON public.patient_addresses USING btree (commune, region);


--
-- Name: patient_addresses_patient_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX patient_addresses_patient_id_index ON public.patient_addresses USING btree (patient_id);


--
-- Name: patients_health_insurance_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX patients_health_insurance_id_index ON public.patients USING btree (health_insurance_id);


--
-- Name: patients_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX patients_user_id_index ON public.patients USING btree (user_id);


--
-- Name: professionals_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX professionals_user_id_index ON public.professionals USING btree (user_id);


--
-- Name: patient_addresses patient_addresses_patient_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patient_addresses
    ADD CONSTRAINT patient_addresses_patient_id_foreign FOREIGN KEY (patient_id) REFERENCES public.patients(id) ON DELETE CASCADE;


--
-- Name: patients patients_health_insurance_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patients
    ADD CONSTRAINT patients_health_insurance_id_foreign FOREIGN KEY (health_insurance_id) REFERENCES public.health_insurances(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

\unrestrict OUGBRv7Zn5rAONXbEhwFrUenB0RFEYxcknsRFEeJkn7XcRanduuThvT6lsE3OTq

