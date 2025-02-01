<?php
session_start();
header('Content-Type: application/json');

// Get JSON body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['theme']) || !in_array($data['theme'], ['light', 'dark'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid theme']);
    exit;
}

// Save theme to cookie (30 days expiration)
setcookie('theme', $data['theme'], time() + (30 * 24 * 60 * 60), '/', '', true, true);

echo json_encode(['success' => true]);
