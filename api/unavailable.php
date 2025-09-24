<?php
header('Content-Type: application/json; charset=utf-8');

// CORS para dev (solo si lo probás desde Astro en 4321)
$allowed = ['http://localhost:4321','http://127.0.0.1:4321'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed, true)) {
  header("Access-Control-Allow-Origin: $origin");
  header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$file = __DIR__ . '/../data/unavailable.json';
if (!file_exists($file)) { echo json_encode(['unavailable'=>new stdClass()]); exit; }

$raw = @file_get_contents($file);
$data = $raw ? json_decode($raw, true) : [];
if (!is_array($data)) $data = [];

echo json_encode(['unavailable'=>$data], JSON_UNESCAPED_UNICODE);
