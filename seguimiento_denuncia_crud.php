<?php
require_once 'conexion.php';

class SeguimientoDenunciaCRUD {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Crear un nuevo seguimiento
    public function crear($denuncia_id, $usuario_id, $estado_anterior, $estado_nuevo, $comentario = null) {
        $query = "INSERT INTO seguimiento_denuncia (denuncia_id, usuario_id, estado_anterior, estado_nuevo, comentario) 
                 VALUES (:denuncia_id, :usuario_id, :estado_anterior, :estado_nuevo, :comentario)";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":denuncia_id", $denuncia_id);
        $stmt->bindParam(":usuario_id", $usuario_id);
        $stmt->bindParam(":estado_anterior", $estado_anterior);
        $stmt->bindParam(":estado_nuevo", $estado_nuevo);
        $stmt->bindParam(":comentario", $comentario);
        
        if ($stmt->execute()) {
            // Actualizar el estado actual de la denuncia
            $this->actualizarEstadoDenuncia($denuncia_id, $estado_nuevo);
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    // Actualizar el estado de una denuncia
    private function actualizarEstadoDenuncia($denuncia_id, $nuevo_estado) {
        $query = "UPDATE denuncias SET estado = :estado, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":estado", $nuevo_estado);
        $stmt->bindParam(":id", $denuncia_id);
        return $stmt->execute();
    }
    
    // Obtener todo el seguimiento de una denuncia
    public function obtenerPorDenuncia($denuncia_id) {
        $query = "SELECT s.*, u.nombre as usuario_nombre, u.rol as usuario_rol 
                 FROM seguimiento_denuncia s 
                 JOIN usuarios u ON s.usuario_id = u.id 
                 WHERE s.denuncia_id = ? 
                 ORDER BY s.fecha_cambio DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$denuncia_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener un seguimiento por ID
    public function obtenerPorId($id) {
        $query = "SELECT s.*, u.nombre as usuario_nombre, u.rol as usuario_rol 
                 FROM seguimiento_denuncia s 
                 JOIN usuarios u ON s.usuario_id = u.id 
                 WHERE s.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Actualizar un seguimiento
    public function actualizar($id, $comentario) {
        $query = "UPDATE seguimiento_denuncia SET comentario = :comentario WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":comentario", $comentario);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
    
    // Eliminar un seguimiento (solo si es el más reciente)
    public function eliminar($id) {
        // Obtener el seguimiento
        $seguimiento = $this->obtenerPorId($id);
        if (!$seguimiento) {
            throw new Exception("Seguimiento no encontrado.");
        }
        
        // Verificar si es el seguimiento más reciente
        $query = "SELECT id FROM seguimiento_denuncia 
                 WHERE denuncia_id = ? 
                 ORDER BY fecha_cambio DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$seguimiento['denuncia_id']]);
        $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ultimo && $ultimo['id'] == $id) {
            // Eliminar el seguimiento
            $query = "DELETE FROM seguimiento_denuncia WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            
            if ($stmt->execute([$id])) {
                // Actualizar el estado de la denuncia al estado anterior
                if ($seguimiento['estado_anterior']) {
                    $this->actualizarEstadoDenuncia($seguimiento['denuncia_id'], $seguimiento['estado_anterior']);
                }
                return true;
            }
            return false;
        } else {
            throw new Exception("Solo se puede eliminar el seguimiento más reciente.");
        }
    }
    
    // Obtener el estado actual de una denuncia
    public function obtenerEstadoActual($denuncia_id) {
        $query = "SELECT estado FROM denuncias WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$denuncia_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['estado'] : null;
    }
    
    // Obtener opciones de estados
    public function getOpcionesEstados($estado_actual = null) {
        $estados = [
            'pendiente' => 'Pendiente',
            'en_revision' => 'En Revisión',
            'en_proceso' => 'En Proceso',
            'resuelta' => 'Resuelta',
            'rechazada' => 'Rechazada'
        ];
        
        // Si hay un estado actual, lo eliminamos de las opciones para evitar duplicados
        if ($estado_actual && isset($estados[$estado_actual])) {
            unset($estados[$estado_actual]);
        }
        
        return $estados;
    }
    
    // Formatear el estado para mostrarlo
    public function formatearEstado($estado) {
        $estados = [
            'pendiente' => 'Pendiente',
            'en_revision' => 'En Revisión',
            'en_proceso' => 'En Proceso',
            'resuelta' => 'Resuelta',
            'rechazada' => 'Rechazada'
        ];
        
        return $estados[$estado] ?? ucfirst($estado);
    }
    
    // Obtener clase CSS para el estado
    public function getClaseEstado($estado) {
        $clases = [
            'pendiente' => 'bg-secondary',
            'en_revision' => 'bg-info text-dark',
            'en_proceso' => 'bg-primary',
            'resuelta' => 'bg-success',
            'rechazada' => 'bg-danger'
        ];
        
        return $clases[$estado] ?? 'bg-secondary';
    }
}

// Procesamiento del formulario
$mensaje = "";
$tipoMensaje = "info";

// Validar y obtener el ID de la denuncia
$denunciaId = filter_input(INPUT_GET, 'denuncia_id', FILTER_VALIDATE_INT);

if (!$denunciaId || $denunciaId <= 0) {
    die("<div class='alert alert-danger'>ID de denuncia no especificado o inválido. <a href='denuncias_crud.php' class='alert-link'>Volver a denuncias</a></div>");
}

// Inicializar el CRUD de seguimiento
$seguimientoCRUD = new SeguimientoDenunciaCRUD($db);

// Obtener información de la denuncia con manejo de errores mejorado
try {
    $query = "SELECT d.*, u.nombre as usuario_nombre, c.nombre as categoria_nombre
              FROM denuncias d 
              JOIN usuarios u ON d.usuario_id = u.id 
              JOIN categorias_denuncia c ON d.categoria_id = c.id 
              WHERE d.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$denunciaId]);
    $denuncia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$denuncia) {
        die("<div class='alert alert-danger'>La denuncia #$denunciaId no fue encontrada o no tienes permiso para verla. <a href='denuncias_crud.php' class='alert-link'>Volver a denuncias</a></div>");
    }
} catch (PDOException $e) {
    die("<div class='alert alert-danger'>Error al cargar la información de la denuncia: " . htmlspecialchars($e->getMessage()) . " <a href='denuncias_crud.php' class='alert-link'>Volver a denuncias</a></div>");
}

// Obtener el estado actual de la denuncia
$estadoActual = $seguimientoCRUD->obtenerEstadoActual($denunciaId);

// Procesar el formulario de nuevo seguimiento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_seguimiento'])) {
    $estado_nuevo = $_POST['estado_nuevo'];
    $comentario = trim($_POST['comentario']);
    
    // ID del usuario actual (en un sistema real, esto vendría de la sesión)
    $usuario_id = 1; // Por defecto, en un sistema real usaríamos el ID del usuario autenticado
    
    try {
        if ($seguimientoCRUD->crear($denunciaId, $usuario_id, $estadoActual, $estado_nuevo, $comentario)) {
            $mensaje = "Seguimiento agregado correctamente.";
            $tipoMensaje = "success";
            
            // Actualizar el estado actual
            $estadoActual = $estado_nuevo;
            
            // Limpiar el formulario
            $_POST = [];
        } else {
            $mensaje = "Error al agregar el seguimiento.";
            $tipoMensaje = "danger";
        }
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "danger";
    }
}

// Procesar eliminación de seguimiento
if (isset($_GET['eliminar'])) {
    try {
        if ($seguimientoCRUD->eliminar($_GET['eliminar'])) {
            $mensaje = "Seguimiento eliminado correctamente.";
            $tipoMensaje = "success";
            
            // Actualizar el estado actual
            $estadoActual = $seguimientoCRUD->obtenerEstadoActual($denunciaId);
        }
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "danger";
    }
    
    // Redirigir para evitar reenvío del formulario
    header("Location: seguimiento_denuncia_crud.php?denuncia_id=$denunciaId&mensaje=" . urlencode($mensaje) . "&tipo=$tipoMensaje");
    exit();
}

// Obtener el historial de seguimiento
$seguimientos = $seguimientoCRUD->obtenerPorDenuncia($denunciaId);

// Mostrar mensaje si existe en la URL
if (isset($_GET['mensaje']) && isset($_GET['tipo'])) {
    $mensaje = urldecode($_GET['mensaje']);
    $tipoMensaje = $_GET['tipo'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de Denuncia #<?= htmlspecialchars($denunciaId) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .timeline {
            position: relative;
            padding-left: 1.5rem;
            margin: 2rem 0;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
            padding-left: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0.5rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background-color: #0d6efd;
            z-index: 1;
        }
        
        .timeline-item:last-child::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 2px;
            height: 100%;
            background: white;
            z-index: 0;
        }
        
        .card {
            transition: transform 0.2s;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .badge-estado {
            font-size: 0.8em;
            padding: 0.35em 0.65em;
        }
        
        .usuario-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #0d6efd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="denuncias_crud.php?editar=<?= $denunciaId ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver a la denuncia
                </a>
                <h2 class="mt-3">Seguimiento de Denuncia</h2>
                <p class="text-muted">
                    <strong>Denuncia #<?= htmlspecialchars($denunciaId) ?>:</strong> 
                    <?= htmlspecialchars($denuncia['titulo']) ?>
                    <span class="badge <?= $seguimientoCRUD->getClaseEstado($estadoActual) ?> ms-2">
                        <?= $seguimientoCRUD->formatearEstado($estadoActual) ?>
                    </span>
                </p>
            </div>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show" role="alert">
                <?= $mensaje ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Historial de Seguimiento</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($seguimientos)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox" style="font-size: 3rem; color: #dee2e6;"></i>
                                <p class="text-muted mt-2">No hay registros de seguimiento para esta denuncia.</p>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($seguimientos as $seguimiento): 
                                    $fecha = new DateTime($seguimiento['fecha_cambio']);
                                    $hace = tiempo_transcurrido($fecha);
                                    
                                    $claseEstado = $seguimientoCRUD->getClaseEstado($seguimiento['estado_nuevo']);
                                    $nombreEstado = $seguimientoCRUD->formatearEstado($seguimiento['estado_nuevo']);
                                    
                                    // Obtener iniciales del usuario
                                    $iniciales = '';
                                    $nombres = explode(' ', $seguimiento['usuario_nombre']);
                                    foreach ($nombres as $nombre) {
                                        $iniciales .= strtoupper(substr($nombre, 0, 1));
                                        if (strlen($iniciales) >= 2) break;
                                    }
                                ?>
                                    <div class="timeline-item">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div class="d-flex align-items-center">
                                                        <div class="usuario-avatar me-2" title="<?= htmlspecialchars($seguimiento['usuario_nombre']) ?>">
                                                            <?= $iniciales ?>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?= htmlspecialchars($seguimiento['usuario_nombre']) ?></h6>
                                                            <small class="text-muted">
                                                                <?= $hace ?>
                                                                <?php if ($seguimiento['usuario_rol'] === 'administrador'): ?>
                                                                    <span class="badge bg-danger ms-1">Admin</span>
                                                                <?php elseif ($seguimiento['usuario_rol'] === 'moderador'): ?>
                                                                    <span class="badge bg-warning text-dark ms-1">Moderador</span>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <span class="badge <?= $claseEstado ?> badge-estado">
                                                            <?= $nombreEstado ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($seguimiento['estado_anterior']): ?>
                                                    <div class="mb-2">
                                                        <small class="text-muted">
                                                            <i class="bi bi-arrow-left-right"></i> 
                                                            Cambiado de 
                                                            <span class="badge bg-light text-dark">
                                                                <?= $seguimientoCRUD->formatearEstado($seguimiento['estado_anterior']) ?>
                                                            </span> 
                                                            a 
                                                            <span class="badge <?= $claseEstado ?>">
                                                                <?= $nombreEstado ?>
                                                            </span>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($seguimiento['comentario']): ?>
                                                    <div class="alert alert-light mt-2 mb-0">
                                                        <?= nl2br(htmlspecialchars($seguimiento['comentario'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Solo permitir eliminar el seguimiento más reciente -->
                                                <?php if ($seguimientos[0]['id'] === $seguimiento['id'] && count($seguimientos) > 1): ?>
                                                    <div class="mt-2 text-end">
                                                        <a href="?denuncia_id=<?= $denunciaId ?>&eliminar=<?= $seguimiento['id'] ?>" 
                                                           class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('¿Está seguro de eliminar este seguimiento? El estado de la denuncia volverá al estado anterior.')">
                                                            <i class="bi bi-trash"></i> Eliminar
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Agregar Seguimiento</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="nuevo_seguimiento" value="1">
                            
                            <div class="mb-3">
                                <label for="estado_actual" class="form-label">Estado Actual</label>
                                <input type="text" class="form-control" id="estado_actual" 
                                       value="<?= $seguimientoCRUD->formatearEstado($estadoActual) ?>" readonly>
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i> 
                                    Estado actual de la denuncia.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="estado_nuevo" class="form-label">Nuevo Estado <span class="text-danger">*</span></label>
                                <select class="form-select" id="estado_nuevo" name="estado_nuevo" required>
                                    <option value="">Seleccione un estado</option>
                                    <?php 
                                    $opcionesEstados = $seguimientoCRUD->getOpcionesEstados($estadoActual);
                                    foreach ($opcionesEstados as $valor => $texto): 
                                    ?>
                                        <option value="<?= $valor ?>"><?= $texto ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="comentario" class="form-label">Comentario</label>
                                <textarea class="form-control" id="comentario" name="comentario" 
                                          rows="4" placeholder="Agregue detalles sobre el seguimiento..."></textarea>
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i> 
                                    Describa los detalles del cambio de estado o actualización.
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Guardar Seguimiento
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Estados de Denuncia</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <span class="badge bg-secondary me-2">P</span>
                                    Pendiente
                                </span>
                                <small class="text-muted">Denuncia recibida</small>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <span class="badge bg-info text-dark me-2">R</span>
                                    En Revisión
                                </span>
                                <small class="text-muted">En proceso de revisión</small>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <span class="badge bg-primary me-2">P</span>
                                    En Proceso
                                </span>
                                <small class="text-muted">Trabajando en la solución</small>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <span class="badge bg-success me-2">✓</span>
                                    Resuelta
                                </span>
                                <small class="text-muted">Problema solucionado</small>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <span class="badge bg-danger me-2">✗</span>
                                    Rechazada
                                </span>
                                <small class="text-muted">Denuncia no procede</small>
                            </li>
                        </ul>
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

<?php
/**
 * Función para mostrar el tiempo transcurrido en formato legible
 */
function tiempo_transcurrido($fecha) {
    $ahora = new DateTime();
    $diferencia = $ahora->diff($fecha);
    
    if ($diferencia->y > 0) {
        return 'hace ' . $diferencia->y . ' ' . ($diferencia->y === 1 ? 'año' : 'años');
    } elseif ($diferencia->m > 0) {
        return 'hace ' . $diferencia->m . ' ' . ($diferencia->m === 1 ? 'mes' : 'meses');
    } elseif ($diferencia->d > 0) {
        if ($diferencia->d === 1) {
            return 'ayer';
        } elseif ($diferencia->d < 7) {
            return 'hace ' . $diferencia->d . ' días';
        } else {
            $semanas = floor($diferencia->d / 7);
            return 'hace ' . $semanas . ' ' . ($semanas === 1 ? 'semana' : 'semanas');
        }
    } elseif ($diferencia->h > 0) {
        return 'hace ' . $diferencia->h . ' ' . ($diferencia->h === 1 ? 'hora' : 'horas');
    } elseif ($diferencia->i > 0) {
        return 'hace ' . $diferencia->i . ' ' . ($diferencia->i === 1 ? 'minuto' : 'minutos');
    } else {
        return 'hace unos segundos';
    }
}
?>
