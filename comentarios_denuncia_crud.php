<?php
require_once 'conexion.php';

class ComentarioDenunciaCRUD {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Crear un nuevo comentario
    public function crear($denuncia_id, $usuario_id, $comentario) {
        $query = "INSERT INTO comentarios_denuncia (denuncia_id, usuario_id, comentario) 
                 VALUES (:denuncia_id, :usuario_id, :comentario)";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":denuncia_id", $denuncia_id);
        $stmt->bindParam(":usuario_id", $usuario_id);
        $stmt->bindParam(":comentario", $comentario);
        
        return $stmt->execute() ? $this->conn->lastInsertId() : false;
    }
    
    // Obtener comentarios de una denuncia
    public function obtenerPorDenuncia($denuncia_id, $orden = 'ASC') {
        $orden = strtoupper($orden) === 'DESC' ? 'DESC' : 'ASC';
        
        $query = "SELECT c.*, u.nombre as usuario_nombre, u.rol as usuario_rol, u.email as usuario_email 
                 FROM comentarios_denuncia c 
                 JOIN usuarios u ON c.usuario_id = u.id 
                 WHERE c.denuncia_id = ? 
                 ORDER BY c.fecha_creacion $orden";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$denuncia_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener un comentario por ID
    public function obtenerPorId($id) {
        $query = "SELECT c.*, u.nombre as usuario_nombre, u.rol as usuario_rol 
                 FROM comentarios_denuncia c 
                 JOIN usuarios u ON c.usuario_id = u.id 
                 WHERE c.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Actualizar un comentario
    public function actualizar($id, $comentario) {
        $query = "UPDATE comentarios_denuncia 
                 SET comentario = :comentario, 
                     editado = 1, 
                     fecha_edicion = CURRENT_TIMESTAMP 
                 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":comentario", $comentario);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
    
    // Eliminar un comentario
    public function eliminar($id) {
        $query = "DELETE FROM comentarios_denuncia WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }
    
    // Verificar si un comentario pertenece a un usuario
    public function verificarPropiedad($comentario_id, $usuario_id) {
        $query = "SELECT COUNT(*) as total FROM comentarios_denuncia 
                 WHERE id = ? AND usuario_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$comentario_id, $usuario_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] > 0;
    }
    
    // Obtener el recuento de comentarios por denuncia
    public function contarPorDenuncia($denuncia_id) {
        $query = "SELECT COUNT(*) as total FROM comentarios_denuncia 
                 WHERE denuncia_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$denuncia_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
}

// Procesamiento del formulario
$mensaje = "";
$tipoMensaje = "info";
$denunciaId = filter_input(INPUT_GET, 'denuncia_id', FILTER_VALIDATE_INT);
$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);

// Validar ID de denuncia
if (!$denunciaId || $denunciaId <= 0) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - ID de denuncia no especificado</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { padding: 20px; }
            .error-container { max-width: 600px; margin: 50px auto; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="alert alert-danger">
                <h4 class="alert-heading">Error: ID de denuncia no especificado o inválido</h4>
                <p>No se pudo cargar la información de la denuncia porque el ID proporcionado no es válido o no se especificó.</p>
                <hr>
                <p class="mb-0">
                    <a href="denuncias_crud.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left"></i> Volver al listado de denuncias
                    </a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$comentarioCRUD = new ComentarioDenunciaCRUD($db);

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
        die("<div class='alert alert-danger'>La denuncia #$denunciaId no fue encontrada o no tienes permiso para verla.</div>");
    }
} catch (PDOException $e) {
    die("<div class='alert alert-danger'>Error al cargar la información de la denuncia: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// ID del usuario actual (en un sistema real, esto vendría de la sesión)
$usuario_actual_id = 1; // Por defecto, en un sistema real usaríamos el ID del usuario autenticado
$usuario_puede_editar = false;

// Procesar el formulario de nuevo comentario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['nuevo_comentario'])) {
        $comentario = trim($_POST['comentario']);
        
        if (empty($comentario)) {
            $mensaje = "El comentario no puede estar vacío.";
            $tipoMensaje = "danger";
        } else {
            try {
                if ($comentarioCRUD->crear($denunciaId, $usuario_actual_id, $comentario)) {
                    $mensaje = "Comentario agregado correctamente.";
                    $tipoMensaje = "success";
                    
                    // Limpiar el formulario
                    $_POST = [];
                    
                    // Redirigir para evitar reenvío del formulario
                    header("Location: comentarios_denuncia_crud.php?denuncia_id=$denunciaId&mensaje=" . urlencode($mensaje) . "&tipo=$tipoMensaje");
                    exit();
                } else {
                    $mensaje = "Error al agregar el comentario.";
                    $tipoMensaje = "danger";
                }
            } catch (Exception $e) {
                $mensaje = $e->getMessage();
                $tipoMensaje = "danger";
            }
        }
    } 
    // Procesar actualización de comentario
    elseif (isset($_POST['actualizar_comentario'])) {
        $comentario_id = $_POST['comentario_id'];
        $comentario = trim($_POST['comentario']);
        
        // Verificar que el comentario pertenece al usuario actual o es administrador
        if ($comentarioCRUD->verificarPropiedad($comentario_id, $usuario_actual_id) || $usuario_es_admin) {
            if (empty($comentario)) {
                $mensaje = "El comentario no puede estar vacío.";
                $tipoMensaje = "danger";
            } else {
                if ($comentarioCRUD->actualizar($comentario_id, $comentario)) {
                    $mensaje = "Comentario actualizado correctamente.";
                    $tipoMensaje = "success";
                    
                    // Redirigir para salir del modo edición
                    header("Location: comentarios_denuncia_crud.php?denuncia_id=$denunciaId&mensaje=" . urlencode($mensaje) . "&tipo=$tipoMensaje");
                    exit();
                } else {
                    $mensaje = "Error al actualizar el comentario.";
                    $tipoMensaje = "danger";
                }
            }
        } else {
            $mensaje = "No tienes permiso para editar este comentario.";
            $tipoMensaje = "danger";
        }
    }
}

// Procesar eliminación de comentario
if (isset($_GET['eliminar'])) {
    $comentario_id = $_GET['eliminar'];
    
    // Verificar que el comentario pertenece al usuario actual o es administrador
    if ($comentarioCRUD->verificarPropiedad($comentario_id, $usuario_actual_id) || $usuario_es_admin) {
        if ($comentarioCRUD->eliminar($comentario_id)) {
            $mensaje = "Comentario eliminado correctamente.";
            $tipoMensaje = "success";
        } else {
            $mensaje = "Error al eliminar el comentario.";
            $tipoMensaje = "danger";
        }
    } else {
        $mensaje = "No tienes permiso para eliminar este comentario.";
        $tipoMensaje = "danger";
    }
    
    // Redirigir para evitar reenvío del formulario
    header("Location: comentarios_denuncia_crud.php?denuncia_id=$denunciaId&mensaje=" . urlencode($mensaje) . "&tipo=$tipoMensaje");
    exit();
}

// Obtener comentarios de la denuncia
$orden = isset($_GET['orden']) && strtoupper($_GET['orden']) === 'ASC' ? 'ASC' : 'DESC';
$comentarios = $comentarioCRUD->obtenerPorDenuncia($denunciaId, $orden);
$total_comentarios = $comentarioCRUD->contarPorDenuncia($denunciaId);

// Obtener comentario para editar
$comentario_editar = null;
if ($editarId) {
    $comentario_editar = $comentarioCRUD->obtenerPorId($editarId);
    
    // Verificar que el comentario pertenece al usuario actual o es administrador
    if ($comentario_editar && ($comentario_editar['usuario_id'] == $usuario_actual_id || $usuario_es_admin)) {
        $usuario_puede_editar = true;
    } else {
        $mensaje = "No tienes permiso para editar este comentario.";
        $tipoMensaje = "danger";
        $comentario_editar = null;
    }
}

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
    <title>Comentarios - Denuncia #<?= htmlspecialchars($denunciaId) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .comment-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid #0d6efd;
        }
        
        .comment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .comment-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 0.5rem;
            margin-bottom: 0.75rem;
        }
        
        .user-avatar {
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
            font-size: 1.1rem;
        }
        
        .comment-actions {
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .comment-card:hover .comment-actions {
            opacity: 1;
        }
        
        .comment-text {
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        .badge-admin {
            background-color: #dc3545;
        }
        
        .badge-moderador {
            background-color: #fd7e14;
        }
        
        .badge-usuario {
            background-color: #6c757d;
        }
        
        .form-control:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }
        
        .btn-outline-primary:hover {
            background-color: #0d6efd;
            color: white;
        }
        
        .comment-edited {
            font-size: 0.8rem;
            color: #6c757d;
            font-style: italic;
        }
        
        .comment-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .sort-options a {
            text-decoration: none;
            color: #6c757d;
            margin: 0 5px;
        }
        
        .sort-options a.active {
            color: #0d6efd;
            font-weight: bold;
        }
        
        .comment-form {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
        }
        
        .comment-form h4 {
            margin-top: 0;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.75rem;
            margin-bottom: 1.25rem;
        }
        
        .denuncia-info {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e9ecef;
        }
        
        .denuncia-info h3 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            color: #212529;
        }
        
        .denuncia-meta {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .denuncia-estado {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .estado-pendiente { background-color: #6c757d; color: white; }
        .estado-en_revision { background-color: #0dcaf0; color: #000; }
        .estado-en_proceso { background-color: #0d6efd; color: white; }
        .estado-resuelta { background-color: #198754; color: white; }
        .estado-rechazada { background-color: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Encabezado -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="denuncias_crud.php?editar=<?= $denunciaId ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver a la denuncia
                </a>
                <h2 class="mt-3 mb-0">Comentarios</h2>
            </div>
            <div>
                <span class="badge bg-primary rounded-pill">
                    <?= $total_comentarios ?> <?= $total_comentarios === 1 ? 'comentario' : 'comentarios' ?>
                </span>
            </div>
        </div>
        
        <!-- Información de la denuncia -->
        <div class="denuncia-info">
            <h3><?= htmlspecialchars($denuncia['titulo']) ?></h3>
            <div class="denuncia-meta">
                <span class="me-3"><i class="bi bi-person"></i> <?= htmlspecialchars($denuncia['usuario_nombre']) ?></span>
                <span class="me-3"><i class="bi bi-tag"></i> <?= htmlspecialchars($denuncia['categoria_nombre']) ?></span>
                <span class="denuncia-estado estado-<?= $denuncia['estado'] ?>">
                    <?php 
                        $estados = [
                            'pendiente' => 'Pendiente',
                            'en_revision' => 'En Revisión',
                            'en_proceso' => 'En Proceso',
                            'resuelta' => 'Resuelta',
                            'rechazada' => 'Rechazada'
                        ];
                        echo $estados[$denuncia['estado']] ?? ucfirst($denuncia['estado']);
                    ?>
                </span>
            </div>
            <p class="mb-0"><?= nl2br(htmlspecialchars($denuncia['descripcion'])) ?></p>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show" role="alert">
                <?= $mensaje ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
        
        <!-- Formulario de comentario -->
        <div class="comment-form">
            <h4><?= $comentario_editar ? 'Editar comentario' : 'Nuevo comentario' ?></h4>
            <form method="POST" action="">
                <?php if ($comentario_editar): ?>
                    <input type="hidden" name="actualizar_comentario" value="1">
                    <input type="hidden" name="comentario_id" value="<?= $comentario_editar['id'] ?>">
                <?php else: ?>
                    <input type="hidden" name="nuevo_comentario" value="1">
                <?php endif; ?>
                
                <div class="mb-3">
                    <label for="comentario" class="form-label">Tu comentario</label>
                    <textarea class="form-control" id="comentario" name="comentario" rows="4" required><?= $comentario_editar ? htmlspecialchars($comentario_editar['comentario']) : '' ?></textarea>
                    <div class="form-text">
                        Los comentarios son públicos y pueden ser vistos por otros usuarios.
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <?php if ($comentario_editar): ?>
                            <a href="comentarios_denuncia_crud.php?denuncia_id=<?= $denunciaId ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x"></i> Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> 
                        <?= $comentario_editar ? 'Actualizar comentario' : 'Publicar comentario' ?>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Lista de comentarios -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Comentarios</h4>
            <div class="sort-options">
                <span class="text-muted me-2">Ordenar por:</span>
                <a href="?denuncia_id=<?= $denunciaId ?>&orden=desc" class="<?= $orden === 'DESC' ? 'active' : '' ?>">
                    <i class="bi bi-sort-down"></i> Más recientes
                </a>
                <span class="text-muted">|</span>
                <a href="?denuncia_id=<?= $denunciaId ?>&orden=asc" class="<?= $orden === 'ASC' ? 'active' : '' ?>">
                    <i class="bi bi-sort-up"></i> Más antiguos
                </a>
            </div>
        </div>
        
        <?php if (empty($comentarios)): ?>
            <div class="text-center py-5 my-4 bg-light rounded">
                <i class="bi bi-chat-square-text" style="font-size: 3rem; color: #dee2e6;"></i>
                <h5 class="mt-3 text-muted">No hay comentarios aún</h5>
                <p class="text-muted">Sé el primero en comentar sobre esta denuncia.</p>
            </div>
        <?php else: ?>
            <div class="mb-5">
                <?php foreach ($comentarios as $comentario): 
                    $fecha = new DateTime($comentario['fecha_creacion']);
                    $hace = tiempo_transcurrido($fecha);
                    
                    // Obtener iniciales del usuario
                    $iniciales = '';
                    $nombres = explode(' ', $comentario['usuario_nombre']);
                    foreach ($nombres as $nombre) {
                        $iniciales .= strtoupper(substr($nombre, 0, 1));
                        if (strlen($iniciales) >= 2) break;
                    }
                    
                    // Determinar la clase del badge según el rol
                    $badge_class = '';
                    if ($comentario['usuario_rol'] === 'administrador') {
                        $badge_class = 'badge-admin';
                    } elseif ($comentario['usuario_rol'] === 'moderador') {
                        $badge_class = 'badge-moderador';
                    } else {
                        $badge_class = 'badge-usuario';
                    }
                ?>
                    <div class="card mb-3 comment-card" id="comentario-<?= $comentario['id'] ?>">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="user-avatar me-3">
                                    <?= $iniciales ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong><?= htmlspecialchars($comentario['usuario_nombre']) ?></strong>
                                            <span class="badge <?= $badge_class ?> ms-2">
                                                <?= ucfirst($comentario['usuario_rol']) ?>
                                            </span>
                                            <?php if ($comentario['usuario_id'] == $denuncia['usuario_id']): ?>
                                                <span class="badge bg-secondary">Autor</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="comment-actions">
                                            <?php if ($comentario['usuario_id'] == $usuario_actual_id || $usuario_es_admin): ?>
                                                <a href="?denuncia_id=<?= $denunciaId ?>&editar=<?= $comentario['id'] ?>#comentario-<?= $comentario['id'] ?>" 
                                                   class="text-primary" 
                                                   title="Editar comentario">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="?denuncia_id=<?= $denunciaId ?>&eliminar=<?= $comentario['id'] ?>" 
                                                   class="text-danger ms-2" 
                                                   onclick="return confirm('¿Está seguro de eliminar este comentario?')" 
                                                   title="Eliminar comentario">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="comment-text mb-2">
                                        <?= nl2br(htmlspecialchars($comentario['comentario'])) ?>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="comment-time" title="<?= $fecha->format('d/m/Y H:i:s') ?>">
                                                <i class="bi bi-clock"></i> <?= $hace ?>
                                            </span>
                                            
                                            <?php if ($comentario['editado']): ?>
                                                <?php 
                                                    $fecha_edicion = new DateTime($comentario['fecha_edicion']);
                                                    $hace_edicion = tiempo_transcurrido($fecha_edicion);
                                                ?>
                                                <span class="comment-edited ms-2" title="Editado el <?= $fecha_edicion->format('d/m/Y H:i:s') ?>">
                                                    <i class="bi bi-pencil-square"></i> editado <?= $hace_edicion ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($comentario['usuario_id'] != $usuario_actual_id): ?>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="responderA('<?= htmlspecialchars(addslashes($comentario['usuario_nombre'])) ?>', <?= $comentario['id'] ?>)">
                                                <i class="bi bi-reply"></i> Responder
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para responder a un comentario
        function responderA(usuario, comentarioId) {
            const textarea = document.getElementById('comentario');
            const mencion = `@${usuario} `;
            
            // Si el textarea ya tiene texto, agregamos un salto de línea antes
            const textoActual = textarea.value.trim();
            const prefijo = textoActual ? '\n\n' : '';
            
            textarea.value = textoActual + prefijo + mencion;
            textarea.focus();
            
            // Desplazarse al formulario
            document.querySelector('.comment-form').scrollIntoView({ behavior: 'smooth' });
            
            // Si hay un comentario en edición, cancelarlo
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('editar')) {
                window.location.href = `comentarios_denuncia_crud.php?denuncia_id=<?= $denunciaId ?>#comentario-${comentarioId}`;
            }
        }
        
        // Mostrar notificación y desaparecer después de 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.querySelector('.alert');
            if (alert) {
                // Desplazarse al mensaje si hay uno
                alert.scrollIntoView({ behavior: 'smooth' });
                
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            }
            
            // Si hay un parámetro de edición en la URL, desplazarse al comentario
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('editar')) {
                const comentarioId = urlParams.get('editar');
                const elemento = document.getElementById(`comentario-${comentarioId}`);
                if (elemento) {
                    setTimeout(() => {
                        elemento.scrollIntoView({ behavior: 'smooth' });
                        elemento.classList.add('bg-light');
                        setTimeout(() => {
                            elemento.classList.remove('bg-light');
                        }, 2000);
                    }, 300);
                }
            }
        });
        
        // Función para cancelar la edición
        function cancelarEdicion() {
            window.location.href = `comentarios_denuncia_crud.php?denuncia_id=<?= $denunciaId ?>`;
        }
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
