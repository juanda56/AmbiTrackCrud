<?php
require_once 'conexion.php';

class DenunciaCRUD {
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Update
    public function crear($titulo, $descripcion, $usuario_id, $categoria_id, $direccion = null, $latitud = null, $longitud = null, $estado = 'pendiente', $privacidad = 'publica') {
        $query = "INSERT INTO denuncias (titulo, descripcion, usuario_id, categoria_id, direccion, latitud, longitud, estado, privacidad) 
                 VALUES (:titulo, :descripcion, :usuario_id, :categoria_id, :direccion, :latitud, :longitud, :estado, :privacidad)";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":titulo", $titulo);
        $stmt->bindParam(":descripcion", $descripcion);
        $stmt->bindParam(":usuario_id", $usuario_id);
        $stmt->bindParam(":categoria_id", $categoria_id);
        $stmt->bindParam(":direccion", $direccion);
        $stmt->bindParam(":latitud", $latitud);
        $stmt->bindParam(":longitud", $longitud);
        $stmt->bindParam(":estado", $estado);
        $stmt->bindParam(":privacidad", $privacidad);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    // Read All with filters
    public function listar($filtros = []) {
        $query = "SELECT d.*, u.nombre as usuario_nombre, c.nombre as categoria_nombre 
                 FROM denuncias d 
                 JOIN usuarios u ON d.usuario_id = u.id 
                 JOIN categorias_denuncia c ON d.categoria_id = c.id 
                 WHERE 1=1";
        
        $params = [];
        
        // Aplicar filtros
        if (!empty($filtros['estado'])) {
            $query .= " AND d.estado = ?";
            $params[] = $filtros['estado'];
        }
        
        if (!empty($filtros['categoria_id'])) {
            $query .= " AND d.categoria_id = ?";
            $params[] = $filtros['categoria_id'];
        }
        
        if (!empty($filtros['usuario_id'])) {
            $query .= " AND d.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }
        
        if (!empty($filtros['busqueda'])) {
            $query .= " AND (d.titulo LIKE ? OR d.descripcion LIKE ?)";
            $busqueda = "%" . $filtros['busqueda'] . "%";
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        
        $query .= " ORDER BY d.fecha_creacion DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Read One
    public function obtenerPorId($id) {
        $query = "SELECT d.*, u.nombre as usuario_nombre, c.nombre as categoria_nombre
                 FROM denuncias d 
                 JOIN usuarios u ON d.usuario_id = u.id 
                 JOIN categorias_denuncia c ON d.categoria_id = c.id 
                 WHERE d.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Update
    public function actualizar($id, $titulo, $descripcion, $categoria_id, $direccion, $latitud, $longitud, $estado, $privacidad) {
        $query = "UPDATE denuncias 
                 SET titulo = :titulo, 
                     descripcion = :descripcion, 
                     categoria_id = :categoria_id,
                     direccion = :direccion,
                     latitud = :latitud,
                     longitud = :longitud,
                     estado = :estado,
                     privacidad = :privacidad,
                     fecha_actualizacion = CURRENT_TIMESTAMP
                 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":titulo", $titulo);
        $stmt->bindParam(":descripcion", $descripcion);
        $stmt->bindParam(":categoria_id", $categoria_id);
        $stmt->bindParam(":direccion", $direccion);
        $stmt->bindParam(":latitud", $latitud);
        $stmt->bindParam(":longitud", $longitud);
        $stmt->bindParam(":estado", $estado);
        $stmt->bindParam(":privacidad", $privacidad);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }
    
    // Delete
    public function eliminar($id) {
        // Primero eliminamos los archivos asociados
        $query = "DELETE FROM archivos_denuncia WHERE denuncia_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        
        // Luego eliminamos los comentarios
        $query = "DELETE FROM comentarios_denuncia WHERE denuncia_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        
        // Finalmente eliminamos la denuncia
        $query = "DELETE FROM denuncias WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }
    
    // Obtener categorías para el select
    public function obtenerCategorias() {
        $query = "SELECT id, nombre FROM categorias_denuncia WHERE activa = 1 ORDER BY nombre";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener usuarios para el select
    public function obtenerUsuarios() {
        // Mostrar todos los usuarios para depuración
        $query = "SELECT id, nombre, email, activo FROM usuarios ORDER BY nombre";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Procesamiento del formulario
$denunciaCRUD = new DenunciaCRUD($db);
$mensaje = "";
$denuncia = [
    'id' => '', 
    'titulo' => '', 
    'descripcion' => '', 
    'usuario_id' => 1, // Por defecto, se puede cambiar según la sesión
    'categoria_id' => '',
    'direccion' => '',
    'latitud' => '',
    'longitud' => '',
    'estado' => 'pendiente',
    'privacidad' => 'publica'
];

// Obtener categorías para los selects
$categorias = $denunciaCRUD->obtenerCategorias();
$usuarios = $denunciaCRUD->obtenerUsuarios();

// Depuración: Mostrar información de los usuarios (deshabilitado)
// echo '<div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">';
// echo '<h4>Depuración - Usuarios cargados:</h4>';
// echo '<pre>';
// var_dump($usuarios);
// echo '</pre>';
// echo '</div>';

// Crear o actualizar denuncia
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $usuario_id = $_POST['usuario_id'];
    $categoria_id = $_POST['categoria_id'];
    $direccion = trim($_POST['direccion']);
    $latitud = !empty($_POST['latitud']) ? $_POST['latitud'] : null;
    $longitud = !empty($_POST['longitud']) ? $_POST['longitud'] : null;
    $estado = $_POST['estado'];
    $privacidad = $_POST['privacidad'];
    
    if (empty($id)) {
        // Crear nueva denuncia
        $nuevoId = $denunciaCRUD->crear($titulo, $descripcion, $usuario_id, $categoria_id, $direccion, $latitud, $longitud, $estado, $privacidad);
        if ($nuevoId) {
            $mensaje = "<div class='alert alert-success'>Denuncia creada correctamente.</div>";
            // Redirigir para editar y poder subir archivos
            header("Location: denuncias_crud.php?editar=$nuevoId&mensaje=Denuncia+creada+correctamente&tipo=success");
            exit();
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al crear la denuncia.</div>";
        }
    } else {
        // Actualizar denuncia existente
        if ($denunciaCRUD->actualizar($id, $titulo, $descripcion, $categoria_id, $direccion, $latitud, $longitud, $estado, $privacidad)) {
            $mensaje = "<div class='alert alert-success'>Denuncia actualizada correctamente.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al actualizar la denuncia.</div>";
        }
    }
}

// Obtener denuncia para editar
if (isset($_GET['editar'])) {
    $denuncia = $denunciaCRUD->obtenerPorId($_GET['editar']);
    if (!$denuncia) {
        $mensaje = "<div class='alert alert-warning'>Denuncia no encontrada.</div>";
    }
}

// Eliminar denuncia
if (isset($_GET['eliminar'])) {
    if ($denunciaCRUD->eliminar($_GET['eliminar'])) {
        $mensaje = "<div class='alert alert-success'>Denuncia eliminada correctamente.</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al eliminar la denuncia.</div>";
    }
}

// Listar denuncias con filtros
$filtros = [
    'estado' => $_GET['estado'] ?? '',
    'categoria_id' => $_GET['categoria_id'] ?? '',
    'usuario_id' => $_GET['usuario_id'] ?? '',
    'busqueda' => $_GET['busqueda'] ?? ''
];

$denuncias = $denunciaCRUD->listar($filtros);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Denuncias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        /* Estilos para el marcador de ubicación actual */
        .current-location-marker {
            background-color: #0d6efd;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            position: relative;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.3);
        }
        
        .pulse-marker {
            position: absolute;
            width: 100%;
            height: 100%;
            background-color: #0d6efd;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(0.8);
                opacity: 0.7;
            }
            70% {
                transform: scale(1.3);
                opacity: 0;
            }
            100% {
                transform: scale(0.8);
                opacity: 0;
            }
        }
        
        /* Estilos para el indicador de precisión */
        .precision-indicator {
            position: absolute;
            background-color: rgba(13, 110, 253, 0.1);
            border: 2px solid #0d6efd;
            border-radius: 50%;
            pointer-events: none;
        }
        
        /* Variables de colores - Tema ecológico */
        :root {
            --color-primary: #2e7d32;
            --color-primary-light: #60ad5e;
            --color-primary-dark: #005005;
            --color-bg: #f5f5f5;
            --color-bg-dark: #e8f5e9;
            --color-bg-light: #ffffff;
            --color-text: #1b5e20;
            --color-text-light: #4c8c4a;
            --color-border: #c8e6c9;
            --color-accent: #8bc34a;
            --color-success: #198754;
            --color-warning: #ffc107;
            --color-danger: #dc3545;
            --color-info: #0dcaf0;
        }
        
        /* Estilos generales */
        body {
            background: linear-gradient(135deg, #f1f8e9 0%, #e8f5e9 100%);
            color: var(--color-text);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-attachment: fixed;
        }
        
        /* Contenedor principal */
        .container {
            background-color: var(--color-bg-light);
            border: 1px solid var(--color-border);
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.1);
            padding: 2.5rem;
            margin-top: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            border-top: 4px solid var(--color-primary);
            transition: all 0.3s ease;
            background: linear-gradient(145deg, #ffffff 0%, #f1f8e9 100%);
        }
        
        /* Encabezados */
        h1, h2, h3, h4, h5, h6 {
            color: var(--color-text);
            font-weight: 600;
        }
        
        /* Enlaces */
        a {
            color: var(--color-primary);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        a:hover {
            color: var(--color-primary-dark);
        }
        
        /* Botones */
        .btn-primary {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
            border: none;
            padding: 0.7rem 2rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.85rem;
            border-radius: 50px;
            transition: all 0.3s ease;
            color: white;
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary:hover {
            background-color: var(--color-primary-dark);
            border-color: var(--color-primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-outline-secondary {
            color: var(--color-primary);
            border: 2px solid var(--color-primary);
            background-color: transparent;
            border-radius: 50px;
            padding: 0.6rem 1.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--color-bg-dark);
            color: white;
            border-color: var(--color-bg-dark);
        }
        
        /* Tarjetas */
        .card {
            background-color: var(--color-bg-light);
            border: 1px solid var(--color-border);
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(139, 195, 74, 0.1);
            margin-bottom: 1.8rem;
            transition: all 0.3s ease;
            overflow: hidden;
            border-top: 4px solid var(--color-primary);
            position: relative;
            background: linear-gradient(145deg, #ffffff 0%, #f9fbe7 100%);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: var(--color-bg-light);
            border-bottom: 1px solid var(--color-border);
            font-weight: 600;
            padding: 1rem 1.5rem;
        }
        
        /* Formularios */
        .form-control, .form-select {
            background-color: var(--color-bg-light);
            border: 2px solid var(--color-border);
            border-radius: 10px;
            padding: 0.9rem 1.2rem;
            color: var(--color-text);
            font-size: 1.05rem;
            height: auto;
            min-height: 52px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 1.2rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.25);
            background-color: #fff;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.7rem;
            color: var(--color-primary-dark);
            font-size: 1.05rem;
            display: block;
        }
        
        /* Tablas */
        .table {
            --bs-table-bg: transparent;
            --bs-table-striped-bg: rgba(200, 230, 201, 0.2);
            --bs-table-hover-bg: rgba(200, 230, 201, 0.3);
            --bs-table-color: var(--color-text);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table thead th {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            border: none;
            padding: 1rem 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .table > :not(:first-child) {
            border-top: none;
        }
        
        .table > :not(caption) > * > * {
            padding: 0.75rem 1rem;
            vertical-align: middle;
        }
        
        /* Badges */
        .badge {
            font-weight: 500;
            padding: 0.4em 0.8em;
            border-radius: 50px;
        }
        
        .bg-primary {
            background-color: var(--color-primary) !important;
        }
        
        /* Alertas */
        .alert {
            border: none;
            border-radius: 8px;
            padding: 1rem 1.5rem;
        }
        
        .alert-success {
            background-color: rgba(25, 135, 84, 0.1);
            color: var(--color-success);
            border-left: 4px solid var(--color-success);
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--color-danger);
            border-left: 4px solid var(--color-danger);
        }
        
        /* Barra de navegación */
        .navbar {
            background-color: var(--color-bg-dark);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: white !important;
            font-size: 1.5rem;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white !important;
        }
        
        /* Botón de menú móvil */
        .navbar-toggler {
            border: none;
            padding: 0.5rem;
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
        }
        
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='%232e7d32' stroke='%232e7d32' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 18px 14px;
            padding-right: 2.5rem;
            font-size: 1.1rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .table-denuncias tr:hover {
            background-color: rgba(25, 135, 84, 0.05) !important;
{{ ... }}
        }
        
        /* Estilos para los estados */
        .estado-pendiente { background-color: #6c757d; color: white; }
        .estado-en_revision { background-color: #0dcaf0; color: #000; }
        .estado-en_proceso { background-color: #ffc107; color: #000; }
        .estado-resuelta { background-color: #198754; color: white; }
        .estado-rechazada { background-color: #dc3545; color: white; }
        
        /* Estilos para los botones de acción */
        .btn-action {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 2px;
            transition: all 0.3s ease;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
        }
        
        /* Estilos para el mapa */
        #map {
            height: 300px;
            border-radius: 8px;
            border: 1px solid var(--color-border);
        }
        
        /* Estilos para el pie de página */
        footer {
            background-color: var(--color-bg-dark);
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            margin-right: 1.5rem;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: white;
            text-decoration: none;
        }
        
        .social-icons a {
            color: white;
            font-size: 1.5rem;
            margin-left: 1rem;
            transition: all 0.3s ease;
        }
        
        .social-icons a:hover {
            color: var(--color-primary-light);
            transform: translateY(-3px);
        }
        
        /* Estilos para dispositivos móviles */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .table-responsive {
                border: none;
            }
            
            .btn {
                padding: 0.4rem 1rem;
                font-size: 0.9rem;
            }
        }
        .form-container {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .table-container {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .action-buttons .btn {
            margin: 0 2px;
        }
        .status-badge {
            font-size: 0.8em;
            padding: 0.35em 0.65em;
        }
        #map {
            height: 300px;
            width: 100%;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .file-preview {
            max-width: 100px;
            max-height: 100px;
            margin: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .files-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
                <h1 class="h3 mb-3 mb-md-0">
                    <i class="bi bi-list-check me-2"></i>Gestión de Denuncias
                </h1>
                
                <!-- Menú principal para pantallas medianas y grandes -->
                <div class="d-none d-md-flex btn-group" role="group">
                    <a href="usuarios_crud.php" class="btn btn-outline-primary">
                        <i class="bi bi-people-fill"></i> Usuarios
                    </a>
                    <a href="categorias_denuncia_crud.php" class="btn btn-outline-primary">
                        <i class="bi bi-tags"></i> Categorías
                    </a>
                    <a href="denuncias_crud.php" class="btn btn-primary">
                        <i class="bi bi-list-check"></i> Denuncias
                    </a>
                    <a href="comentarios_denuncia_crud.php" class="btn btn-outline-primary">
                        <i class="bi bi-chat-text"></i> Comentarios
                    </a>
                    <a href="archivos_denuncia_crud.php" class="btn btn-outline-primary">
                        <i class="bi bi-files"></i> Archivos
                    </a>
                    <a href="seguimiento_denuncia_crud.php" class="btn btn-outline-primary">
                        <i class="bi bi-clock-history"></i> Seguimiento
                    </a>
                </div>
                
                <!-- Menú desplegable para pantallas pequeñas -->
                <div class="dropdown d-md-none">
                    <button class="btn btn-primary dropdown-toggle" type="button" id="menuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-list"></i> Menú
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="menuDropdown">
                        <li><a class="dropdown-item" href="usuarios_crud.php"><i class="bi bi-people-fill me-2"></i>Usuarios</a></li>
                        <li><a class="dropdown-item" href="categorias_denuncia_crud.php"><i class="bi bi-tags me-2"></i>Categorías</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item active" href="denuncias_crud.php"><i class="bi bi-list-check me-2"></i>Denuncias</a></li>
                        <li><a class="dropdown-item" href="comentarios_denuncia_crud.php"><i class="bi bi-chat-text me-2"></i>Comentarios</a></li>
                        <li><a class="dropdown-item" href="archivos_denuncia_crud.php"><i class="bi bi-files me-2"></i>Archivos</a></li>
                        <li><a class="dropdown-item" href="seguimiento_denuncia_crud.php"><i class="bi bi-clock-history me-2"></i>Seguimiento</a></li>
                    </ul>
                </div>
            </div>
        <?php 
        // Mostrar mensaje de éxito/error
        if (isset($_GET['mensaje']) && isset($_GET['tipo'])) {
            echo "<div class='alert alert-{$_GET['tipo']} alert-dismissible fade show' role='alert'>";
            echo htmlspecialchars(urldecode($_GET['mensaje']));
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>';
            echo '</div>';
        } elseif ($mensaje) {
            echo $mensaje;
        }
        ?>
        
        <!-- Filtros -->
        <style>
            /* Estilos para los filtros */
            .filtros-card {
                border: 1px solid var(--color-border);
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
                overflow: hidden;
                background-color: var(--color-bg-light);
                margin-bottom: 2rem;
                transition: all 0.3s ease;
            }
            
            .filtros-header {
                background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
                color: white;
                padding: 1.25rem 1.5rem;
                border-bottom: none;
                position: relative;
                overflow: hidden;
            }
            
            .filtros-header::before {
                content: '';
                position: absolute;
                top: 0;
                right: 0;
                width: 100%;
                height: 100%;
                background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
                transform: translateX(100%);
                transition: transform 0.6s ease;
            }
            
            .filtros-header:hover::before {
                transform: translateX(-100%);
            }
            
            .filtros-body {
                background-color: var(--color-bg-light);
                padding: 1.5rem;
            }
            
            .form-label {
                color: var(--color-text);
                font-weight: 500;
                margin-bottom: 0.5rem;
                display: flex;
                align-items: center;
            }
            
            .form-label i {
                margin-right: 8px;
                color: var(--color-primary);
            }
            
            .form-select, .form-control, input[type="date"] {
                background-color: var(--color-bg-light);
                border: 1px solid var(--color-border);
                color: var(--color-text);
                border-radius: 8px;
                transition: all 0.3s ease;
                height: calc(2.25rem + 2px);
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            
            .form-select:focus, .form-control:focus, input[type="date"]:focus {
                background-color: var(--color-bg-light);
                border-color: var(--color-primary);
                color: var(--color-text);
                box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.15);
            }
            
            .btn-filtrar {
                background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
                border: none;
                font-weight: 500;
                padding: 0.5rem 1.5rem;
                transition: all 0.3s ease;
                color: white;
                border-radius: 6px;
                box-shadow: 0 2px 5px rgba(25, 135, 84, 0.2);
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            
            .btn-filtrar i {
                margin-right: 6px;
            }
            
            .btn-filtrar:hover {
                background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(25, 135, 84, 0.25);
                color: white;
            }
            
            .btn-limpiar {
                background-color: transparent;
                color: var(--color-primary);
                border: 1px solid var(--color-primary);
                font-weight: 500;
                padding: 0.5rem 1.5rem;
                transition: all 0.3s ease;
                border-radius: 6px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            
            .btn-limpiar i {
                margin-right: 6px;
            }
            
            .btn-limpiar:hover {
                background-color: rgba(25, 135, 84, 0.1);
                color: var(--color-primary-dark);
                border-color: var(--color-primary-dark);
                transform: translateY(-2px);
            }
            
            /* Estilos para los selectores */
            .select2-container--default .select2-selection--single {
                background-color: var(--color-bg-light);
                border: 1px solid var(--color-border);
                border-radius: 8px;
                height: 38px;
                padding: 0.375rem 0.75rem;
                transition: all 0.3s ease;
            }
            
            .select2-container--default .select2-selection--single .select2-selection__rendered {
                color: var(--color-text);
                line-height: 1.5;
                padding-left: 0;
            }
            
            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 36px;
                right: 8px;
            }
            
            /* Estilos para los grupos de botones */
            .btn-group-filtros {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                justify-content: flex-end;
            }
        </style>
        
        <div class="card mb-4 filtros-card">
            <div class="card-header filtros-header">
                <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filtrar Denuncias</h5>
            </div>
            <div class="card-body filtros-body">
                <form method="GET" class="row g-4">
                    <div class="col-md-3">
                        <label for="estado" class="form-label"><i class="bi bi-flag me-2"></i>Estado</label>
                        <select name="estado" id="estado" class="form-select">
                            <option value="">Todos los estados</option>
                            <option value="pendiente" <?= ($filtros['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>🟡 Pendiente</option>
                            <option value="en_revision" <?= ($filtros['estado'] ?? '') === 'en_revision' ? 'selected' : '' ?>>🔵 En Revisión</option>
                            <option value="en_proceso" <?= ($filtros['estado'] ?? '') === 'en_proceso' ? 'selected' : '' ?>>🟠 En Proceso</option>
                            <option value="resuelta" <?= ($filtros['estado'] ?? '') === 'resuelta' ? 'selected' : '' ?>>🟢 Resuelta</option>
                            <option value="rechazada" <?= ($filtros['estado'] ?? '') === 'rechazada' ? 'selected' : '' ?>>🔴 Rechazada</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="categoria_id" class="form-label"><i class="bi bi-tag me-2"></i>Categoría</label>
                        <select name="categoria_id" id="categoria_id" class="form-select">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= $categoria['id'] ?>" <?= ($filtros['categoria_id'] ?? '') == $categoria['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($categoria['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="usuario_id" class="form-label"><i class="bi bi-person me-2"></i>Usuario</label>
                        <select name="usuario_id" id="usuario_id" class="form-select">
                            <option value="">Todos los usuarios</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?= $usuario['id'] ?>" <?= ($filtros['usuario_id'] ?? '') == $usuario['id'] ? 'selected' : '' ?>>
                                    👤 <?= htmlspecialchars($usuario['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_desde" class="form-label"><i class="bi bi-calendar3 me-2"></i>Fecha desde</label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                               value="<?= htmlspecialchars($filtros['fecha_desde'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_hasta" class="form-label"><i class="bi bi-calendar3 me-2"></i>Fecha hasta</label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                               value="<?= htmlspecialchars($filtros['fecha_hasta'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <div class="input-group">
                            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                                   value="<?= htmlspecialchars($filtros['busqueda'] ?? '') ?>" 
                                   placeholder="Buscar por título o descripción...">
                            <button class="btn btn-filtrar" type="submit">
                                <i class="bi bi-search me-1"></i> Buscar
                            </button>
                            <?php if (!empty($filtros['estado']) || !empty($filtros['categoria_id']) || !empty($filtros['usuario_id']) || !empty($filtros['busqueda']) || !empty($filtros['fecha_desde']) || !empty($filtros['fecha_hasta'])): ?>
                                <a href="denuncias_crud.php" class="btn btn-limpiar" title="Limpiar filtros">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="form-container">
                    <h4><?= empty($denuncia['id']) ? 'Nueva Denuncia' : 'Editar Denuncia #' . $denuncia['id'] ?></h4>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?= $denuncia['id'] ?>">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="titulo" name="titulo" 
                                           value="<?= htmlspecialchars($denuncia['titulo'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="descripcion" class="form-label">Descripción <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" 
                                              rows="5" required><?= htmlspecialchars($denuncia['descripcion'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="categoria_id" class="form-label">Categoría <span class="text-danger">*</span></label>
                                        <select class="form-select" id="categoria_id" name="categoria_id" required>
                                            <option value="">Seleccione una categoría</option>
                                            <?php foreach ($categorias as $categoria): ?>
                                                <option value="<?= $categoria['id'] ?>" 
                                                    <?= ($denuncia['categoria_id'] ?? '') == $categoria['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($categoria['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="usuario_id" class="form-label">Usuario <span class="text-danger">*</span></label>
                                        <select class="form-select" id="usuario_id" name="usuario_id" required>
                                            <?php foreach ($usuarios as $usuario): ?>
                                                <option value="<?= $usuario['id'] ?>" 
                                                    <?= ($denuncia['usuario_id'] ?? '') == $usuario['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($usuario['nombre']) ?> (<?= htmlspecialchars($usuario['email']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="direccion" class="form-label">Dirección</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="direccion" name="direccion" 
                                               value="<?= htmlspecialchars($denuncia['direccion'] ?? '') ?>">
                                        <button type="button" class="btn btn-outline-secondary" id="buscarDireccion">
                                            <i class="bi bi-geo-alt"></i> Buscar
                                        </button>
                                    </div>
                                    <small class="text-muted">Haz clic en el mapa o usa el buscador para marcar la ubicación.</small>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="latitud" class="form-label">Latitud</label>
                                        <input type="text" class="form-control" id="latitud" name="latitud" 
                                               value="<?= htmlspecialchars($denuncia['latitud'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="longitud" class="form-label">Longitud</label>
                                        <input type="text" class="form-control" id="longitud" name="longitud" 
                                               value="<?= htmlspecialchars($denuncia['longitud'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="estado" class="form-label">Estado</label>
                                        <select class="form-select" id="estado" name="estado" required>
                                            <option value="pendiente" <?= ($denuncia['estado'] ?? 'pendiente') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                            <option value="en_revision" <?= ($denuncia['estado'] ?? '') === 'en_revision' ? 'selected' : '' ?>>En Revisión</option>
                                            <option value="en_proceso" <?= ($denuncia['estado'] ?? '') === 'en_proceso' ? 'selected' : '' ?>>En Proceso</option>
                                            <option value="resuelta" <?= ($denuncia['estado'] ?? '') === 'resuelta' ? 'selected' : '' ?>>Resuelta</option>
                                            <option value="rechazada" <?= ($denuncia['estado'] ?? '') === 'rechazada' ? 'selected' : '' ?>>Rechazada</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="privacidad" class="form-label">Privacidad</label>
                                        <select class="form-select" id="privacidad" name="privacidad">
                                            <option value="publica" <?= ($denuncia['privacidad'] ?? 'publica') === 'publica' ? 'selected' : '' ?>>Pública</option>
                                            <option value="privada" <?= ($denuncia['privacidad'] ?? '') === 'privada' ? 'selected' : '' ?>>Privada</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <?php if (!empty($denuncia['id'])): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Archivos adjuntos</label>
                                        <div class="files-container" id="archivosContainer">
                                            <!-- Aquí se mostrarán los archivos existentes -->
                                            <?php
                                            // Código para mostrar archivos existentes
                                            if (!empty($denuncia['id'])) {
                                                $query = "SELECT * FROM archivos_denuncia WHERE denuncia_id = ?";
                                                $stmt = $db->prepare($query);
                                                $stmt->execute([$denuncia['id']]);
                                                $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                
                                                foreach ($archivos as $archivo) {
                                                    $ext = strtolower(pathinfo($archivo['nombre_archivo'], PATHINFO_EXTENSION));
                                                    $esImagen = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                                                    
                                                    echo '<div class="position-relative d-inline-block">';
                                                    if ($esImagen) {
                                                        echo '<img src="' . htmlspecialchars($archivo['ruta_archivo']) . '" class="file-preview" alt="' . htmlspecialchars($archivo['nombre_archivo']) . '">';
                                                    } else {
                                                        echo '<div class="p-3 bg-light text-center">';
                                                        echo '<i class="bi bi-file-earmark-text" style="font-size: 2rem;"></i><br>';
                                                        echo '<small>' . htmlspecialchars($archivo['nombre_archivo']) . '</small>';
                                                        echo '</div>';
                                                    }
                                                    echo '<a href="eliminar_archivo.php?id=' . $archivo['id'] . '&denuncia_id=' . $denuncia['id'] . '" ';
                                                    echo 'class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" ';
                                                    echo 'onclick="return confirm(\'¿Está seguro de eliminar este archivo?\')" title="Eliminar">';
                                                    echo '<i class="bi bi-x"></i>';
                                                    echo '</a>';
                                                    echo '</div>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <label for="archivos" class="form-label">Agregar más archivos</label>
                                            <input class="form-control" type="file" id="archivos" name="archivos[]" multiple>
                                            <div class="form-text">Puedes seleccionar múltiples archivos (imágenes, documentos, etc.)</div>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> La denuncia fue creada el 
                                        <strong><?= date('d/m/Y H:i', strtotime($denuncia['fecha_creacion'])) ?></strong>
                                        <?php if (!empty($denuncia['fecha_actualizacion'])): ?>
                                            y actualizada por última vez el 
                                            <strong><?= date('d/m/Y H:i', strtotime($denuncia['fecha_actualizacion'])) ?></strong>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label mb-0">Ubicación en el mapa</label>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="centrarUbicacion" title="Centrar en mi ubicación actual">
                                                <i class="bi bi-geo-alt"></i> Mi ubicación
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger d-none" id="detenerSeguimiento" title="Detener seguimiento">
                                                <i class="bi bi-geo-alt-slash"></i> Detener
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div id="map" style="height: 400px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #dee2e6;"></div>
                                    
                                    <div class="alert alert-info py-2 px-3 mb-3 d-none" id="precisionContainer">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <span id="precisionText"></span>
                                        <button type="button" class="btn-close float-end" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                                    </div>
                                    
                                    <div class="input-group mb-2">
                                        <input type="text" class="form-control" id="direccion" name="direccion" 
                                               placeholder="Buscar dirección" value="<?= htmlspecialchars($denuncia['direccion'] ?? '') ?>">
                                        <button class="btn btn-outline-primary" type="button" id="buscarDireccion" title="Buscar dirección en el mapa">
                                            <i class="bi bi-search"></i> Buscar
                                        </button>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Latitud</label>
                                            <input type="text" class="form-control" id="latitud" name="latitud" 
                                                   value="<?= $denuncia['latitud'] ?? '' ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Longitud</label>
                                            <input type="text" class="form-control" id="longitud" name="longitud" 
                                                   value="<?= $denuncia['longitud'] ?? '' ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($denuncia['id'])): ?>
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0">Seguimiento</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-grid gap-2">
                                                <a href="seguimiento_denuncia_crud.php?denuncia_id=<?= $denuncia['id'] ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-list-check"></i> Ver seguimiento
                                                </a>
                                                <a href="comentarios_denuncia_crud.php?denuncia_id=<?= $denuncia['id'] ?>" 
                                                   class="btn btn-outline-secondary btn-sm">
                                                    <i class="bi bi-chat-left-text"></i> Ver comentarios
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Acciones</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-save"></i> 
                                                <?= empty($denuncia['id']) ? 'Guardar Denuncia' : 'Actualizar Denuncia' ?>
                                            </button>
                                            
                                            <?php if (!empty($denuncia['id'])): ?>
                                                <a href="denuncias_crud.php" class="btn btn-outline-secondary">
                                                    <i class="bi bi-plus-circle"></i> Nueva Denuncia
                                                </a>
                                                <a href="#" class="btn btn-outline-danger" 
                                                   onclick="if(confirm('¿Está seguro de eliminar esta denuncia? Esta acción no se puede deshacer.')) { 
                                                       window.location.href='?eliminar=<?= $denuncia['id'] ?>' 
                                                   } return false;">
                                                    <i class="bi bi-trash"></i> Eliminar Denuncia
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Lista de denuncias -->
        <?php if (empty($denuncia['id'])): ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="table-container">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4>Lista de Denuncias</h4>
                            <span class="badge bg-primary">Total: <?= count($denuncias) ?></span>
                        </div>
                        
                        <?php if (empty($denuncias)): ?>
                            <div class="alert alert-info">No se encontraron denuncias con los filtros actuales.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Título</th>
                                            <th>Usuario</th>
                                            <th>Categoría</th>
                                            <th>Estado</th>
                                            <th>Fecha</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($denuncias as $den): ?>
                                            <tr>
                                                <td>#<?= $den['id'] ?></td>
                                                <td>
                                                    <a href="denuncias_crud.php?editar=<?= $den['id'] ?>">
                                                        <?= htmlspecialchars($den['titulo']) ?>
                                                    </a>
                                                </td>
                                                <td><?= htmlspecialchars($den['usuario_nombre']) ?></td>
                                                <td><?= htmlspecialchars($den['categoria_nombre']) ?></td>
                                                <td>
                                                    <?php 
                                                        $estadoClases = [
                                                            'pendiente' => 'bg-secondary',
                                                            'en_revision' => 'bg-info text-dark',
                                                            'en_proceso' => 'bg-primary',
                                                            'resuelta' => 'bg-success',
                                                            'rechazada' => 'bg-danger'
                                                        ];
                                                        $estadoTexto = [
                                                            'pendiente' => 'Pendiente',
                                                            'en_revision' => 'En Revisión',
                                                            'en_proceso' => 'En Proceso',
                                                            'resuelta' => 'Resuelta',
                                                            'rechazada' => 'Rechazada'
                                                        ];
                                                    ?>
                                                    <span class="badge <?= $estadoClases[$den['estado']] ?? 'bg-secondary' ?> status-badge">
                                                        <?= $estadoTexto[$den['estado']] ?? ucfirst($den['estado']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($den['fecha_creacion'])) ?></td>
                                                <td class="action-buttons">
                                                    <a href="denuncias_crud.php?editar=<?= $den['id'] ?>" 
                                                       class="btn btn-sm btn-primary" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-sm btn-danger" 
                                                       onclick="if(confirm('¿Está seguro de eliminar esta denuncia?')) { 
                                                           window.location.href='?eliminar=<?= $den['id'] ?>' 
                                                       } return false;" 
                                                       title="Eliminar">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Variables globales
        let map, marker, watchId, currentLocationMarker;
        const defaultLat = <?= !empty($denuncia['latitud']) ? $denuncia['latitud'] : '19.4326' ?>; // Latitud por defecto (CDMX)
        const defaultLng = <?= !empty($denuncia['longitud']) ? $denuncia['longitud'] : '-99.1332' ?>; // Longitud por defecto (CDMX)
        
        // Inicializar el mapa
        function initMap() {
            // Crear el mapa centrado en la ubicación por defecto
            map = L.map('map').setView([defaultLat, defaultLng], 13);
            
            // Añadir capa de OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Crear un icono personalizado para la ubicación actual
            const currentLocationIcon = L.divIcon({
                className: 'current-location-marker',
                html: '<div class="pulse-marker"></div>',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });
            
            // Si hay una ubicación guardada, agregar un marcador
            if (<?= !empty($denuncia['latitud']) && !empty($denuncia['longitud']) ? 'true' : 'false' ?>) {
                marker = L.marker([defaultLat, defaultLng], {
                    draggable: true
                }).addTo(map);
                
                // Actualizar campos de latitud y longitud al mover el marcador
                marker.on('dragend', function(e) {
                    detenerSeguimientoUbicacion();
                    const { lat, lng } = e.target.getLatLng();
                    actualizarCamposUbicacion(lat, lng);
                    updateAddressFromCoords(lat, lng);
                });
            }
            
            // Manejar clic en el mapa para agregar/mover el marcador
            map.on('click', function(e) {
                detenerSeguimientoUbicacion();
                const { lat, lng } = e.latlng;
                actualizarUbicacion(lat, lng);
                updateAddressFromCoords(lat, lng);
            });
            
            // Configurar eventos para los botones de ubicación
            document.getElementById('centrarUbicacion').addEventListener('click', iniciarSeguimientoUbicacion);
            document.getElementById('detenerSeguimiento').addEventListener('click', detenerSeguimientoUbicacion);
        }
        
        // Actualizar la ubicación en el mapa
        function actualizarUbicacion(lat, lng, direccion = '') {
            // Actualizar marcador de ubicación seleccionada
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng], {
                    draggable: true
                }).addTo(map);
                
                marker.on('dragend', function(e) {
                    detenerSeguimientoUbicacion();
                    const { lat, lng } = e.target.getLatLng();
                    actualizarCamposUbicacion(lat, lng);
                    updateAddressFromCoords(lat, lng);
                });
            }
            
            // Actualizar campos del formulario
            actualizarCamposUbicacion(lat, lng);
            
            // Centrar el mapa en la nueva ubicación
            map.setView([lat, lng], 15);
            
            // Actualizar dirección si se proporciona
            if (direccion) {
                document.getElementById('direccion').value = direccion;
            }
        }
        
        // Actualizar los campos de latitud y longitud
        function actualizarCamposUbicacion(lat, lng) {
            document.getElementById('latitud').value = lat.toFixed(6);
            document.getElementById('longitud').value = lng.toFixed(6);
        }
        
        // Iniciar seguimiento de ubicación en tiempo real
        function iniciarSeguimientoUbicacion() {
            console.log('Iniciando seguimiento de ubicación...');
            
            if (!navigator.geolocation) {
                console.error('Geolocalización no soportada');
                alert('Tu navegador no soporta la geolocalización');
                return;
            }
            
            // Detener cualquier seguimiento anterior
            detenerSeguimientoUbicacion();
            
            // Mostrar indicador de carga
            const botonUbicacion = document.getElementById('centrarUbicacion');
            botonUbicacion.disabled = true;
            botonUbicacion.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Buscando...';
            
            // Opciones para la geolocalización
            const opciones = {
                enableHighAccuracy: true,  // Alta precisión
                maximumAge: 10000,         // Máxima antigüedad de la posición en caché (10 segundos)
                timeout: 15000             // Tiempo máximo de espera (15 segundos)
            };
            
            // Iniciar seguimiento de ubicación
            watchId = navigator.geolocation.watchPosition(
                // Éxito
                function(position) {
                    console.log('Posición obtenida:', position);
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const precision = position.coords.accuracy;
                    
                    // Actualizar interfaz
                    const botonUbicacion = document.getElementById('centrarUbicacion');
                    botonUbicacion.disabled = false;
                    botonUbicacion.innerHTML = '<i class="bi bi-geo-alt"></i> Siguiendo ubicación';
                    
                    const botonDetener = document.getElementById('detenerSeguimiento');
                    botonDetener.classList.remove('d-none');
                    botonDetener.disabled = false;
                    
                    // Crear o actualizar el marcador de ubicación actual
                    if (!currentLocationMarker) {
                        // Crear un icono personalizado para la ubicación actual
                        const iconoUbicacion = L.divIcon({
                            className: 'current-location-marker',
                            html: '<div class="pulse-marker"></div>',
                            iconSize: [20, 20],
                            iconAnchor: [10, 10]
                        });
                        
                        // Crear el marcador
                        currentLocationMarker = L.marker([lat, lng], {
                            icon: iconoUbicacion,
                            zIndexOffset: 1000
                        }).addTo(map);
                        
                        // Añadir círculo de precisión
                        currentLocationMarker.accuracyCircle = L.circle([lat, lng], {
                            radius: precision,
                            color: '#3388ff',
                            weight: 1,
                            fillColor: '#3388ff',
                            fillOpacity: 0.2
                        }).addTo(map);
                    } else {
                        // Actualizar posición del marcador existente
                        currentLocationMarker.setLatLng([lat, lng]);
                        
                        // Actualizar círculo de precisión
                        if (currentLocationMarker.accuracyCircle) {
                            currentLocationMarker.accuracyCircle.setLatLng([lat, lng]).setRadius(precision);
                        }
                    }
                    
                    // Actualizar círculo de precisión
                    if (currentLocationMarker.accuracyCircle) {
                        currentLocationMarker.accuracyCircle.setLatLng([lat, lng]).setRadius(precision);
                    }
                    
                    // Actualizar campos del formulario
                    actualizarCamposUbicacion(lat, lng);
                    updateAddressFromCoords(lat, lng);
                    
                    // Mostrar precisión
                    mostrarPrecision(precision);
                    
                    // Centrar el mapa en la ubicación actual
                    map.setView([lat, lng]);
                },
                // Error
                function(error) {
                    console.error('Error de geolocalización:', error);
                    let errorMessage = 'Error al obtener la ubicación: ';
                    
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = 'Permiso denegado. Por favor, habilita los permisos de ubicación en la configuración de tu navegador.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = 'La información de ubicación no está disponible. Por favor, verifica tu conexión o GPS.';
                            break;
                        case error.TIMEOUT:
                            errorMessage = 'Tiempo de espera agotado. No se pudo obtener la ubicación.';
                            break;
                        case error.UNKNOWN_ERROR:
                        default:
                            errorMessage = 'Ocurrió un error desconocido al intentar obtener tu ubicación.';
                            break;
                    }
                    
                    // Mostrar mensaje de error en la interfaz
                    const precisionContainer = document.getElementById('precisionContainer');
                    const precisionText = document.getElementById('precisionText');
                    precisionText.className = 'text-danger';
                    precisionText.textContent = errorMessage;
                    precisionContainer.classList.remove('d-none');
                    
                    // Restaurar botón
                    const botonUbicacion = document.getElementById('centrarUbicacion');
                    botonUbicacion.disabled = false;
                    botonUbicacion.innerHTML = '<i class="bi bi-geo-alt"></i> Intentar de nuevo';
                },
                // Opciones de geolocalización
                opciones
            );
        }
        
        // Detener el seguimiento de ubicación
        function detenerSeguimientoUbicacion() {
            console.log('Deteniendo seguimiento de ubicación...');
            
            // Detener el seguimiento de geolocalización
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
                watchId = null;
            }
            
            // Restaurar botones
            const botonUbicacion = document.getElementById('centrarUbicacion');
            const botonDetener = document.getElementById('detenerSeguimiento');
            
            botonUbicacion.disabled = false;
            botonUbicacion.innerHTML = '<i class="bi bi-geo-alt"></i> Mi ubicación';
            
            botonDetener.classList.add('d-none');
            botonDetener.disabled = true;
            
            // Ocultar indicador de precisión
            document.getElementById('precisionContainer').classList.add('d-none');
            
            // Eliminar marcador de ubicación actual si existe
            if (currentLocationMarker) {
                if (currentLocationMarker.accuracyCircle) {
                    map.removeLayer(currentLocationMarker.accuracyCircle);
                }
                map.removeLayer(currentLocationMarker);
                currentLocationMarker = null;
            }
        }
        
        // Mostrar información de precisión
        function mostrarPrecision(precision) {
            const precisionContainer = document.getElementById('precisionContainer');
            const precisionText = document.getElementById('precisionText');
            
            if (precision < 20) {
                precisionText.className = 'text-success';
                precisionText.textContent = `Precisión: Muy alta (${Math.round(precision)} metros)`;
            } else if (precision < 50) {
                precisionText.className = 'text-primary';
                precisionText.textContent = `Precisión: Buena (${Math.round(precision)} metros)`;
            } else if (precision < 100) {
                precisionText.className = 'text-warning';
                precisionText.textContent = `Precisión: Media (${Math.round(precision)} metros)`;
            } else {
                precisionText.className = 'text-danger';
                precisionText.textContent = `Precisión: Baja (${Math.round(precision)} metros)`;
            }
            
            precisionContainer.classList.remove('d-none');
        }
        
        // Función para actualizar la dirección a partir de coordenadas (geocodificación inversa)
        function updateAddressFromCoords(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.display_name) {
                        document.getElementById('direccion').value = data.display_name;
                    }
                })
                .catch(error => console.error('Error al obtener la dirección:', error));
        }
        
        // Función para buscar una dirección (geocodificación directa)
        document.getElementById('buscarDireccion').addEventListener('click', function() {
            const direccion = document.getElementById('direccion').value;
            if (!direccion.trim()) return;
            
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(direccion)}&limit=1`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const { lat, lon, display_name } = data[0];
                        
                        // Actualizar campos de latitud y longitud
                        document.getElementById('latitud').value = lat;
                        document.getElementById('longitud').value = lon;
                        
                        // Mover el mapa y el marcador
                        if (marker) {
                            marker.setLatLng([lat, lon]);
                        } else {
                            marker = L.marker([lat, lon], {
                                draggable: true
                            }).addTo(map);
                            
                            // Actualizar campos de latitud y longitud al mover el marcador
                            marker.on('dragend', function(e) {
                                const { lat, lng } = e.target.getLatLng();
                                document.getElementById('latitud').value = lat;
                                document.getElementById('longitud').value = lng;
                                updateAddressFromCoords(lat, lng);
                            });
                        }
                        
                        map.setView([lat, lon], 16);
                        
                        // Actualizar el campo de dirección con el nombre completo
                        document.getElementById('direccion').value = display_name;
                    } else {
                        alert('No se encontró la dirección especificada.');
                    }
                })
                .catch(error => {
                    console.error('Error al buscar la dirección:', error);
                    alert('Ocurrió un error al buscar la dirección.');
                });
        });
        
        // Inicializar el mapa cuando el documento esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar el mapa
            initMap();
            
            // Configurar eventos para los botones de ubicación
            document.getElementById('centrarUbicacion').addEventListener('click', function(e) {
                e.preventDefault();
                iniciarSeguimientoUbicacion();
            });
            
            document.getElementById('detenerSeguimiento').addEventListener('click', function(e) {
                e.preventDefault();
                detenerSeguimientoUbicacion();
            });
            
            // Mostrar notificación y desaparecer después de 5 segundos
            const alert = document.querySelector('.alert');
            if (alert) {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>
