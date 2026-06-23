<?php
/**
 * Philly Chef Showdown — competitor application handler
 * Logs to CSV, emails MYB + Katika team, sends applicant auto-reply.
 */

// ===== CONFIG =====
$STORAGE_DIR    = '/home/phillych/private';
$CSV_FILE       = $STORAGE_DIR . '/applications.csv';
$LOG_FILE       = $STORAGE_DIR . '/submit.log';
$NOTIFY_EMAILS  = [
    'tanya@momyourbusiness.com',
    'jason@katika.us',
    'info@momyourbusiness.com',
];
$FROM_EMAIL     = 'noreply@phillychefshowdown.com';
$FROM_NAME      = 'Philly Chef Showdown';
$ALLOWED_HOSTS  = ['phillychefshowdown.com', 'www.phillychefshowdown.com', 'philly-chef-showdown.katikaws.com'];

// ===== GUARDS =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Referer/Origin check
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$ok_origin = false;
foreach ($ALLOWED_HOSTS as $h) {
    if (stripos($referer, $h) !== false || stripos($origin, $h) !== false) {
        $ok_origin = true;
        break;
    }
}
if (!$ok_origin && !empty($referer)) {
    http_response_code(403);
    exit('Forbidden');
}

// Honeypot — bots fill every field; humans never see this one
if (!empty($_POST['website_url_confirm'] ?? '')) {
    header('Location: /apply.html?status=ok#thanks');
    exit;
}

// ===== HELPERS =====
function safe($v) {
    if (is_array($v)) return array_map('safe', $v);
    return trim(strip_tags((string)$v));
}
function hdr_safe($v) {
    return str_replace(["\r", "\n", "%0a", "%0d"], '', (string)$v);
}

// ===== COLLECT =====
$f = [
    'full_name'           => safe($_POST['full_name']           ?? ''),
    'business_name'       => safe($_POST['business_name']       ?? ''),
    'business_type'       => safe($_POST['business_type']       ?? ''),
    'neighborhood'        => safe($_POST['neighborhood']        ?? ''),
    'years_operating'     => safe($_POST['years_operating']     ?? ''),
    'phone'               => safe($_POST['phone']               ?? ''),
    'email'               => safe($_POST['email']               ?? ''),
    'website'             => safe($_POST['website']             ?? ''),
    'business_story'      => safe($_POST['business_story']      ?? ''),
    'cuisine'             => safe($_POST['cuisine']             ?? ''),
    'signature_dish'      => safe($_POST['signature_dish']      ?? ''),
    'philly_pitch'        => safe($_POST['philly_pitch']        ?? ''),
    'has_license'         => safe($_POST['has_license']         ?? ''),
    'license_number'      => safe($_POST['license_number']      ?? ''),
    'tax_compliant'       => safe($_POST['tax_compliant']       ?? ''),
    'serves_underserved'  => safe($_POST['serves_underserved']  ?? ''),
    'veteran_owned'       => safe($_POST['veteran_owned']       ?? ''),
    'tour_stops'          => implode('; ', safe((array)($_POST['tour_stops'] ?? []))),
    'has_equipment'       => safe($_POST['has_equipment']       ?? ''),
    'participation_notes' => safe($_POST['participation_notes'] ?? ''),
    'referral_source'     => safe($_POST['referral_source']     ?? ''),
    'additional_info'     => safe($_POST['additional_info']     ?? ''),
    'certify'             => isset($_POST['certify']) ? 'Yes' : 'No',
];

// ===== VALIDATE =====
$required = [
    'full_name', 'business_name', 'business_type', 'neighborhood', 'years_operating',
    'phone', 'email', 'business_story', 'cuisine', 'signature_dish', 'philly_pitch',
    'has_license', 'tax_compliant', 'serves_underserved', 'has_equipment'
];
$missing = [];
foreach ($required as $k) {
    if (empty($f[$k])) $missing[] = $k;
}
if ($f['certify'] !== 'Yes') $missing[] = 'certify';
if (!empty($f['email']) && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
    $missing[] = 'email (invalid format)';
}
if ($missing) {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Missing fields</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:600px;margin:60px auto;padding:0 24px;color:#14213D;}h1{color:#E63946;}</style>';
    echo '</head><body><h1>Some required fields are missing</h1>';
    echo '<p>Please return to the application and complete: <strong>' . implode(', ', array_map('htmlspecialchars', $missing)) . '</strong></p>';
    echo '<p><a href="/apply.html" style="color:#E63946;font-weight:700;">← Back to application</a></p>';
    echo '</body></html>';
    exit;
}

// ===== STORE =====
if (!is_dir($STORAGE_DIR)) {
    @mkdir($STORAGE_DIR, 0700, true);
}
$timestamp = date('c');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$row = array_merge(['timestamp' => $timestamp, 'ip' => $ip], $f);

$new_file = !file_exists($CSV_FILE);
$fp = @fopen($CSV_FILE, 'a');
if ($fp) {
    if ($new_file) fputcsv($fp, array_keys($row));
    fputcsv($fp, $row);
    fclose($fp);
    @chmod($CSV_FILE, 0600);
}

// Append a short log line too
$log_line = sprintf("[%s] %s | %s | %s | %s\n",
    $timestamp, $ip, $f['business_name'], $f['full_name'], $f['email']);
@file_put_contents($LOG_FILE, $log_line, FILE_APPEND | LOCK_EX);
@chmod($LOG_FILE, 0600);

// ===== EMAIL: ADMIN NOTIFY =====
$labels = [
    'full_name'           => 'Full Name',
    'business_name'       => 'Business Name',
    'business_type'       => 'Business Type',
    'neighborhood'        => 'Neighborhood',
    'years_operating'     => 'Years Operating',
    'phone'               => 'Phone',
    'email'               => 'Email',
    'website'             => 'Website / Social',
    'business_story'      => 'Business Story',
    'cuisine'             => 'Cuisine / Category',
    'signature_dish'      => 'Dish for the Showdown',
    'philly_pitch'        => 'Why Philly Needs This Food',
    'has_license'         => 'Commercial Activity License',
    'license_number'      => 'License Number',
    'tax_compliant'       => 'Tax Compliant',
    'serves_underserved'  => 'Serves Underserved Neighborhood',
    'veteran_owned'       => 'Veteran-Owned Business',
    'tour_stops'          => 'Tasting Tour Availability',
    'has_equipment'       => 'Equipment Access',
    'participation_notes' => 'Participation Notes',
    'referral_source'     => 'Heard About Us From',
    'additional_info'     => 'Additional Info',
    'certify'             => 'Certified Accurate',
];

$body  = "New Philly Chef Showdown competitor application\n";
$body .= str_repeat('=', 50) . "\n\n";
$body .= "Received: $timestamp\nIP: $ip\n\n";
foreach ($labels as $k => $v) {
    if (!empty($f[$k])) {
        $body .= "=== $v ===\n";
        $body .= $f[$k] . "\n\n";
    }
}
$body .= "\n---\nLogged to {$CSV_FILE} on the server.\n";

$subject = "New PCS application: " . hdr_safe($f['business_name'] . ' (' . $f['full_name'] . ')');
$headers  = "From: " . hdr_safe($FROM_NAME) . " <" . hdr_safe($FROM_EMAIL) . ">\r\n";
$headers .= "Reply-To: " . hdr_safe($f['full_name']) . " <" . hdr_safe($f['email']) . ">\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

foreach ($NOTIFY_EMAILS as $to) {
    @mail($to, $subject, $body, $headers, "-f $FROM_EMAIL");
}

// ===== EMAIL: APPLICANT AUTO-REPLY =====
$reply_subject = "Your Philly Chef Showdown Application Has Been Received!";
$reply_body  = "Thank you for applying to the Philly Chef Showdown!\n\n";
$reply_body .= "We've received your application and our team will review it carefully. ";
$reply_body .= "Applications are reviewed on a rolling basis. Selected applicants will be contacted ";
$reply_body .= "with their Tasting Tour stop assignment and next steps.\n\n";
$reply_body .= "Questions? Reach out to info@momyourbusiness.com.\n\n";
$reply_body .= "Philly, What Do You Want to Taste?\n— The MYB & Katika Team\n\n";
$reply_body .= "phillychefshowdown.com\n";

$reply_headers  = "From: " . hdr_safe($FROM_NAME) . " <" . hdr_safe($FROM_EMAIL) . ">\r\n";
$reply_headers .= "Reply-To: info@momyourbusiness.com\r\n";
$reply_headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$reply_headers .= "MIME-Version: 1.0\r\n";
$reply_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

@mail($f['email'], $reply_subject, $reply_body, $reply_headers, "-f $FROM_EMAIL");

// ===== DONE =====
header('Location: /apply.html?status=ok#thanks');
exit;
