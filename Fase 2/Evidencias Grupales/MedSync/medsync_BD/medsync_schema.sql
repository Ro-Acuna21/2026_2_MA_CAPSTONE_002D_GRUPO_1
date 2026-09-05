

-- ============================================
-- Medical centers
-- ============================================

CREATE TABLE medical_center (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) UNIQUE NOT NULL,
    rut VARCHAR(12) UNIQUE NOT NULL,
    address VARCHAR(200),
    phone VARCHAR(20),
    email VARCHAR(150),
    status BOOLEAN NOT NULL DEFAULT TRUE
);

-- ============================================
-- Users
-- Guarda las credenciales de acceso.
-- El rol NO vive aquí, vive en center_users.
-- ============================================

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status BOOLEAN NOT NULL DEFAULT TRUE,
    remember_token VARCHAR(100)
);

-- ============================================
-- Health insurance
-- ============================================

CREATE TABLE health_insurance (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    type VARCHAR(20) NOT NULL,
    status BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT chk_health_insurance_type
        CHECK (type IN ('FONASA', 'ISAPRE', 'PARTICULAR', 'OTHER'))
);

-- ============================================
-- Patients
-- user_id queda NULL para permitir fichas creadas
-- por recepción sin cuenta de usuario.
-- ============================================

CREATE TABLE patient (
    id SERIAL PRIMARY KEY,
    user_id INTEGER,
    health_insurance_id INTEGER,
    medical_center_id INTEGER NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    rut VARCHAR(12) NOT NULL,
    birth_date DATE,
    email VARCHAR(150),
    phone VARCHAR(20),
    status BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT fk_patient_user
        FOREIGN KEY (user_id)
        REFERENCES users(id),

    CONSTRAINT fk_patient_health_insurance
        FOREIGN KEY (health_insurance_id)
        REFERENCES health_insurance(id),

    CONSTRAINT fk_patient_medical_center
        FOREIGN KEY (medical_center_id)
        REFERENCES medical_center(id),

    CONSTRAINT uq_patient_center_rut
        UNIQUE (medical_center_id, rut),

    CONSTRAINT uq_patient_center_email
        UNIQUE (medical_center_id, email),

    CONSTRAINT uq_patient_center_user
        UNIQUE (medical_center_id, user_id)
);

-- ============================================
-- Professionals
-- user_id queda NULL para permitir crear el profesional
-- antes de darle acceso al sistema.
-- ============================================

CREATE TABLE professional (
    id SERIAL PRIMARY KEY,
    user_id INTEGER,
    medical_center_id INTEGER NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    rut VARCHAR(12) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(20),
    status BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT fk_professional_user
        FOREIGN KEY (user_id)
        REFERENCES users(id),

    CONSTRAINT fk_professional_medical_center
        FOREIGN KEY (medical_center_id)
        REFERENCES medical_center(id),

    CONSTRAINT uq_professional_center_rut
        UNIQUE (medical_center_id, rut),

    CONSTRAINT uq_professional_center_email
        UNIQUE (medical_center_id, email),

    CONSTRAINT uq_professional_center_user
        UNIQUE (medical_center_id, user_id)
);

-- ============================================
-- Center Users
-- Relaciona usuario + centro médico + rol.
-- Es la tabla clave para saber qué rol tiene
-- un usuario dentro de un centro.
-- ============================================

CREATE TABLE center_users (
    id SERIAL PRIMARY KEY,
    medical_center_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    role VARCHAR(20) NOT NULL,
    patient_id INTEGER,
    professional_id INTEGER,
    status BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_center_users
        UNIQUE (medical_center_id, user_id),

    CONSTRAINT fk_center_users_medical_center
        FOREIGN KEY (medical_center_id)
        REFERENCES medical_center(id),

    CONSTRAINT fk_center_users_user
        FOREIGN KEY (user_id)
        REFERENCES users(id),

    CONSTRAINT fk_center_users_patient
        FOREIGN KEY (patient_id)
        REFERENCES patient(id),

    CONSTRAINT fk_center_users_professional
        FOREIGN KEY (professional_id)
        REFERENCES professional(id),

    CONSTRAINT chk_center_users_role
        CHECK (role IN ('ADMIN', 'RECEPCIONISTA', 'PROFESIONAL', 'PACIENTE'))
);

-- ============================================
-- Specialties
-- Por ahora se mantiene simple.
-- Se puede ajustar por centro en otra iteración.
-- ============================================

CREATE TABLE specialty (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description VARCHAR(255)
);

-- ============================================
-- Professional Specialties
-- ============================================

CREATE TABLE professional_specialty (
    professional_id INTEGER NOT NULL,
    specialty_id INTEGER NOT NULL,

    PRIMARY KEY (professional_id, specialty_id),

    CONSTRAINT fk_professional_specialty_professional
        FOREIGN KEY (professional_id)
        REFERENCES professional(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_professional_specialty_specialty
        FOREIGN KEY (specialty_id)
        REFERENCES specialty(id)
        ON DELETE CASCADE
);

-- ============================================
-- Availability
-- ============================================

CREATE TABLE availability (
    id SERIAL PRIMARY KEY,
    professional_id INTEGER NOT NULL,
    day_of_week INTEGER NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,

    CONSTRAINT fk_availability_professional
        FOREIGN KEY (professional_id)
        REFERENCES professional(id)
        ON DELETE CASCADE,

    CONSTRAINT chk_day_of_week
        CHECK (day_of_week BETWEEN 1 AND 7),

    CONSTRAINT chk_availability_times
        CHECK (start_time < end_time)
);

-- ============================================
-- Appointments
-- ============================================

CREATE TABLE appointment (
    id SERIAL PRIMARY KEY,
    medical_center_id INTEGER NOT NULL,
    patient_id INTEGER NOT NULL,
    professional_id INTEGER NOT NULL,
    specialty_id INTEGER NOT NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE',

    CONSTRAINT fk_appointment_medical_center
        FOREIGN KEY (medical_center_id)
        REFERENCES medical_center(id),

    CONSTRAINT fk_appointment_patient
        FOREIGN KEY (patient_id)
        REFERENCES patient(id),

    CONSTRAINT fk_appointment_professional
        FOREIGN KEY (professional_id)
        REFERENCES professional(id),

    CONSTRAINT fk_appointment_specialty
        FOREIGN KEY (specialty_id)
        REFERENCES specialty(id),

    CONSTRAINT chk_appointment_status
        CHECK (status IN ('PENDIENTE', 'CONFIRMADA', 'ATENDIDA', 'CANCELADA', 'NO_SHOW')),

    CONSTRAINT chk_appointment_times
        CHECK (start_time < end_time)
);

-- ============================================
-- Índices recomendados para esta etapa
-- ============================================

CREATE INDEX idx_center_users_user
ON center_users(user_id);

CREATE INDEX idx_center_users_center_role
ON center_users(medical_center_id, role);

CREATE INDEX idx_patient_medical_center
ON patient(medical_center_id);

CREATE INDEX idx_professional_medical_center
ON professional(medical_center_id);

CREATE INDEX idx_appointment_agenda
ON appointment(medical_center_id, appointment_date, professional_id, start_time);

-- ============================================
-- Datos iniciales para desarrollo
-- ============================================

INSERT INTO medical_center (
    name,
    slug,
    rut,
    address,
    phone,
    email,
    status
)
VALUES (
    'MedSync - Centro Demo',
    'centro-demo',
    '76543210-1',
    'Sede de pruebas',
    '+56900000000',
    'demo@medsync.cl',
    true
);

INSERT INTO health_insurance (
    name,
    type,
    status
)
VALUES
    ('FONASA', 'FONASA', true),
    ('Particular', 'PARTICULAR', true);