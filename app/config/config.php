<?php
// Raptor CRM Configuration
//
// Values are read from environment variables first (set these on the VPS via
// Apache SetEnv, a .env loader, or the shell), falling back to sensible local
// Docker defaults. Never commit real production credentials — set them in the
// server environment. See config.sample.php for the full list.

if (!function_exists('env')) {
    // Load .env file if present
    $envFile = dirname(dirname(__DIR__)) . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v, " \t\n\r\0\x0B\"'");
                if (getenv($k) === false) {
                    putenv("$k=$v");
                    $_ENV[$k] = $v;
                }
            }
        }
    }

    /** Read an env var with a default. */
    function env(string $key, $default = null) {
        $val = getenv($key);
        return ($val === false || $val === '') ? $default : $val;
    }
}

// Database Settings
define('DB_HOST', env('DB_HOST', 'db'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', 'rootpassword'));
define('DB_NAME', env('DB_NAME', 'raptor_crm_db'));

// App Paths & URLs
define('APPROOT', dirname(dirname(__FILE__)));
define('URLROOT', env('URLROOT', 'http://localhost:8080/public'));
define('SITENAME', env('SITENAME', 'Raptor CRM'));

// Environment: 'development' shows errors, 'production' hides them.
define('APP_ENV', env('APP_ENV', 'development'));

// Session Settings
define('SESSION_TIMEOUT', (int) env('SESSION_TIMEOUT', 1800)); // 30 minutes in seconds

// File storage (selfies, proof uploads). STORAGE_PATH is a private directory
// OUTSIDE the web root; files are served through a controlled endpoint, never
// linked directly. On a VPS point this at e.g. /var/raptor/storage.
define('STORAGE_PATH', env('STORAGE_PATH', APPROOT . '/../storage'));
define('MAX_UPLOAD_BYTES', (int) env('MAX_UPLOAD_BYTES', 5 * 1024 * 1024)); // 5 MB

// Error display driven by environment.
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
