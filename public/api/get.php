<?php
// public/api/get.php
header('Content-Type: application/json; charset=utf-8');

$data_dir = __DIR__ . '/../../data';
$cabins_file = $data_dir . '/cabins.json';
$cabins = file_exists($cabins_file) ? json_decode(file_get_contents($cabins_file), true) : [];

$unav = [];
$dbfile = $data_dir . '/db.sqlite';

if (file_exists($dbfile)) {
  try {
    $db = new PDO('sqlite:' . $dbfile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->query("SELECT cabinId, dateISO FROM unavailable");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $unav[$r['cabinId']][] = $r['dateISO'];
    }
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error', 'msg' => $e->getMessage()]);
    exit;
  }
}

echo json_encode(['cabins' => $cabins, 'unavailable' => $unav], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
