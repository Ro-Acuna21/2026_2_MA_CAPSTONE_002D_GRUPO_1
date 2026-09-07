# Respaldo SQL — MedSync

Este archivo SQL corresponde a un respaldo documental del esquema principal de base de datos utilizado en la Iteración 1 del proyecto MedSync.

La fuente principal para crear y versionar la base de datos son las migraciones de Laravel ubicadas en:

medsync_backend/database/migrations/

Por lo tanto, este archivo SQL no reemplaza las migraciones. Su objetivo es servir como apoyo para revisar la estructura de las tablas principales del sistema o recrearlas manualmente en caso de ser necesario.

El respaldo incluye solamente las tablas principales de MedSync implementadas en la Iteración 1:

- medical_centers
- users
- health_insurances
- patients
- center_users

No se incluyen tablas internas generadas por Laravel, como cache, jobs, personal_access_tokens o migrations, ya que esas son administradas automáticamente por Laravel mediante sus propias migraciones.

En caso de diferencias entre este archivo SQL y las migraciones, se debe considerar como válida la estructura definida en las migraciones de Laravel.