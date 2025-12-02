<?php
require_once 'conexion.php';

class ArchivoDenunciaCRUD {
    private $conn;
    private $uploadDir = 'uploads/denuncias/';
    private $maxFileSize = 5 * 1024 * 1024; // 5MB
    private $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    public function __construct($db) {
        $this->conn = $db;
        // Crear directorio de subidas si no existe
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }
    
    // Subir archivo
    public function subirArchivo($denunciaId, $file) {
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileType = $file['type'];
        $fileError = $file['error'];
        
        // Validar tipo de archivo
        if (!in_array($fileType, $this->allowedTypes)) {
            throw new Exception("Tipo de archivo no permitido. Solo se permiten imágenes, PDF y documentos de Office.");
        }
        
        // Validar tamaño del archivo
        if ($fileSize > $this->maxFileSize) {
            throw new Exception("El archivo es demasiado grande. El tamaño máximo permitido es 5MB.");
        }
        
        // Generar nombre único para el archivo
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $newFileName = uniqid('denuncia_' . $denunciaId . '_', true) . '.' . $fileExt;
        $filePath = $this->uploadDir . $newFileName;
        
        // Mover el archivo al directorio de subidas
        if (move_uploaded_file($fileTmpName, $filePath)) {
            // Guardar en la base de datos
            $query = "INSERT INTO archivos_denuncia (denuncia_id, nombre_archivo, ruta_archivo, tipo_archivo, tamano) 
                     VALUES (:denuncia_id, :nombre_archivo, :ruta_archivo, :tipo_archivo, :tamano)";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(":denuncia_id", $denunciaId);
            $stmt->bindParam(":nombre_archivo", $fileName);
            $stmt->bindParam(":ruta_archivo", $filePath);
            $stmt->bindParam(":tipo_archivo", $fileType);
            $stmt->bindParam(":tamano", $fileSize);
            
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            } else {
                // Si falla la inserción en la BD, eliminar el archivo subido
                unlink($filePath);
                throw new Exception("Error al guardar la información del archivo en la base de datos.");
            }
        } else {
            throw new Exception("Error al subir el archivo. Código de error: $fileError");
        }
    }
    
    // Obtener archivos por ID de denuncia
    public function obtenerPorDenuncia($denunciaId) {
        $query = "SELECT * FROM archivos_denuncia WHERE denuncia_id = ? ORDER BY fecha_subida DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$denunciaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener un archivo por ID
    public function obtenerPorId($id) {
        $query = "SELECT * FROM archivos_denuncia WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Eliminar archivo
    public function eliminar($id) {
        // Primero obtener la información del archivo
        $archivo = $this->obtenerPorId($id);
        
        if (!$archivo) {
            throw new Exception("Archivo no encontrado.");
        }
        
        // Eliminar el archivo físico
        if (file_exists($archivo['ruta_archivo'])) {
            if (!unlink($archivo['ruta_archivo'])) {
                throw new Exception("No se pudo eliminar el archivo físico.");
            }
        }
        
        // Eliminar el registro de la base de datos
        $query = "DELETE FROM archivos_denuncia WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }
    
    // Verificar si un archivo pertenece a una denuncia
    public function verificarPropiedad($archivoId, $denunciaId) {
        $query = "SELECT COUNT(*) as total FROM archivos_denuncia WHERE id = ? AND denuncia_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$archivoId, $denunciaId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] > 0;
    }
    
    // Obtener tipos MIME permitidos
    public function getTiposPermitidos() {
        return $this->allowedTypes;
    }
    
    // Obtener la extensión de un archivo a partir de su tipo MIME
    public function getExtension($mimeType) {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
        ];
        
        return $mimeMap[$mimeType] ?? 'bin';
    }
}

// Procesamiento de solicitudes
$mensaje = "";
$tipoMensaje = "info";

// Validar y obtener el ID de la denuncia
$denunciaId = filter_input(INPUT_GET, 'denuncia_id', FILTER_VALIDATE_INT);

if (!$denunciaId || $denunciaId <= 0) {
    die("<div class='alert alert-danger'>ID de denuncia no especificado o inválido. <a href='denuncias_crud.php' class='alert-link'>Volver a denuncias</a></div>");
}

// Inicializar el CRUD de archivos
$archivoCRUD = new ArchivoDenunciaCRUD($db);

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

$archivoCRUD = new ArchivoDenunciaCRUD($db);

// Manejar subida de archivos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivos'])) {
    $archivos = $_FILES['archivos'];
    $archivosSubidos = 0;
    $errores = [];
    
    // Procesar cada archivo
    for ($i = 0; $i < count($archivos['name']); $i++) {
        if ($archivos['error'][$i] === UPLOAD_ERR_OK) {
            try {
                $file = [
                    'name' => $archivos['name'][$i],
                    'type' => $archivos['type'][$i],
                    'tmp_name' => $archivos['tmp_name'][$i],
                    'error' => $archivos['error'][$i],
                    'size' => $archivos['size'][$i]
                ];
                
                $archivoCRUD->subirArchivo($denunciaId, $file);
                $archivosSubidos++;
            } catch (Exception $e) {
                $errores[] = $archivos['name'][$i] . ": " . $e->getMessage();
            }
        } elseif ($archivos['error'][$i] !== UPLOAD_ERR_NO_FILE) {
            $errores[] = $archivos['name'][$i] . ": Error al subir el archivo (Código: " . $archivos['error'][$i] . ")";
        }
    }
    
    if ($archivosSubidos > 0) {
        $mensaje = "Se subieron correctamente $archivosSubidos archivo(s).";
        $tipoMensaje = "success";
    }
    
    if (!empty($errores)) {
        $mensaje .= "<br>Errores:<br>" . implode("<br>", $errores);
        $tipoMensaje = count($errores) === $archivos['name'] ? "danger" : "warning";
    }
}

// Manejar eliminación de archivo
if (isset($_GET['eliminar'])) {
    $archivoId = $_GET['eliminar'];
    
    // Verificar que el archivo pertenezca a la denuncia
    if ($archivoCRUD->verificarPropiedad($archivoId, $denunciaId)) {
        try {
            if ($archivoCRUD->eliminar($archivoId)) {
                $mensaje = "Archivo eliminado correctamente.";
                $tipoMensaje = "success";
            } else {
                $mensaje = "Error al eliminar el archivo de la base de datos.";
                $tipoMensaje = "danger";
            }
        } catch (Exception $e) {
            $mensaje = $e->getMessage();
            $tipoMensaje = "danger";
        }
    } else {
        $mensaje = "No tienes permiso para eliminar este archivo.";
        $tipoMensaje = "danger";
    }
    
    // Redirigir para evitar reenvío del formulario
    header("Location: archivos_denuncia_crud.php?denuncia_id=$denunciaId&mensaje=" . urlencode($mensaje) . "&tipo=$tipoMensaje");
    exit();
}

// Obtener archivos de la denuncia
$archivos = $archivoCRUD->obtenerPorDenuncia($denunciaId);

// Obtener información de la denuncia para mostrar en la interfaz
$denuncia = null;
if ($denunciaId) {
    $query = "SELECT d.id, d.titulo, u.nombre as usuario_nombre 
              FROM denuncias d 
              JOIN usuarios u ON d.usuario_id = u.id 
              WHERE d.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$denunciaId]);
    $denuncia = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Archivos de la Denuncia #<?= htmlspecialchars($denunciaId) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .file-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .file-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .file-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .file-preview {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
        }
        .file-actions {
            margin-top: 10px;
        }
        .back-button {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="denuncias_crud.php?editar=<?= $denunciaId ?>" class="btn btn-outline-secondary back-button">
                    <i class="bi bi-arrow-left"></i> Volver a la denuncia
                </a>
                <h2>Archivos de la Denuncia</h2>
                <?php if ($denuncia): ?>
                    <p class="text-muted">
                        <strong>Denuncia #<?= htmlspecialchars($denuncia['id']) ?>:</strong> 
                        <?= htmlspecialchars($denuncia['titulo']) ?>
                    </p>
                <?php endif; ?>
            </div>
            <a href="#subirArchivo" class="btn btn-primary" data-bs-toggle="modal">
                <i class="bi bi-upload"></i> Subir Archivos
            </a>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show" role="alert">
                <?= $mensaje ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
        
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php if (empty($archivos)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        No hay archivos adjuntos a esta denuncia.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($archivos as $archivo): 
                    $esImagen = strpos($archivo['tipo_archivo'], 'image/') === 0;
                    $tamano = $archivo['tamano'] < 1024 * 1024 
                        ? round($archivo['tamano'] / 1024, 1) . ' KB' 
                        : round($archivo['tamano'] / (1024 * 1024), 1) . ' MB';
                    
                    // Obtener icono según el tipo de archivo
                    $icono = 'file-earmark';
                    if (strpos($archivo['tipo_archivo'], 'image/') === 0) {
                        $icono = 'file-image';
                    } elseif (strpos($archivo['tipo_archivo'], 'application/pdf') === 0) {
                        $icono = 'file-pdf';
                    } elseif (strpos($archivo['tipo_archivo'], 'application/msword') === 0 || 
                              strpos($archivo['tipo_archivo'], 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') === 0) {
                        $icono = 'file-word';
                    } elseif (strpos($archivo['tipo_archivo'], 'application/vnd.ms-excel') === 0 || 
                              strpos($archivo['tipo_archivo'], 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') === 0) {
                        $icono = 'file-excel';
                    }
                ?>
                    <div class="col">
                        <div class="card h-100 file-card">
                            <?php if ($esImagen): ?>
                                <img src="<?= htmlspecialchars($archivo['ruta_archivo']) ?>" class="card-img-top file-preview" alt="<?= htmlspecialchars($archivo['nombre_archivo']) ?>">
                            <?php else: ?>
                                <div class="text-center py-4 bg-light">
                                    <i class="bi bi-<?= $icono ?> text-primary file-icon"></i>
                                    <h5 class="card-title"><?= htmlspecialchars($archivo['nombre_archivo']) ?></h5>
                                    <p class="text-muted mb-0"><?= strtoupper(pathinfo($archivo['nombre_archivo'], PATHINFO_EXTENSION)) ?> • <?= $tamano ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">
                                    Subido: <?= date('d/m/Y H:i', strtotime($archivo['fecha_subida'])) ?>
                                </h6>
                                <p class="card-text small text-truncate" title="<?= htmlspecialchars($archivo['tipo_archivo']) ?>">
                                    <?= htmlspecialchars($archivo['tipo_archivo']) ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center file-actions">
                                    <a href="<?= htmlspecialchars($archivo['ruta_archivo']) ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       target="_blank" 
                                       title="Ver archivo">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                    <a href="<?= htmlspecialchars($archivo['ruta_archivo']) ?>" 
                                       class="btn btn-sm btn-outline-secondary" 
                                       download="<?= htmlspecialchars($archivo['nombre_archivo']) ?>" 
                                       title="Descargar archivo">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <a href="?denuncia_id=<?= $denunciaId ?>&eliminar=<?= $archivo['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('¿Está seguro de eliminar este archivo? Esta acción no se puede deshacer.')" 
                                       title="Eliminar archivo">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal para subir archivos -->
    <div class="modal fade" id="subirArchivo" tabindex="-1" aria-labelledby="subirArchivoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="subirArchivoLabel">Subir archivos</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="archivos" class="form-label">Seleccionar archivos</label>
                            <input class="form-control" type="file" id="archivos" name="archivos[]" multiple>
                            <div class="form-text">
                                Formatos permitidos: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX. Tamaño máximo: 5MB por archivo.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Subir archivos
                        </button>
                    </div>
                </form>
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
            
            // Inicializar tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
