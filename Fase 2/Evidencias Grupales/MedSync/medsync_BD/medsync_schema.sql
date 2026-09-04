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
CREATE TABLE users(
id SERIAL PRIMARY KEY,
email VARCHAR(150) UNIQUE NOT NULL,
password VARCHAR(255) NOT NULL,
status BOOLEAN NOT NULL DEFAULT TRUE
);
ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'patient';

ALTER TABLE users ADD CONSTRAINT chk_users_role 
  CHECK (role IN ('patient', 'professional', 'center_admin', 'super_admin'));
--Health insurance
CREATE TABLE health_insurance(
id SERIAL PRIMARY KEY,
name VARCHAR(100) UNIQUE NOT NULL,
type VARCHAR (20) NOT NULL,
status BOOLEAN NOT NULL DEFAULT TRUE
);
--Patients
CREATE TABLE patient(
id SERIAL PRIMARY KEY,
users_id INTEGER UNIQUE NOT NULL,
health_insurance_id INTEGER,
first_name VARCHAR(100) NOT NULL,
last_name VARCHAR(100) NOT NULL,
rut VARCHAR(12) UNIQUE NOT NULL,
birth_date DATE,
phone VARCHAR(20),
CONSTRAINT fk_patient_users FOREIGN KEY (users_id) REFERENCES users(id),
CONSTRAINT fk_patient_health_insurance FOREIGN KEY (health_insurance_id) REFERENCES health_insurance(id)
);

--Professionals
CREATE TABLE professional(
id SERIAL PRIMARY KEY,
users_id INTEGER UNIQUE NOT NULL,
medical_center_id INTEGER NOT NULL,
first_name VARCHAR(100) NOT NULL,
last_name VARCHAR(100) NOT NULL,
rut VARCHAR(12) UNIQUE NOT NULL,
phone VARCHAR(20),
CONSTRAINT fk_professional_users FOREIGN KEY (users_id) REFERENCES users(id),
CONSTRAINT fk_professional_medical_center FOREIGN KEY (medical_center_id) REFERENCES medical_center(id)
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
-- Valida que el dia de la semana sea entre 1 y 7
CONSTRAINT chk_day_of_week CHECK (day_of_week BETWEEN 1 AND 7),
--Verifica que la hora de inicio sea menor a la hora de termino
CONSTRAINT chk_availability_times CHECK (start_time < end_time)
);

-- Appointments
CREATE TABLE appointment(
id SERIAL PRIMARY KEY,
medical_center_id INTEGER NOT NULL,
patient_id INTEGER NOT NULL,
professional_id INTEGER NOT NULL,
specialty_id INTEGER NOT NULL,
appointment_date DATE NOT NULL,
start_time DATE NOT NULL,
end_time DATE NOT NULL,
status VARCHAR(30) NOT NULL DEFAULT 'pending',
CONSTRAINT fk_appointment_medical_center FOREIGN KEY (medical_center_id) REFERENCES medical_center(id),
CONSTRAINT fk_appointment_patient FOREIGN KEY (patient_id) REFERENCES patient(id),
CONSTRAINT fk_appointment_professional FOREIGN KEY (professional_id) REFERENCES professional(id),
CONSTRAINT fk_appointment_specialty FOREIGN KEY (specialty_id) REFERENCES specialty(id),
--Verifica que la hora de inicio sea menor a la hora de termino
CONSTRAINT chk_appointment_times CHECK (start_time<end_time)
);




