<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = "localhost";
$user = "iris";
$password = "uh(*6l7AQJ@qM.@7";
$database = "invoices";
$base_url = "https://inv.nuvoco.in/invoices";
$temp_dir = "temp_invoices";
$output_dir = "output";

// Function to get invoice names for a plant code
function getInvoicesForPlant($plant_code) {
    global $host, $user, $password, $database;

    $conn = new mysqli($host, $user, $password, $database);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT invoiceName FROM tbl_invoices WHERE dispatchFromLocation = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $plant_code);
    $stmt->execute();
    $result = $stmt->get_result();

    $invoices = [];
    while ($row = $result->fetch_assoc()) {
        $invoices[] = $row['invoiceName'];
    }

    $stmt->close();
    $conn->close();

    return $invoices;
}

// Function to download an invoice
function downloadInvoice($url, $save_path) {
    $content = file_get_contents($url);
    if ($content === FALSE) {
        echo "Error downloading $url\n";
        return false;
    }

    if (!file_put_contents($save_path, $content)) {
        echo "Error saving $save_path\n";
        return false;
    }

    echo "Downloaded: $save_path\n";
    return true;
}

// Function to zip files for a given plant code
function zipFilesForPlant($plant_code) {
    global $base_url, $temp_dir, $output_dir;

    // Get invoice names
    $invoice_names = getInvoicesForPlant($plant_code);

    if (empty($invoice_names)) {
        echo "No invoices found for plant code: $plant_code\n";
        return;
    }

    // Ensure output and temporary directories exist
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir);
    }
    if (!is_dir($output_dir)) {
        mkdir($output_dir);
    }

    $zip_filename = "plant_{$plant_code}_invoices.zip";
    $zip_path = "$output_dir/$zip_filename";

    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        die("Could not create ZIP file: $zip_path\n");
    }

    foreach ($invoice_names as $invoice_name) {
        $file_url = "$base_url/$invoice_name";
        $local_path = "$temp_dir/$invoice_name";

        // Download the invoice
        if (downloadInvoice($file_url, $local_path)) {
            $zip->addFile($local_path, $invoice_name);
            echo "Added $local_path to ZIP.\n";
        } else {
            echo "Skipping $file_url due to download error.\n";
        }
    }

    $zip->close();
    echo "ZIP file created: $zip_path\n";

    // Cleanup temporary files
    cleanupTempFiles();
}

// Function to clean up temporary files
function cleanupTempFiles() {
    global $temp_dir;

    if (is_dir($temp_dir)) {
        $files = scandir($temp_dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                unlink("$temp_dir/$file");
            }
        }
        rmdir($temp_dir);
        echo "Temporary files cleaned up.\n";
    }
}

// Example usage
if (php_sapi_name() == "cli") {
    echo "Enter the plant code: ";
    $plant_code = trim(fgets(STDIN));
    zipFilesForPlant($plant_code);
}
?>
