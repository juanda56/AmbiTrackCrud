<?php
class Database {
    private $host = "localhost";
    private $db_name = "ambitrack";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                $this->username, 
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }

        return $this->conn;
    }
}

// Crear instancia de conexión
$database = new Database();
$db = $database->getConnection();

// Función para ejecutar consultas SQL desde archivo
function executeSQLFile($filePath, $db) {
    // Leer el archivo SQL
    $sql = file_get_contents($filePath);
    
    // Eliminar comentarios (opcional pero recomendado)
    $sql = preg_replace('/--.*?\r?\n/', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    // Ejecutar cada consulta por separado
    $queries = explode(';', $sql);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            try {
                $db->exec($query);
            } catch (PDOException $e) {
                // Ignorar errores de tablas que ya existen
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "Error al ejecutar consulta: " . $e->getMessage() . "<br>";
                }
            }
        }
    }
}

// Si se solicita la creación de tablas
if (isset($_GET['crear_tablas']) && $_GET['crear_tablas'] == 'si') {
    try {
        // Iniciar transacción
        $db->beginTransaction();
        
        // Ejecutar script SQL
        executeSQLFile(__DIR__ . '/database.sql', $db);
        
        // Confirmar cambios
        $db->commit();
        echo "<div class='alert alert-success'>Tablas creadas exitosamente en la base de datos 'ambitrack'</div>";
    } catch (Exception $e) {
        // Revertir en caso de error
        $db->rollBack();
        echo "<div class='alert alert-danger'>Error al crear las tablas: " . $e->getMessage() . "</div>";
    }
}
?>
