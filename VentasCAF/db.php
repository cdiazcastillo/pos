<?php
// config/db.php
require_once __DIR__ . '/bootstrap.php'; // Incluir bootstrap para cargar variables de entorno

class Database {
    // Database connection parameters (ahora se inicializan desde el entorno)
    private static $host;
    private static $dbname;
    private static $username;
    private static $password;

    // The single instance of the class
    private static $instance = null;
    // The PDO connection object
    private $conn;

    /**
     * Private constructor to prevent direct creation of object.
     * Connects to the database.
     */
    private function __construct() {
        // Asignar los valores desde las variables de entorno
        self::$host = $_ENV['DB_HOST'] ?? 'localhost';
        self::$dbname = $_ENV['DB_NAME'] ?? 'ventascaf_db';
        self::$username = $_ENV['DB_USER'] ?? 'root';
        self::$password = $_ENV['DB_PASS'] ?? '';

        try {
            $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, self::$username, self::$password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // En un entorno de producción, registrarías este error y mostrarías un mensaje genérico
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Clones are not allowed for singletons.
     */
    private function __clone() { }

    /**
     * Get the singleton instance of the Database class.
     *
     * @return Database The single instance.
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the PDO connection object.
     *
     * @return PDO The PDO connection object.
     */
    public function getConnection() {
        return $this->conn;
    }

    /**
     * A helper method for preparing and executing a SELECT query.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params The parameters to bind to the query.
     * @param bool $fetchAll If true, fetches all results, otherwise fetches one.
     * @return mixed The result set or single row.
     */
    public function query(string $sql, array $params = [], bool $fetchAll = false) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            if ($fetchAll) {
                return $stmt->fetchAll();
            }
            return $stmt->fetch();
        } catch (PDOException $e) {
            // Manejar error de consulta
            error_log("Query failed: " . $e->getMessage()); // Registrar el error
            return false;
        }
    }

    /**
     * A helper method for executing INSERT, UPDATE, or DELETE queries.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params The parameters to bind to the query.
     * @return int The number of affected rows.
     */
    public function execute(string $sql, array $params = []): int {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            // Manejar error de ejecución
            error_log("Execute failed: " . $e->getMessage()); // Registrar el error
            return 0;
        }
    }
    
    /**
     * A helper method to begin a transaction.
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    /**
     * A helper method to commit a transaction.
     */
    public function commit() {
        return $this->conn->commit();
    }

    /**
     * A helper method to roll back a transaction.
     */
    public function rollBack() {
        return $this->conn->rollBack();
    }

    /**
     * Gets the ID of the last inserted row.
     */
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    public static function getHost() {
        // Asegurarse de que las variables de entorno estén cargadas
        require_once __DIR__ . '/bootstrap.php';
        return $_ENV['DB_HOST'] ?? 'localhost';
    }

    public static function getDbname() {
        require_once __DIR__ . '/bootstrap.php';
        return $_ENV['DB_NAME'] ?? 'ventascaf_db';
    }

    public static function getUsername() {
        require_once __DIR__ . '/bootstrap.php';
        return $_ENV['DB_USER'] ?? 'root';
    }

    public static function getPassword() {
        require_once __DIR__ . '/bootstrap.php';
        return $_ENV['DB_PASS'] ?? '';
    }
}