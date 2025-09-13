<?php
// public/api/book.php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Método no permitido']);
  exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$data) {
  http_response_code(400);
  echo json_encode(['error' => 'JSON inválido']);
  exit;
}

$required = ['cabinId','startISO','endISO','name','phone','mail'];
foreach ($required as $r) {
  if (empty($data[$r])) {
    http_response_code(400);
    echo json_encode(['error' => "Falta $r"]);
    exit;
  }
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['startISO']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['endISO'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Fechas con formato inválido (YYYY-MM-DD)']);
  exit;
}

$data_dir = __DIR__ . '/../../data';
$dbfile = $data_dir . '/db.sqlite';
if (!file_exists($dbfile)) {
  http_response_code(500);
  echo json_encode(['error' => 'DB no encontrada. Ejecutá setup/create_db.php']);
  exit;
}

try {
  $db = new PDO('sqlite:' . $dbfile);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // generar array de fechas [start, end)
  $dates = [];
  $s = new DateTime($data['startISO']);
  $e = new DateTime($data['endISO']);
  for ($d = clone $s; $d < $e; $d->modify('+1 day')) $dates[] = $d->format('Y-m-d');
  if (count($dates) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Rango inválido']);
    exit;
  }

  // check disponibilidad
  $placeholders = rtrim(str_repeat('?,', count($dates)), ',');
  $stmt = $db->prepare("SELECT 1 FROM unavailable WHERE cabinId = ? AND dateISO IN ($placeholders) LIMIT 1");
  $params = array_merge([$data['cabinId']], $dates);
  $stmt->execute($params);
  if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Fechas no disponibles']);
    exit;
  }

  // insertar booking
  $ins = $db->prepare("INSERT INTO bookings (cabinId,startISO,endISO,nights,name,phone,mail,created_at)
    VALUES (?,?,?,?,?,?,?,?)");
  $ins->execute([
    $data['cabinId'],
    $data['startISO'],
    $data['endISO'],
    intval($data['nights'] ?? count($dates)),
    htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8'),
    htmlspecialchars($data['phone'], ENT_QUOTES, 'UTF-8'),
    filter_var($data['mail'], FILTER_VALIDATE_EMAIL) ? $data['mail'] : $data['mail'],
    date('c')
  ]);

  // actualizar unavailable
  $ins2 = $db->prepare("INSERT OR IGNORE INTO unavailable (cabinId,dateISO) VALUES (?, ?)");
  foreach ($dates as $d) $ins2->execute([$data['cabinId'], $d]);

  echo json_encode(['ok' => true]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'DB error', 'msg' => $e->getMessage()]);
}
