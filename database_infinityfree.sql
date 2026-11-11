-- ============================================================
-- IMPORTAR EN INFINITYFREE
-- 1. En el panel: MySQL Databases -> crea tu base de datos.
-- 2. Abre phpMyAdmin y SELECCIONA esa base de datos.
-- 3. Pestaña "Importar" -> sube este archivo.
-- NO se incluye CREATE DATABASE ni USE porque InfinityFree
-- no permite crearlas desde SQL.
-- ============================================================

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS asistencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    estado ENUM('ausente','presente') NOT NULL DEFAULT 'ausente',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
