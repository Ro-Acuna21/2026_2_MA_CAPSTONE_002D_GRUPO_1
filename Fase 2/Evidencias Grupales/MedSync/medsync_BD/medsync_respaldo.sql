DROP TABLE IF EXISTS center_users CASCADE;
DROP TABLE IF EXISTS patients CASCADE;
DROP TABLE IF EXISTS health_insurances CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS medical_centers CASCADE;

-- Tabla: medical_centers
-- Almacena los centros médicos registrados en MedSync.


CREATE TABLE medical_centers (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    rut VARCHAR(12) NOT NULL UNIQUE,
    address VARCHAR(200),
    phone VARCHAR(20),
    email VARCHAR(150),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE INDEX idx_medical_centers_is_active
ON medical_centers(is_active);


-- Tabla: users
-- Almacena las credenciales de acceso de los usuarios.
-- El rol no se guarda aquí, se define en center_users.


CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    remember_token VARCHAR(100),
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);

-- Tabla: health_insurances
-- Catálogo de previsiones de salud utilizadas en el registro.

CREATE TABLE health_insurances (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    type VARCHAR(20) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT chk_health_insurances_type
        CHECK (type IN ('FONASA', 'ISAPRE', 'PARTICULAR', 'OTHER'))
);

-- Tabla: patients
-- Almacena los datos básicos de los pacientes.

CREATE TABLE patients (
    id BIGSERIAL PRIMARY KEY,
    medical_center_id BIGINT NOT NULL,
    user_id BIGINT NULL,
    health_insurance_id BIGINT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    rut VARCHAR(12) NOT NULL,
    birth_date DATE NULL,
    email VARCHAR(150),
    phone VARCHAR(20),
    address VARCHAR(200),
    medical_insurance VARCHAR(100),
    consent_at TIMESTAMP NULL,
    consent_version VARCHAR(20),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    CONSTRAINT fk_patients_medical_center
        FOREIGN KEY (medical_center_id)
        REFERENCES medical_centers(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_patients_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT uq_patients_center_rut
        UNIQUE (medical_center_id, rut),

    CONSTRAINT uq_patients_center_email
        UNIQUE (medical_center_id, email)
);

CREATE INDEX idx_patients_medical_center
ON patients(medical_center_id);
-- Tabla: center_users
-- Relaciona usuarios con centros médicos y define su rol.

CREATE TABLE center_users (
    id BIGSERIAL PRIMARY KEY,
    medical_center_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    role VARCHAR(20) NOT NULL,
    patient_id BIGINT NULL,
    professional_id BIGINT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_center_users_medical_center
        FOREIGN KEY (medical_center_id)
        REFERENCES medical_centers(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_center_users_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT uq_center_users_center_user
        UNIQUE (medical_center_id, user_id),

    CONSTRAINT chk_center_users_role
        CHECK (role IN ('ADMIN', 'RECEPCIONISTA', 'PROFESIONAL', 'PACIENTE'))
);

CREATE INDEX idx_center_users_center_role
ON center_users(medical_center_id, role);


-- Datos iniciales equivalentes a los seeders de Laravel
INSERT INTO medical_centers (
    name,
    slug,
    rut,
    address,
    phone,
    email,
    is_active,
    created_at,
    updated_at
)
VALUES (
    'Clínica Horizonte',
    'clinica-horizonte',
    '76543210-3',
    'Av. Providencia 1234, Providencia, Santiago',
    '+56221234567',
    'contacto@clinicahorizonte.cl',
    TRUE,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);

INSERT INTO health_insurances (
    name,
    type,
    is_active,
    created_at,
    updated_at
)
VALUES
    ('Fonasa', 'FONASA', TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Isapre', 'ISAPRE', TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Particular', 'PARTICULAR', TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Otra', 'OTHER', TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);