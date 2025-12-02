-- Usar la base de datos existente
USE ambitrack;

-- Table: usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    telefono VARCHAR(20),
    contrasena VARCHAR(255) NOT NULL,
    rol ENUM('usuario', 'administrador', 'moderador') DEFAULT 'usuario',
    activo BOOLEAN DEFAULT TRUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL
);

-- Table: categorias_denuncia
CREATE TABLE IF NOT EXISTS categorias_denuncia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    prioridad ENUM('baja', 'media', 'alta') DEFAULT 'media',
    activa BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: denuncias
CREATE TABLE IF NOT EXISTS denuncias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NOT NULL,
    usuario_id INT NOT NULL,
    categoria_id INT NOT NULL,
    direccion VARCHAR(255),
    latitud DECIMAL(10, 8),
    longitud DECIMAL(11, 8),
    estado ENUM('pendiente', 'en_revision', 'en_proceso', 'resuelta', 'rechazada') DEFAULT 'pendiente',
    privacidad ENUM('publica', 'privada') DEFAULT 'publica',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias_denuncia(id) ON DELETE RESTRICT
);

-- Table: archivos_denuncia
CREATE TABLE IF NOT EXISTS archivos_denuncia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    denuncia_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(512) NOT NULL,
    tipo_archivo VARCHAR(100),
    tamano INT,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (denuncia_id) REFERENCES denuncias(id) ON DELETE CASCADE
);

-- Table: seguimiento_denuncia
CREATE TABLE IF NOT EXISTS seguimiento_denuncia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    denuncia_id INT NOT NULL,
    usuario_id INT,
    estado_anterior VARCHAR(50),
    estado_nuevo VARCHAR(50) NOT NULL,
    comentario TEXT,
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (denuncia_id) REFERENCES denuncias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Table: comentarios_denuncia
CREATE TABLE IF NOT EXISTS comentarios_denuncia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    denuncia_id INT NOT NULL,
    usuario_id INT NOT NULL,
    comentario TEXT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    editado BOOLEAN DEFAULT FALSE,
    fecha_edicion TIMESTAMP NULL,
    FOREIGN KEY (denuncia_id) REFERENCES denuncias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Insertar categorías de denuncia por defecto
INSERT INTO categorias_denuncia (nombre, descripcion, prioridad) VALUES
('Infraestructura', 'Problemas con calles, aceras, alumbrado público, etc.', 'media'),
('Medio Ambiente', 'Contaminación, basura, tala de árboles, etc.', 'alta'),
('Seguridad', 'Robos, asaltos, vandalismo, etc.', 'alta'),
('Ruido', 'Contaminación acústica', 'baja'),
('Transporte', 'Problemas con el transporte público', 'media'),
('Salud', 'Problemas de salud pública', 'alta'),
('Educación', 'Problemas en instituciones educativas', 'media'),
('Otros', 'Otras categorías no especificadas', 'baja');

-- Crear usuario administrador por defecto (contraseña: admin123)
INSERT INTO usuarios (nombre, email, contrasena, rol) VALUES 
('Administrador', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador');
