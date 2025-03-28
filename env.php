<?php

// Define the environment setting: 'development', 'testing', or 'production'
define('ENVIRONMENT', 'development');

// Define the path where ZIP files are stored. Replace with appropriate path.
define('ZIP_PATH', '/path/to/your/zip/files/');

// Define the path where CSV files are stored. Replace with appropriate path.
define('CSV_PATH', '/path/to/your/csv/uploads/');

// Define the log file path for logging purposes. Replace with appropriate path.
define('LOG_FILE', '/path/to/your/logs/importer.log');

// Define the database connection details
define('DB_HOST', 'localhost');  // Database host
define('DB_USER', 'root');       // Database username
define('DB_PASSWORD', 'your_password_here');  // Database password (should be replaced with an actual password or a secure method)
define('DB_NAME', 'your_database_name_here');  // Database name (should be replaced with the actual database name)

// Set error reporting level based on the environment setting
switch (ENVIRONMENT) {
    case 'development':
        // Display all errors for development purposes
        error_reporting(-1);
        ini_set('display_e rrors', 1);  // Show errors in the browser
        break;
    case 'testing':
    case 'production':
        // Suppress display of errors in testing and production, but log them
        ini_set('display_errors', 0);  // Do not show errors on the screen
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED); // Log only critical errors
        break;
}
