
-- Medical centers
CREATE TABLE medical_center(
id SERIAL PRIMARY KEY,
name VARCHAR(150) NOT NULL,
rut VARCHAR(12) UNIQUE NOT NULL,
address VARCHAR(200),
phone VARCHAR(20),
email VARCHAR(150),
status BOOLEAN NOT NULL DEFAULT TRUE
);

-- Users
-- El rol YA NO vive aquí (ver center_users más abajo).
CREATE TABLE users(
id SERIAL PRIMARY KEY,
email VARCHAR(150) UNIQUE NOT NULL,
password VARCHAR(255) NOT NULL,
status BOOLEAN NOT NULL DEFAULT TRUE
);

-- Center Users
-- Relaciona un usuario con un centro médico y el rol que tiene
-- en ese centro específico. Es la pieza central del modelo
-- multi-tenant: un mismo usuario podría (a futuro) pertenecer
-- a más de un centro, con roles distintos en cada uno.
CREATE TABLE center_users(
id SERIAL PRIMARY KEY,
medical_center_id INTEGER NOT NULL,
user_id INTEGER NOT NULL,
role VARCHAR(20) NOT NULL,
patient_id INTEGER,
professional_id INTEGER,
status BOOLEAN NOT NULL DEFAULT TRUE,
CONSTRAINT uq_center_users UNIQUE(medical_center_id, user_id),
CONSTRAINT fk_center_users_medical_center FOREIGN KEY (medical_center_id) REFERENCES medical_center(id),
CONSTRAINT fk_center_users_user FOREIGN KEY (user_id) REFERENCES users(id),
CONSTRAINT chk_center_users_role CHECK (role IN ('patient', 'professional', 'center_admin', 'super_admin'))
);

--Health insurance
CREATE TABLE health_insurance(
id SERIAL PRIMARY KEY,
name VARCHAR(100) UNIQUE NOT NULL,
type VARCHAR (20) NOT NULL,
status BOOLEAN NOT NULL DEFAULT TRUE
);

--Patients
-- "rut" ya no es único de forma global: es único POR centro
-- médico (ver uq_patient_center_rut). medical_center_id es
-- obligatorio: todo paciente pertenece a un centro desde su
-- creación.
CREATE TABLE patient(
id SERIAL PRIMARY KEY,
users_id INTEGER UNIQUE NOT NULL,
health_insurance_id INTEGER,
medical_center_id INTEGER NOT NULL,
first_name VARCHAR(100) NOT NULL,
last_name VARCHAR(100) NOT NULL,
rut VARCHAR(12) NOT NULL,
birth_date DATE,
phone VARCHAR(20),
CONSTRAINT fk_patient_users FOREIGN KEY (users_id) REFERENCES users(id),
CONSTRAINT fk_patient_health_insurance FOREIGN KEY (health_insurance_id) REFERENCES health_insurance(id),
CONSTRAINT fk_patient_medical_center FOREIGN KEY (medical_center_id) REFERENCES medical_center(id),
CONSTRAINT uq_patient_center_rut UNIQUE(medical_center_id, rut)
);

--Professionals
CREATE TABLE professional(
id SERIAL PRIMARY KEY,
users_id INTEGER UNIQUE NOT NULL,
medical_center_id INTEGER NOT NULL,
first_name VARCHAR(100) NOT NULL,
last_name VARCHAR(100) NOT NULL,
rut VARCHAR(12) NOT NULL,
phone VARCHAR(20),
CONSTRAINT fk_professional_users FOREIGN KEY (users_id) REFERENCES users(id),
CONSTRAINT fk_professional_medical_center FOREIGN KEY (medical_center_id) REFERENCES medical_center(id),
CONSTRAINT uq_professional_center_rut UNIQUE(medical_center_id, rut)
);

--Specialties
CREATE TABLE specialty(
id SERIAL PRIMARY KEY,
name VARCHAR(100) UNIQUE NOT NULL,
description VARCHAR(255)
);

--Professional Specialties
CREATE TABLE professional_specialty(
professional_id INTEGER NOT NULL,
specialty_id INTEGER NOT NULL,
PRIMARY KEY(professional_id,specialty_id),
CONSTRAINT fk_professional_specialty_professional FOREIGN KEY (professional_id) REFERENCES professional(id) ON DELETE CASCADE,
CONSTRAINT fk_professional_specialty_specialty FOREIGN KEY (specialty_id) REFERENCES specialty(id) ON DELETE CASCADE
);

-- Availability
CREATE TABLE availability(
id SERIAL PRIMARY KEY,
professional_id INTEGER NOT NULL,
day_of_week INTEGER NOT NULL,
start_time TIME NOT NULL,
end_time TIME NOT NULL,
CONSTRAINT fk_availability_professional FOREIGN KEY (professional_id) REFERENCES professional(id) ON DELETE CASCADE,
CONSTRAINT chk_day_of_week CHECK (day_of_week BETWEEN 1 AND 7),
CONSTRAINT chk_availability_times CHECK (start_time < end_time)
);

-- Appointments
-- start_time / end_time corregidos a TIME (antes estaban como DATE).
-- status ahora tiene CHECK con valores válidos.
CREATE TABLE appointment(
id SERIAL PRIMARY KEY,
medical_center_id INTEGER NOT NULL,
patient_id INTEGER NOT NULL,
professional_id INTEGER NOT NULL,
specialty_id INTEGER NOT NULL,
appointment_date DATE NOT NULL,
start_time TIME NOT NULL,
end_time TIME NOT NULL,
status VARCHAR(30) NOT NULL DEFAULT 'pending',
CONSTRAINT fk_appointment_medical_center FOREIGN KEY (medical_center_id) REFERENCES medical_center(id),
CONSTRAINT fk_appointment_patient FOREIGN KEY (patient_id) REFERENCES patient(id),
CONSTRAINT fk_appointment_professional FOREIGN KEY (professional_id) REFERENCES professional(id),
CONSTRAINT fk_appointment_specialty FOREIGN KEY (specialty_id) REFERENCES specialty(id),
CONSTRAINT chk_appointment_status CHECK (status IN ('pending', 'confirmed', 'completed', 'cancelled', 'no_show')),
CONSTRAINT chk_appointment_times CHECK (start_time < end_time)
);

-- Índices recomendados
CREATE INDEX idx_center_users_user ON center_users(user_id);
CREATE INDEX idx_patient_medical_center ON patient(medical_center_id);
CREATE INDEX idx_appointment_agenda ON appointment(medical_center_id, appointment_date, professional_id, start_time);

-- Centro médico de referencia para desarrollo/pruebas
INSERT INTO medical_center (name, rut, address, phone, email, status)
VALUES ('MedSync - Centro Demo', '76543210-1', 'Sede de pruebas', '+56900000000', 'demo@medsync.cl', true);