<?php
require_once 'conexion.php';

class CategoriaDenunciaCRUD {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create
    public function crear($nombre, $descripcion, $prioridad = 'media') {
        $query = "INSERT INTO categorias_denuncia (nombre, descripcion, prioridad) 
                 VALUES (:nombre, :descripcion, :prioridad)";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":nombre", $nombre);
        $stmt->bindParam(":descripcion", $descripcion);
        $stmt->bindParam(":prioridad", $prioridad);
        
        return $stmt->execute();
    }
    
    // Read All
    public function listar() {
        $query = "SELECT * FROM categorias_denuncia ORDER BY nombre";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Read One
    public function obtenerPorId($id) {
        $query = "SELECT * FROM categorias_denuncia WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Update
    public function actualizar($id, $nombre, $descripcion, $prioridad, $activa) {
        $query = "UPDATE categorias_denuncia 
                 SET nombre = :nombre, 
                     descripcion = :descripcion, 
                     prioridad = :prioridad,
                     activa = :activa
                 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":nombre", $nombre);
        $stmt->bindParam(":descripcion", $descripcion);
        $stmt->bindParam(":prioridad", $prioridad);
        $stmt->bindParam(":activa", $activa, PDO::PARAM_BOOL);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }
    
    // Delete
    public function eliminar($id) {
        // Primero verificamos si hay denuncias asociadas
        $query = "SELECT COUNT(*) as total FROM denuncias WHERE categoria_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total'] > 0) {
            return false; // No se puede eliminar si hay denuncias asociadas
        }
        
        // Si no hay denuncias asociadas, procedemos a eliminar
        $query = "DELETE FROM categorias_denuncia WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }
}

// Procesamiento del formulario
$categoriaCRUD = new CategoriaDenunciaCRUD($db);
$mensaje = "";
$categoria = ['id' => '', 'nombre' => '', 'descripcion' => '', 'prioridad' => 'media', 'activa' => 1];

// Crear o actualizar categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $prioridad = $_POST['prioridad'];
    $activa = isset($_POST['activa']) ? 1 : 0;
    
    if (empty($id)) {
        // Crear nueva categoría
        if ($categoriaCRUD->crear($nombre, $descripcion, $prioridad)) {
            $mensaje = "<div class='alert alert-success'>Categoría creada correctamente.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al crear la categoría.</div>";
        }
    } else {
        // Actualizar categoría existente
        if ($categoriaCRUD->actualizar($id, $nombre, $descripcion, $prioridad, $activa)) {
            $mensaje = "<div class='alert alert-success'>Categoría actualizada correctamente.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al actualizar la categoría.</div>";
        }
    }
}

// Obtener categoría para editar
if (isset($_GET['editar'])) {
    $categoria = $categoriaCRUD->obtenerPorId($_GET['editar']);
    if (!$categoria) {
        $mensaje = "<div class='alert alert-warning'>Categoría no encontrada.</div>";
        $categoria = ['id' => '', 'nombre' => '', 'descripcion' => '', 'prioridad' => 'media', 'activa' => 1];
    }
}

// Eliminar categoría
if (isset($_GET['eliminar'])) {
    if ($categoriaCRUD->eliminar($_GET['eliminar'])) {
        $mensaje = "<div class='alert alert-success'>Categoría eliminada correctamente.</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>No se puede eliminar la categoría porque tiene denuncias asociadas.</div>";
    }
}

// Listar categorías
$categorias = $categoriaCRUD->listar();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Categorías de Denuncia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
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
        .priority-badge {
            font-size: 0.8em;
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="bi bi-tags-fill me-2"></i>Gestión de Categorías
            </h2>
            <div class="btn-group" role="group">
                <a href="usuarios_crud.php" class="btn btn-outline-primary">
                    <i class="bi bi-people-fill"></i> Usuarios
                </a>
                <a href="categorias_denuncia_crud.php" class="btn btn-primary">
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
            <?= $mensaje ?>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-12">
                <div class="form-container">
                    <h4><?= empty($categoria['id']) ? 'Nueva Categoría' : 'Editar Categoría' ?></h4>
                    <form method="POST" action="">
                        <input type="hidden" name="id" value="<?= $categoria['id'] ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       value="<?= htmlspecialchars($categoria['nombre'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="prioridad" class="form-label">Prioridad</label>
                                <select class="form-select" id="prioridad" name="prioridad" required>
                                    <option value="baja" <?= ($categoria['prioridad'] ?? '') === 'baja' ? 'selected' : '' ?>>Baja</option>
                                    <option value="media" <?= ($categoria['prioridad'] ?? 'media') === 'media' ? 'selected' : '' ?>>Media</option>
                                    <option value="alta" <?= ($categoria['prioridad'] ?? '') === 'alta' ? 'selected' : '' ?>>Alta</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars($categoria['descripcion'] ?? '') ?></textarea>
                        </div>
                        
                        <?php if (!empty($categoria['id'])): ?>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="activa" name="activa" 
                                       <?= ($categoria['activa'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="activa">Activa</label>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> <?= empty($categoria['id']) ? 'Crear' : 'Actualizar' ?>
                            </button>
                            
                            <?php if (!empty($categoria['id'])): ?>
                                <a href="categorias_denuncia_crud.php" class="btn btn-secondary">
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
                <div class="table-container">
                    <h4>Lista de Categorías</h4>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Prioridad</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categorias as $cat): ?>
                                    <tr>
                                        <td><?= $cat['id'] ?></td>
                                        <td><?= htmlspecialchars($cat['nombre']) ?></td>
                                        <td><?= htmlspecialchars($cat['descripcion']) ?></td>
                                        <td>
                                            <?php 
                                                $badgeClass = [
                                                    'baja' => 'bg-info',
                                                    'media' => 'bg-primary',
                                                    'alta' => 'bg-danger'
                                                ][$cat['prioridad']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?= $badgeClass ?> priority-badge">
                                                <?= ucfirst($cat['prioridad']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $cat['activa'] ? 'success' : 'secondary' ?>">
                                                <?= $cat['activa'] ? 'Activa' : 'Inactiva' ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                            <a href="?editar=<?= $cat['id'] ?>" class="btn btn-sm btn-primary" 
                                               title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?eliminar=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('¿Está seguro de eliminar esta categoría? Esta acción no se puede deshacer y no se podrán eliminar categorías con denuncias asociadas.')" 
                                               title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar notificación y desaparecer después de 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
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
