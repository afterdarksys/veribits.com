<?php
namespace VeriBits\Utils;

class Database {
    private static ?\PDO $connection = null;
    private static array $config = [];
    private static int $queryCount = 0;
    private static int $maxQueriesPerConnection = 1000;

    // Whitelist of allowed table names to prevent SQL injection
    private static array $allowedTables = [
        'users', 'api_keys', 'verifications', 'webhooks', 'webhook_deliveries',
        'billing_plans', 'subscriptions', 'usage_logs', 'audit_logs',
        'password_resets', 'email_verifications', 'sessions', 'anonymous_scans',
        'quotas', 'rate_limits', 'keystore_conversions'
    ];

    /**
     * Validate table name against whitelist
     * @throws \InvalidArgumentException if table name is not allowed
     */
    private static function validateTableName(string $table): void {
        if (!in_array($table, self::$allowedTables, true)) {
            Logger::security('Attempted SQL injection via invalid table name', [
                'table' => $table,
                'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
            ]);
            throw new \InvalidArgumentException("Invalid table name: $table");
        }
    }

    /**
     * Validate column/field names to prevent SQL injection
     * Only allows alphanumeric characters and underscores
     * @throws \InvalidArgumentException if field name is invalid
     */
    private static function validateFieldName(string $field): void {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
            Logger::security('Attempted SQL injection via invalid field name', [
                'field' => $field,
                'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
            ]);
            throw new \InvalidArgumentException("Invalid field name: $field");
        }
    }

    /**
     * Validate all field names in an array
     */
    private static function validateFieldNames(array $fields): void {
        foreach ($fields as $field) {
            self::validateFieldName($field);
        }
    }

    public static function connect(): \PDO {
        // Recycle connection after max queries to prevent connection leaks
        if (self::$connection !== null && self::$queryCount >= self::$maxQueriesPerConnection) {
            Logger::info('Recycling database connection', ['queries_executed' => self::$queryCount]);
            self::$connection = null;
            self::$queryCount = 0;
        }

        if (self::$connection !== null) {
            try {
                // Health check - verify connection is still alive
                self::$connection->query('SELECT 1');
                return self::$connection;
            } catch (\PDOException $e) {
                Logger::warning('Database connection unhealthy, reconnecting', [
                    'error' => $e->getMessage()
                ]);
                self::$connection = null;
                self::$queryCount = 0;
            }
        }

        $host = Config::getRequired('DB_HOST');
        $port = Config::getInt('DB_PORT', 5432);
        $dbname = Config::getRequired('DB_DATABASE');
        $username = Config::getRequired('DB_USERNAME');
        $password = Config::getRequired('DB_PASSWORD');
        $driver = Config::get('DB_DRIVER', 'pgsql');

        $dsn = "$driver:host=$host;port=$port;dbname=$dbname";

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_PERSISTENT => false, // Disabled for better connection management in containers
            \PDO::ATTR_TIMEOUT => 5, // 5 second connection timeout
        ];

        // PostgreSQL-specific options
        if ($driver === 'pgsql') {
            $options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;
        }

        try {
            self::$connection = new \PDO($dsn, $username, $password, $options);

            // Set statement timeout to prevent long-running queries
            if ($driver === 'pgsql') {
                self::$connection->exec("SET statement_timeout = '30000'"); // 30 seconds
            }

            Logger::debug('Database connection established', [
                'host' => $host,
                'database' => $dbname
            ]);

            return self::$connection;
        } catch (\PDOException $e) {
            Logger::error('Database connection failed', [
                'error' => $e->getMessage(),
                'host' => $host,
                'database' => $dbname
            ]);
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function getConnection(): \PDO {
        return self::connect();
    }

    public static function query(string $sql, array $params = []): \PDOStatement {
        $pdo = self::connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        self::$queryCount++; // Track queries for connection recycling
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    public static function insert(string $table, array $data): string {
        self::validateTableName($table);
        $fields = array_keys($data);
        self::validateFieldNames($fields);

        $placeholders = array_map(fn($field) => ":$field", $fields);

        $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ") RETURNING id";
        $stmt = self::query($sql, $data);

        return $stmt->fetchColumn();
    }

    public static function update(string $table, array $data, array $where): int {
        self::validateTableName($table);
        self::validateFieldNames(array_keys($data));
        self::validateFieldNames(array_keys($where));

        $setClause = implode(', ', array_map(fn($field) => "$field = :$field", array_keys($data)));
        $whereClause = implode(' AND ', array_map(fn($field) => "$field = :where_$field", array_keys($where)));

        $params = array_merge($data, array_combine(
            array_map(fn($key) => "where_$key", array_keys($where)),
            array_values($where)
        ));

        $sql = "UPDATE $table SET $setClause WHERE $whereClause";
        $stmt = self::query($sql, $params);

        return $stmt->rowCount();
    }

    public static function delete(string $table, array $where): int {
        self::validateTableName($table);
        self::validateFieldNames(array_keys($where));

        $whereClause = implode(' AND ', array_map(fn($field) => "$field = :$field", array_keys($where)));
        $sql = "DELETE FROM $table WHERE $whereClause";
        $stmt = self::query($sql, $where);

        return $stmt->rowCount();
    }

    public static function exists(string $table, array $where): bool {
        self::validateTableName($table);
        self::validateFieldNames(array_keys($where));

        $whereClause = implode(' AND ', array_map(fn($field) => "$field = :$field", array_keys($where)));
        $sql = "SELECT 1 FROM $table WHERE $whereClause LIMIT 1";
        $stmt = self::query($sql, $where);

        return $stmt->fetchColumn() !== false;
    }

    public static function count(string $table, array $where = []): int {
        self::validateTableName($table);
        if (!empty($where)) {
            self::validateFieldNames(array_keys($where));
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', array_map(fn($field) => "$field = :$field", array_keys($where)));
        $sql = "SELECT COUNT(*) FROM $table $whereClause";
        $stmt = self::query($sql, $where);

        return (int)$stmt->fetchColumn();
    }

    public static function beginTransaction(): void {
        self::connect()->beginTransaction();
    }

    public static function commit(): void {
        self::connect()->commit();
    }

    public static function rollback(): void {
        self::connect()->rollBack();
    }
}