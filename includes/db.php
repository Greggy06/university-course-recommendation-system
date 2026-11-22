<?php
/**
 * Database Connection using PDO (SEC-02, SEC-08, NFR-05)
 */

require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log real error internally
            error_log("Database Connection Error: " . $e->getMessage());

            // NFR-05: Show friendly error page if database is down, not code errors
            http_response_code(500);
            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>System Notice - University Course Recommendation System</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1rem; }
                    .error-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); max-width: 500px; width: 100%; padding: 2rem; text-align: center; }
                    .error-card h1 { font-size: 1.5rem; color: #0f172a; margin-top: 0; }
                    .error-card p { color: #64748b; line-height: 1.6; margin-bottom: 1.5rem; }
                    .btn { display: inline-block; background-color: #2563eb; color: white; padding: 0.6rem 1.25rem; border-radius: 6px; text-decoration: none; font-weight: 500; }
                    .btn:hover { background-color: #1d4ed8; }
                </style>
            </head>
            <body>
                <div class="error-card">
                    <h1>Service Temporarily Unavailable</h1>
                    <p>We are currently experiencing difficulty connecting to the database service. Please check back in a few moments or ensure your local MySQL server is running in XAMPP.</p>
                    <a href="' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES, 'UTF-8') . '" class="btn">Try Again</a>
                </div>
            </body>
            </html>';
            exit;
        }
    }

    return $pdo;
}
