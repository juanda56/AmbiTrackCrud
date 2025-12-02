<?php
session_start();
require_once 'conexion.php';

// Mostrar mensaje de sesión si existe
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

class UsuarioCRUD {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Verificar si el correo ya existe
    public function existeEmail($email, $excluirId = null) {
        $query = "SELECT COUNT(*) as count FROM usuarios WHERE email = :email";
        if ($excluirId) {
            $query .= " AND id != :excluirId";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        if ($excluirId) {
            $stmt->bindParam(":excluirId", $excluirId);
        }
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
    
    // Create
    public function crear($nombre, $email, $telefono, $contrasena, $rol = 'usuario') {
        // Verificar si el correo ya existe
        if ($this->existeEmail($email)) {
            return false; // Retorna falso si el correo ya existe
        }
        
        $query = "INSERT INTO usuarios (nombre, email, telefono, contrasena, rol) 
                 VALUES (:nombre, :email, :telefono, :contrasena, :rol)";
        $stmt = $this->conn->prepare($query);
        
        $hashed_password = password_hash($contrasena, PASSWORD_BCRYPT);
        
        $stmt->bindParam(":nombre", $nombre);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":telefono", $telefono);
        $stmt->bindParam(":contrasena", $hashed_password);
        $stmt->bindParam(":rol", $rol);
        
        return $stmt->execute();
    }
    
    // Read All
    public function listar() {
        $query = "SELECT id, nombre, email, telefono, rol, activo, fecha_registro 
                 FROM usuarios ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Read One
    public function obtenerPorId($id) {
        $query = "SELECT id, nombre, email, telefono, rol, activo 
                 FROM usuarios WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Update
    public function actualizar($id, $nombre, $email, $telefono, $rol, $activo) {
        $query = "UPDATE usuarios 
                 SET nombre = :nombre, 
                     email = :email, 
                     telefono = :telefono, 
                     rol = :rol, 
                     activo = :activo 
                 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":nombre", $nombre);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":telefono", $telefono);
        $stmt->bindParam(":rol", $rol);
        $stmt->bindParam(":activo", $activo, PDO::PARAM_BOOL);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }
    
    // Delete
    public function eliminar($id) {
        // En lugar de eliminar, cambiamos el estado a inactivo
        $query = "UPDATE usuarios SET activo = 0 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        return $stmt->execute();
    }
    
    // Cambiar contraseña
    public function cambiarContrasena($id, $nueva_contrasena) {
        $query = "UPDATE usuarios SET contrasena = :contrasena WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        $hashed_password = password_hash($nueva_contrasena, PASSWORD_BCRYPT);
        
        $stmt->bindParam(":contrasena", $hashed_password);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }
}

// Procesamiento del formulario
$usuarioCRUD = new UsuarioCRUD($db);
$mensaje = "";
$usuario = ['id' => '', 'nombre' => '', 'email' => '', 'telefono' => '', 'rol' => 'usuario', 'activo' => 1];

// Crear o actualizar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $rol = $_POST['rol'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    if (empty($id)) {
        // Crear nuevo usuario
        $contrasena = $_POST['contrasena'];
        
        // Verificar si el correo ya existe (validación adicional)
        if ($usuarioCRUD->existeEmail($email)) {
            $mensaje = "<div class='alert alert-warning'>El correo electrónico ya está registrado. Por favor, utiliza otro correo.</div>";
        } else {
            if ($usuarioCRUD->crear($nombre, $email, $telefono, $contrasena, $rol)) {
                $_SESSION['mensaje'] = "<div class='alert alert-success'>Usuario creado correctamente.</div>";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $mensaje = "<div class='alert alert-danger'>Error al crear el usuario. Inténtalo de nuevo.</div>";
            }
        }
    } else {
        // Actualizar usuario existente
        if ($usuarioCRUD->actualizar($id, $nombre, $email, $telefono, $rol, $activo)) {
            $_SESSION['mensaje'] = "<div class='alert alert-success'>Usuario actualizado correctamente.</div>";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al actualizar el usuario.</div>";
        }
    }
}

// Obtener usuario para editar
if (isset($_GET['editar'])) {
    $usuario = $usuarioCRUD->obtenerPorId($_GET['editar']);
    if (!$usuario) {
        $mensaje = "<div class='alert alert-warning'>Usuario no encontrado.</div>";
        $usuario = ['id' => '', 'nombre' => '', 'email' => '', 'telefono' => '', 'rol' => 'usuario', 'activo' => 1];
    }
}

// Eliminar usuario
if (isset($_GET['eliminar'])) {
    if ($usuarioCRUD->eliminar($_GET['eliminar'])) {
        $mensaje = "<div class='alert alert-success'>Usuario desactivado correctamente.</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al desactivar el usuario.</div>";
    }
}

// Listar usuarios
$usuarios = $usuarioCRUD->listar();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
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
            padding: 2rem;
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
        
        /* Tarjetas */
        .card {
            border: 1px solid var(--color-border);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(139, 195, 74, 0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            border-top: 4px solid var(--color-primary);
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
            padding: 0.75rem 1rem;
            color: var(--color-text);
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--color-primary-dark);
        }
        
        /* Botones */
        .btn-primary {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
            border: none;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            color: white;
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
        }
        
        .btn-primary:hover {
            background: var(--color-primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-outline-secondary {
            color: var(--color-primary);
            border: 2px solid var(--color-primary);
            background-color: transparent;
            border-radius: 50px;
            padding: 0.5rem 1.2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--color-bg-dark);
            color: var(--color-text);
            border-color: var(--color-bg-dark);
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
            padding: 0.8rem 1.2rem;
        }
        
        .table > :not(:first-child) {
            border-top: none;
        }
        
        /* Contenedores de formulario */
        .form-container {
            background-color: var(--color-bg-light);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(139, 195, 74, 0.1);
            border: 1px solid var(--color-border);
        }
        
        .table-container {
            background-color: var(--color-bg-light);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(139, 195, 74, 0.1);
            border: 1px solid var(--color-border);
        }
        
        /* Botones de acción */
        .action-buttons .btn {
            margin: 0 3px;
            padding: 0.35rem 0.7rem;
            font-size: 0.85rem;
            border-radius: 6px;
        }
        
        /* Badges */
        .badge {
            font-weight: 500;
            padding: 0.4em 0.8em;
            border-radius: 50px;
        }
        
        .badge.bg-primary {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%) !important;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="bi bi-people-fill me-2"></i>Gestión de Usuarios
            </h2>
            <div class="btn-group" role="group">
                <a href="usuarios_crud.php" class="btn btn-primary">
                    <i class="bi bi-people-fill"></i> Usuarios
                </a>
                <a href="categorias_denuncia_crud.php" class="btn btn-outline-primary">
                    <i class="bi bi-tags"></i> Categorías
                </a>
                <a href="denuncias_crud.php" class="btn btn-outline-primary">
                    <i class="bi bi-list-check"></i> Denuncias
                </a>
                <a href="comentarios_denuncia_crud.php" class="btn btn-outline-primary">
                    <i class="bi bi-chat-text"></i> Comentarios
                </a>
                <a href="archivos_denuncia_crud.php" class="btn btn-outline-primary">
                    <i class="bi bi-files"></i> Archivos
                </a>
                <a href="seguimiento_denuncia_crud.php" class="btn btn-outline-primary">
                    <i class="bi bi-clock-history"></i> Seguimientos
                </a>
                <a href="#" class="btn btn-outline-secondary" onclick="location.reload()" title="Recargar página">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="mb-4">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-person-plus-fill me-2"></i>
                    <?= empty($usuario['id']) ? 'Nuevo Usuario' : 'Editar Usuario: ' . htmlspecialchars($usuario['nombre']) ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="needs-validation" novalidate>
                    <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="telefono" name="telefono" 
                                       value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="rol" class="form-label">Rol</label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="usuario" <?= ($usuario['rol'] ?? '') === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                                    <option value="moderador" <?= ($usuario['rol'] ?? '') === 'moderador' ? 'selected' : '' ?>>Moderador</option>
                                    <option value="administrador" <?= ($usuario['rol'] ?? '') === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                                </select>
                            </div>
                        </div>
                        
                        <?php if (empty($usuario['id'])): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="contrasena" class="form-label">Contraseña</label>
                                    <input type="password" class="form-control" id="contrasena" name="contrasena" required>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($usuario['id'])): ?>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="activo" name="activo" 
                                       <?= ($usuario['activo'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="activo">Activo</label>
                            </div>
                            
                            <?php if (isset($usuario['id'])): ?>
                                <a href="cambiar_contrasena.php?id=<?= $usuario['id'] ?>" class="btn btn-warning">
                                    <i class="bi bi-key"></i> Cambiar Contraseña
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> <?= empty($usuario['id']) ? 'Crear' : 'Actualizar' ?>
                            </button>
                            
                            <?php if (!empty($usuario['id'])): ?>
                                <a href="usuarios_crud.php" class="btn btn-secondary">
                                    <i class="bi bi-x"></i> Cancelar
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-people me-2"></i>Lista de Usuarios
                        </h5>
                        <span class="badge bg-primary">
                            Total: <?= count($usuarios) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($usuarios)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="bi bi-people display-6 d-block mb-2"></i>
                                                    No hay usuarios registrados
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($usuarios as $u): ?>
                                            <tr>
                                                <td class="fw-bold">#<?= $u['id'] ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-2">
                                                            <i class="bi bi-person-circle fs-4"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold"><?= htmlspecialchars($u['nombre']) ?></div>
                                                            <small class="text-muted">Registrado: <?= date('d/m/Y', strtotime($u['fecha_registro'])) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($u['email']) ?></td>
                                                <td>
                                                    <?php if (!empty($u['telefono'])): ?>
                                                        <a href="tel:<?= htmlspecialchars($u['telefono']) ?>" class="text-decoration-none">
                                                            <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($u['telefono']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $u['rol'] === 'administrador' ? 'danger' : ($u['rol'] === 'moderador' ? 'warning text-dark' : 'primary') ?> px-3 py-2">
                                                        <i class="bi bi-<?= $u['rol'] === 'administrador' ? 'shield-shaded' : ($u['rol'] === 'moderador' ? 'shield-lock' : 'person') ?> me-1"></i>
                                                        <?= ucfirst($u['rol']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $u['activo'] ? 'success' : 'secondary' ?> px-3 py-2">
                                                        <i class="bi bi-<?= $u['activo'] ? 'check-circle' : 'x-circle' ?> me-1"></i>
                                                        <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group" role="group">
                                                        <a href="?editar=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" 
                                                           data-bs-toggle="tooltip" title="Editar usuario">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <?php if ($u['activo']): ?>
                                                            <a href="?eliminar=<?= $u['id'] ?>" 
                                                               class="btn btn-sm btn-outline-danger" 
                                                               onclick="return confirm('¿Estás seguro de desactivar este usuario?')"
                                                               data-bs-toggle="tooltip" title="Desactivar usuario">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="?activar=<?= $u['id'] ?>" 
                                                               class="btn btn-sm btn-outline-success" 
                                                               onclick="return confirm('¿Estás seguro de activar este usuario?')"
                                                               data-bs-toggle="tooltip" title="Activar usuario">
                                                                <i class="bi bi-check-circle"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Inicializar tooltips de Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Animación para las tarjetas al cargar
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    
                    // Forzar reflow para que la animación funcione
                    void card.offsetWidth;
                    
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
            });
            
            // Validación de formulario
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
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
            
            // Manejar parámetros de URL para mostrar mensajes
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('mensaje')) {
                const mensaje = urlParams.get('mensaje');
                const tipo = urlParams.get('tipo') || 'success';
                
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${tipo} alert-dismissible fade show`;
                alertDiv.role = 'alert';
                alertDiv.innerHTML = `
                    ${mensaje}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                `;
                
                document.querySelector('.container').prepend(alertDiv);
                
                // Eliminar parámetros de la URL sin recargar la página
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
                
                // Ocultar después de 5 segundos
                setTimeout(() => {
                    alertDiv.style.transition = 'opacity 0.5s';
                    alertDiv.style.opacity = '0';
                    setTimeout(() => alertDiv.remove(), 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>
