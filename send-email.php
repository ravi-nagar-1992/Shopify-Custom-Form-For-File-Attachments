<?php
// --- CORS Setup ---
header("Access-Control-Allow-Origin: https://sypnosis-checkout-ui-extension.myshopify.com");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Debugging (optional) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Main Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storeEmail = "sypnosistechnologies@gmail.com"; // Replace with your email
    $from = "no-reply@sypnosistechnologies.com";

    // Get form fields
    $customerName = isset($_POST['name']) ? trim($_POST['name']) : '';
    $customerEmail = isset($_POST['email']) ? trim($_POST['email']) : '';
    $customerAddress = isset($_POST['address']) ? trim($_POST['address']) : '';
    $fileOk = isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK;

    // Validate
    if (!$fileOk || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL) || empty($customerName) || empty($customerAddress)) {
        http_response_code(400);
        echo json_encode(['message' => 'Missing or invalid data.']);
        exit();
    }

    // --- Prepare Attachment ---
    $fileTmp = $_FILES['file']['tmp_name'];
    $fileName = basename($_FILES['file']['name']);
    $fileType = mime_content_type($fileTmp);
    $fileContent = chunk_split(base64_encode(file_get_contents($fileTmp)));
    $boundary = md5(time());

    // --- Build Email with Attachment ---
    function buildEmailBody($boundary, $textMessage, $fileType, $fileName, $fileContent) {
        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=\"utf-8\"\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $textMessage . "\r\n\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: {$fileType}; name=\"{$fileName}\"\r\n";
        $body .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= $fileContent . "\r\n";
        $body .= "--{$boundary}--";
        return $body;
    }

    // Email headers
    $headers = "From: $from\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

    // Messages
    $adminMessage = "New customer submission:\n"
        . "Name: $customerName\n"
        . "Email: $customerEmail\n"
        . "Address: $customerAddress";

    $customerMessage = "Hi $customerName,\n\n"
        . "Thank you for your submission! We received the following details:\n"
        . "Name: $customerName\n"
        . "Email: $customerEmail\n"
        . "Address: $customerAddress\n\n"
        . "Your uploaded file is attached.";

    // Build email bodies
    $adminBody = buildEmailBody($boundary, $adminMessage, $fileType, $fileName, $fileContent);
    $customerBody = buildEmailBody($boundary, $customerMessage, $fileType, $fileName, $fileContent);

    // Send both emails
    $mailToAdmin = mail($storeEmail, "New Submission from $customerName", $adminBody, $headers);
    $mailToCustomer = mail($customerEmail, "Thanks for your submission, $customerName!", $customerBody, $headers);

    if ($mailToAdmin && $mailToCustomer) {
        echo json_encode(['message' => 'Emails sent successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to send one or both emails.']);
    }
}
?>