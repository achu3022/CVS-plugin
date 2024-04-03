<?php
// Check if WordPress is loaded
if (!defined('ABSPATH')) {
    exit;
}

// Include the necessary PhpSpreadsheet library
require_once 'vendor/autoload.php';

// Plugin Functions
function course_certificate_add_course_certificate($code, $name, $course, $hours, $cert_link, $award_date, $editid)
{
    global $wpdb;

    // Check for duplicates based on certificate code and student name
    $existing_entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM smec_course_certificates WHERE certificate_code = %s AND student_name = %s", $code, $name));

    if ($existing_entry) {
        // If entry exists, update the existing record
        $result = $wpdb->update(
            'smec_course_certificates',
            array(
                'course_name'  => $course,
                'course_hours' => $hours,
                'cert_link'    => $cert_link,
                'award_date'   => $award_date,
            ),
            array('id' => $existing_entry->id)
        );

        if ($result === false) {
            // Log or display the update error
            error_log('Update error: ' . $wpdb->last_error);
        }
    } else {
        // If entry doesn't exist, perform an insert
        $result = $wpdb->insert(
            'smec_course_certificates',
            array(
                'certificate_code' => $code,
                'student_name'     => $name,
                'course_name'      => $course,
                'course_hours'     => $hours,
                'cert_link'        => $cert_link,
                'award_date'       => $award_date,
            )
        );

        if ($result === false) {
            // Log or display the insert error
            error_log('Insert error: ' . $wpdb->last_error);
        }
    }

    return $result;
}

function course_certificate_delete_course_certificate($editid)
{
    global $wpdb;
    $result = false;
    if (is_numeric($editid) && $editid != '') {
        $result = $wpdb->delete('smec_course_certificates', array('id' => $editid));
    }
    return $result;
}

// Add the following function for bulk adding data from Excel
function course_certificate_bulk_add_from_excel($file_path)
{
    global $wpdb;

    // Load Excel file
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
    $sheet = $spreadsheet->getActiveSheet();

    // Set a flag to skip the first row
    $skipFirstRow = true;

    // Iterate through rows and add data to the database
    foreach ($sheet->getRowIterator() as $row) {
        // Skip the first row
        if ($skipFirstRow) {
            $skipFirstRow = false;
            continue;
        }

        // Use getCell() to fetch cell data
        $data = $row->getCellIterator();

        // Assuming the data order in Excel is: certificate_code, student_name, course_name, course_hours, cert_link, award_date
        $rowData = array();
        foreach ($data as $cell) {
            $rowData[] = $cell->getValue();
        }

        list($code, $name, $course, $hours, $cert_link, $award_date_excel) = $rowData;

        // Convert Excel serial number to Unix timestamp
        $award_date_timestamp = ($award_date_excel - 25569) * 86400; // Convert Excel date to Unix timestamp

        // Convert Unix timestamp to MySQL date format
        $award_date = date('Y-m-d', $award_date_timestamp);

        // Call the existing function to add or update data in the database
        course_certificate_add_course_certificate($code, $name, $course, $hours, $cert_link, $award_date, '');
    }
}

// Add the following code to handle form submission and call the bulk add function
if (isset($_POST['add_excel'])) {
    // Ensure the nonce is valid
    //if (function_exists('wp_verify_nonce') && isset($_POST['course_nonce']) && wp_verify_nonce($_POST['course_nonce'], 'admin_certificate_ui')) {
        // Check if a file was uploaded
        if (isset($_FILES['file_name']) && $_FILES['file_name']['error'] == 0) {
            $file_path = $_FILES['file_name']['tmp_name'];

            // Call the function to bulk add data from Excel
            course_certificate_bulk_add_from_excel($file_path);
        } else {
            // Handle the case when no file is uploaded
            // You may display an error message or take appropriate action
            error_log('No file uploaded');
        }
    //} else {
        // Handle invalid nonce
        // You may display an error message or take appropriate action
        error_log('Invalid nonce');
   // }
}
?>
