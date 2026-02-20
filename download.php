<?php
session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_login();

$file_id = sanitize_input($_GET['id'] ?? '');
if ($file_id === '') {
    redirect_with_message('my-orders.php', 'Invalid file id', 'error');
}

$user_id = $_SESSION['user_id'];

// 1) Get file + order_id
$stmt = $pdo->prepare("
    SELECT file_id, order_id, file_path, original_filename, mime_type, file_size
    FROM file_attachments
    WHERE file_id = :fid
    LIMIT 1
");
$stmt->execute(['fid' => $file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    redirect_with_message('my-orders.php', 'File not found', 'error');
}

// 2) Authorize: user must be client or freelancer of that order
$stmt = $pdo->prepare("
    SELECT client_id, freelancer_id
    FROM orders
    WHERE order_id = :oid
    LIMIT 1
");
$stmt->execute(['oid' => $file['order_id']]);
$o = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$o) {
    redirect_with_message('my-orders.php', 'Order not found', 'error');
}

if ((string)$o['client_id'] !== (string)$user_id && (string)$o['freelancer_id'] !== (string)$user_id) {
    redirect_with_message('my-orders.php', 'Access denied', 'error');
}

// 3) Serve the file
$path = $file['file_path'];

if (!$path || !file_exists($path)) {
    redirect_with_message('order-details.php?id=' . urlencode($file['order_id']), 'File missing on server', 'error');
}

$mime = $file['mime_type'] ?: 'application/octet-stream';
$name = $file['original_filename'] ?: ('file_' . $file_id);

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($name) . '"');
header('Content-Length: ' . (string)filesize($path));
header('Pragma: public');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

readfile($path);
exit;
