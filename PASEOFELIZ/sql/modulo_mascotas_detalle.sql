-- Panel de detalle de mascota (admin): raza, edad y fecha de registro propia
-- de la mascota. Todas nullable/con default, no rompe mascotas existentes.
-- Las mascotas ya existentes quedarán con fecha_registro = fecha del ALTER
-- (no hay forma de recuperar su fecha real de creación).
ALTER TABLE mascota_usuario
  ADD COLUMN raza VARCHAR(80) NULL AFTER nombre_mascota,
  ADD COLUMN edad TINYINT UNSIGNED NULL AFTER raza,
  ADD COLUMN fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER enfermedades_discapacidades;
