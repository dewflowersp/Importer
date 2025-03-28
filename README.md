# Importer Class Documentation

## Overview

The `Importer` class is designed to automate the daily import of data from a CSV file into a MySQL database. This process is triggered by a third-party application that pushes the data to the server as a ZIP archive via SFTP. The script is configured to process the data for the previous day.

## Functionality

The `Importer` class handles the following key steps:

1.  **Database Connection:** Establishes a connection to the MySQL database using credentials defined in the `env.php` configuration file. The script will terminate if the database connection fails or if the specified database cannot be selected.
2.  **File Naming:** Calculates the date for the previous day and constructs the expected filenames for both the incoming ZIP archive and the extracted CSV file based on this date. This ensures the script processes the correct daily data.
3.  **ZIP File Handling:** Checks for the existence of the expected ZIP file. If the file is not found, an error is logged, and the script exits. If found, it attempts to open the archive and extract the CSV file.
4.  **CSV Data Import:** Reads the extracted CSV file and imports the data into the MySQL database. This process includes:
    * Setting appropriate execution time and memory limits to handle potentially large files.
    * Skipping the header row of the CSV file.
    * Mapping CSV column headers to corresponding database columns.
    * Cleaning and formatting the data before insertion (e.g., removing special characters, reformatting dates).
    * Inserting data into a temporary table (`pmc_db_tmp`) in batches for performance optimization.
    * Replacing the data in the main table (`pmc_db`) with the contents of the temporary table.
    * Truncating the temporary table after the import is complete.
    * Applying updates from an edit log table (`pmc_db_edit_log`) to the main table.
    * Deleting records from the main table that are marked for deletion in a separate table (`pmc_db_deleted`).
5.  **Cleanup:** After the import process, the script closes the database connection and deletes both the extracted CSV file and the original ZIP archive from the server to manage disk space.
6.  **Error Logging:** Throughout the process, the script logs important events and errors to a specified log file, aiding in monitoring and troubleshooting.

## Files

* **`env.php`:** Configuration file containing environment-specific settings such as database credentials, file paths for ZIP archives and CSV files, and the log file location.
* **`importer.php`:** The main script containing the `Importer` class and the logic for initiating the import process.

## Setup

1.  **Configuration:** Ensure the `env.php` file is correctly configured for your environment (development, testing, or production). This includes setting the correct paths for ZIP files, CSV storage, log files, and database connection details.
2.  **SFTP Setup:** Verify that the third-party application is correctly configured to push the daily ZIP files to the location specified in the `ZIP_PATH` constant within `env.php`.
3.  **Database Schema:** Ensure the MySQL database has the necessary tables (`pmc_db`, `pmc_db_tmp`, `pmc_db_edit_log`, `pmc_db_deleted`) with the correct schema to accommodate the imported data. The column names in the `pmc_db` table should correspond to the keys defined in the `$fields` array within the `csv_to_database` function in `importer.php`.
4.  **Permissions:** Ensure the PHP script has the necessary permissions to read the ZIP files, write the extracted CSV files, and write to the log file. Also, ensure the PHP environment can connect to the MySQL database.

## Usage

The `importer.php` script is intended to be executed daily, likely via a cron job or a similar scheduling mechanism. When executed, it will automatically look for the ZIP file for the previous day, extract the data, and import it into the database.

## Error Handling

The script includes basic error handling, such as checking for the existence of the ZIP file and handling database connection errors. Errors encountered during the process are logged to the file specified by the `LOG_FILE` constant in `env.php`.

## Dependencies

* **PHP:** The script is written in PHP and requires a PHP installation on the server.
* **MySQL Extension:** The PHP MySQL extension (`mysqli`) is required to connect to and interact with the MySQL database.
* **Zip Extension:** The PHP Zip extension is required to open and extract the contents of the ZIP archive.

## Notes

* The script assumes that the ZIP file contains a single CSV file with a specific naming convention based on the previous day's date.
* The `$fields` array in the `csv_to_database` function defines the expected mapping between the CSV headers and the database columns. Ensure this mapping is correct based on the structure of the incoming CSV file.
* The script uses temporary table (`pmc_db_tmp`) to ensure data integrity during the import process.
* The script also handles updates and deletions based on data in `pmc_db_edit_log` and `pmc_db_deleted` tables, indicating that there might be a mechanism for managing updates and deletions to the imported data.
