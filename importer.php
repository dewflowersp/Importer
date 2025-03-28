<?php

include_once 'env.php';

define('MYSQL_DATETIME', date("Y-m-d H:i:s"));

/**
 * Class Importer
 * Handles importing data from a CSV file within a ZIP archive into a MySQL database.
 *
 * @property mysqli $con - MySQL connection object
 * @property string $today - Date for file naming
 * @property string $zip - ZIP file name
 * @property string $csv - CSV file name
 */
class Importer {
    private $con;  // MySQL connection object
    private $today; // Date for file naming
    private $zip;   // ZIP file name
    private $csv;   // CSV file name

    /**
     * Constructor. Establishes a database connection and sets up file names.
     */
    public function __construct() {
        // Establish a connection to the MySQL database.
        $this->con = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD);

        // Handle connection errors.
        if (!$this->con) {
            file_put_contents(LOG_FILE, 'mysqli_connect: ' . mysqli_error($this->con) . MYSQL_DATETIME . PHP_EOL, FILE_APPEND);
            exit(1);
        }

        // Select the database.
        if (!mysqli_select_db($this->con, DB_NAME)) {
            file_put_contents(LOG_FILE, 'mysqli_select_db: ' . mysqli_error($this->con) . MYSQL_DATETIME . PHP_EOL, FILE_APPEND);
            exit(1);
        }
        // Calculate the date for the previous day.
        $this->today = date("Ymd", strtotime('-1 day'));
        // Construct the ZIP and CSV filenames.
        $this->zip = "acquisition_{$this->today}.zip";
        $this->csv = "acquisition_{$this->today}.csv";
    }

    /**
     * Recursively deletes a file or directory.
     * @param string $str The path to the file or directory.
     * @return bool True on success, false on failure.
     */
    private function _deleteAll($str) {
        // If it's a file, attempt to delete it.
        if (is_file($str)) {
            return unlink($str);
        }
        // If it's a directory.
        elseif (is_dir($str)) {
            // Get a list of the files in this directory.
            $scan = glob(rtrim($str, '/') . '/*');
            // Loop through the list of files and recursively delete them.
            foreach ($scan as $index => $path) {
                $this->_deleteAll($path);
            }
            // Remove the directory itself.
            return @rmdir($str);
        }
    }

    /**
     * Initializes the import process by extracting the CSV from the ZIP and importing it into the database.
     */
    public function init_import() {
        // Check if the ZIP file exists.
        if (!file_exists(ZIP_PATH . $this->zip)) {
            file_put_contents(LOG_FILE, ZIP_PATH . $this->zip . ' not found. at:' . MYSQL_DATETIME . PHP_EOL, FILE_APPEND);
            exit(1);
        }

        // Open the ZIP archive.
        $zip = new ZipArchive;
        $res = $zip->open(ZIP_PATH . $this->zip);
        // Check if the ZIP was opened successfully.
        if ($res === TRUE) {
            // Extract the CSV file.
            $zip->extractTo(CSV_PATH . $this->csv);
            $zip->close();
            // Import the CSV data into the database.
            $this->csv_to_database(CSV_PATH . DIRECTORY_SEPARATOR . $this->csv);
            file_put_contents(LOG_FILE, CSV_PATH . $this->csv . ' imported succesfully at:' . MYSQL_DATETIME . PHP_EOL, FILE_APPEND);
        } else {
            file_put_contents(LOG_FILE, 'unzip failed code: ' . $res . ' at:' . MYSQL_DATETIME . PHP_EOL, FILE_APPEND);
            exit(1);
        }
    }

    /**
     * Imports data from a CSV file into the database.
     * @param string $file_name The path to the CSV file.
     */
    public function csv_to_database($file_name) {
        // Set maximum execution time and memory limit for potentially large files.
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '1024M');
        //date_default_timezone_set('UTC');
        // Check if the file exists.
        if (!file_exists($file_name)) {
            file_put_contents(LOG_FILE, $file_name . ' not found. at:' . MYSQL_DATETIME . PHP_EOL, FILE_APPEND);
            exit(1);
        }
        // Handle cases where the filename might be a directory.
        if (!is_file($file_name)) {
            if (!file_exists($file_name . DIRECTORY_SEPARATOR . $this->csv) || !is_file($file_name . DIRECTORY_SEPARATOR . $this->csv)) {
                file_put_contents(LOG_FILE, $file_name . DIRECTORY_SEPARATOR . $this->csv . ' not found. at:' . MYSQL_DATETIME . PHP_EOL, FILE_APPEND);
                exit(1);
            } else {
                $file_name = $file_name . DIRECTORY_SEPARATOR . $this->csv;
            }
        }
        // Open the CSV file for reading.
        $csvFile = fopen($file_name, 'r');

        // Skip the first line (headers).
        $line = fgetcsv($csvFile);

        mysqli_query($this->con, "SET NAMES UTF8");
        // Define the mapping between database columns and CSV headers.
        $fields = [
            'provider_name' => 'Provider name',
            'verify' => 'Verify',
            'application_device' => 'Application Device',
            'content_name' => 'Content Name',
            'contract_member_status' => 'Contract Member Status',
            'payment_method' => 'Payment Method',
            'application_date' => 'Application date',
            'withdrawal_date' => 'Withdrawal Date',
            'shop_id' => 'Shop Id',
            'agency_group_id' => 'Agency Group Id',
            'staff_id' => 'Staff Id',
            'dealer_id' => 'Dealer Id'
        ];
        $columns = implode(',', array_keys($fields));

        // Start building the SQL INSERT statement.
        $sql = $raw_sql = "INSERT INTO pmc_db_tmp ({$columns},created_at) VALUES";
        $now = date("Y-m-d H:i:s");

        // Parse data from the CSV file line by line.
        $i = 0;
        while (($data = fgetcsv($csvFile)) !== FALSE) {
            // Execute the query in batches of 500 to avoid memory issues.
            if ($i % 500 == 0) {
                mysqli_query($this->con, $sql . ';');
                $sql = $raw_sql;
            } else if ($i > 0) {
                $sql .= ',';
            }
            $str = '';
            $j = 0;
            // Iterate through the defined fields.
            foreach ($fields as $key => $value) {
                // Handle date fields.
                if (in_array($key, ['application_date', 'withdrawal_date'])) {
                    $row = strftime('%Y-%m-%d %H:%M:%S', strtotime($data[$j]));
                } else {
                    // Clean the data by removing single quotes, commas, and double quotes.
                    $row = str_replace("'", '', str_replace(',', '', str_replace('"', '', @$data[$j])));
                }
                $str .= ($str == '' ? '' : ",") . "'{$row}'";
                $j++;
            }
            $str .= ",'{$now}'";
            $sql .= "({$str})";
            $i++;
        }
        // Execute the final INSERT query if there's any remaining data.
        if ($i) {
            mysqli_query($this->con, $sql) or die(mysqli_error($this->con));
        }
        // Close the opened CSV file.
        fclose($csvFile);

        // Replace the data in the main table with the data from the temporary table.
        mysqli_query($this->con, "REPLACE INTO pmc_db (SELECT * FROM pmc_db_tmp)");
        // Truncate the temporary table.
        mysqli_query($this->con, 'TRUNCATE TABLE `pmc_db_tmp`;');

        // Retrieve edit logs.
        $result = mysqli_query($this->con, 'SELECT * FROM pmc_db_edit_log');
        // Apply updates based on the edit logs.
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $updated_data = json_decode($row['modified_data']);
                $update_query = '';
                foreach ($updated_data as $key => $value) {
                    $update_query .= (empty($update_query) ? '' : ',') . "{$key}='{$value}'";
                }
                if (!empty($update_query)) {
                    mysqli_query($this->con, "UPDATE pmc_db SET {$update_query} WHERE id=" . $row['pmc_db_id']);
                }
            }
        }
        // Delete records marked for deletion.
        mysqli_query($this->con, 'DELETE FROM pmc_db WHERE id IN(SELECT id FROM pmc_db_deleted)');
    }

    /**
     * Destructor. Closes the database connection and deletes the extracted CSV and ZIP files.
     */
    public function __destruct() {
        $this->con->close();
        $this->_deleteAll(ZIP_PATH . $this->zip);
        $this->_deleteAll(CSV_PATH . $this->csv);
    }

}

// Create an instance of the Importer class and start the import process.
$importer = new Importer;
$importer->init_import();