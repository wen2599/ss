<?php
require_once 'config.php';

/**
 * Establishes a connection to the SQLite database and returns the PDO object.
 *
 * This function uses a static variable to ensure that the database connection is
 * established only once per request. If the database file does not exist,
 * it will be created and initialized with the schema from `schema.sql`.
 *
 * @return PDO The PDO database connection object.
 */
function get_db() {
    static $db = null;
    if ($db === null) {
        try {
            $db_path = DB_PATH;
            $needs_init = !file_exists($db_path);

            // Create a new PDO connection to the SQLite database.
            // This will create the file if it doesn't exist.
            $db = new PDO('sqlite:' . $db_path);

            // Set PDO attributes. We want it to throw exceptions on errors.
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Enable foreign key constraints, as it's off by default in SQLite.
            $db->exec('PRAGMA foreign_keys = ON;');

            if ($needs_init) {
                // The database file did not exist, so we need to initialize the schema.
                $schema_sql = file_get_contents(__DIR__ . '/schema.sql');
                if ($schema_sql === false) {
                    throw new Exception("Could not read schema.sql file.");
                }
                // Execute the entire schema SQL script.
                $db->exec($schema_sql);
            }
        } catch (PDOException $e) {
            // Handle PDO exceptions (e.g., connection errors) gracefully.
            // If send_json_error is not available, the script will halt with a generic error.
            // This is a fallback for critical, early-stage failures.
            http_response_code(500);
            error_log('DB Error: ' . $e->getMessage());
            exit(json_encode(['success' => false, 'message' => 'A critical database error occurred.']));

        } catch (Exception $e) {
            // Handle other exceptions (e.g., file read error).
            http_response_code(500);
            error_log('DB Init Error: ' . $e->getMessage());
            exit(json_encode(['success' => false, 'message' => 'A critical initialization error occurred.']));
        }
    }
    return $db;
}
?>
