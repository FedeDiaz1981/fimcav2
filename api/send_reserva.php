<?php
declare(strict_types=1);

$cfg = require __DIR__ . '/../config.php';

// 2) PHPMailer (si NO usás Composer)
require __DIR__ . '/../phpmailer/src/PHPMailer.php';
require __DIR__ . '/../phpmailer/src/SMTP.php';
require __DIR__ . '/../phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Siempre JSON
header('Content-Type: application/json; charset=utf-8');

// CORS (ajustado a tu dominio y dev)
$allowed = ['http://localhost:4321','http://127.0.0.1:4321'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed, true)) {
  header("Access-Control-Allow-Origin: $origin");
  header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Cargar config
$configFile = dirname(__DIR__) . '/config.php';
if (!file_exists($configFile)) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Falta config.php']);
  exit;
}
$config = require $configFile;

// Cargar PHPMailer (Composer o carpeta phpmailer/src)
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload;
} else {
  $base = dirname(__DIR__) . '/phpmailer/src';
  foreach (['PHPMailer.php','SMTP.php','Exception.php'] as $f) {
    $p = $base . '/' . $f;
    if (!file_exists($p)) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'PHPMailer no disponible']);
      exit;
    }
    require_once $p;
  }
}

// Parsear JSON
try {
  $raw = file_get_contents('php://input') ?: '';
  $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
  exit;
}

// Validaciones mínimas
$required = ['nombre','email','telefono','cabinId','startISO','endISO'];
foreach ($required as $f) {
  if (empty($data[$f])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => "Campo requerido: $f"]);
    exit;
  }
}
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Email inválido']);
  exit;
}
$iso = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($iso, (string)$data['startISO']) || !preg_match($iso, (string)$data['endISO'])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Fecha inválida (YYYY-MM-DD)']);
  exit;
}

// Datos
$nombre   = trim((string)$data['nombre']);
$email    = trim((string)$data['email']);
$telefono = trim((string)$data['telefono']);
$cabinId  = (string)$data['cabinId'];
$startISO = (string)$data['startISO'];
$endISO   = (string)$data['endISO'];
$notes    = trim((string)($data['notes'] ?? ''));

// Expande rango [startISO, endISO)
$daysList = [];
try {
  $d = new DateTime($startISO);
  $e = new DateTime($endISO);
  while ($d < $e) { $daysList[] = $d->format('Y-m-d'); $d->modify('+1 day'); }
} catch (Throwable $t) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Fechas inválidas']); exit;
}
if (!$daysList) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Rango vacío']); exit;
}

// ====== PERSISTENCIA EN JSON con LOCK + validación de choque ======
$dir  = dirname(__DIR__) . '/data';
$file = $dir . '/unavailable.json';
if (!is_dir($dir)) @mkdir($dir, 0755, true);

// abrir con lock
$fh = @fopen($file, 'c+'); // crea si no existe
if (!$fh) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'No se pudo abrir el almacenamiento']); exit;
}
flock($fh, LOCK_EX);

// leer
$raw = stream_get_contents($fh);
$store = $raw ? json_decode($raw, true) : [];
if (!is_array($store)) $store = [];
if (!isset($store[$cabinId])) $store[$cabinId] = [];

// choque?
$existing = array_flip($store[$cabinId]);
foreach ($daysList as $d) {
  if (isset($existing[$d])) {
    flock($fh, LOCK_UN); fclose($fh);
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'Ya reservado en ese rango']); exit;
  }
}

// reservar tentativo: escribir ahora (así evitamos doble mail)
// (si el envío de mail falla, hacemos rollback)
$store[$cabinId] = array_values(array_unique(array_merge($store[$cabinId], $daysList)));
rewind($fh);
ftruncate($fh, 0);
$writeOk = fwrite($fh, json_encode($store, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
fflush($fh);
flock($fh, LOCK_UN);
fclose($fh);

if ($writeOk === false) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'No se pudo guardar']); exit;
}

// ====== MAIL ======
try {
  $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
  $mail->CharSet  = 'UTF-8';
  $mail->Encoding = 'base64';
  $mail->setLanguage('es');

  // SMTP
  $mail->isSMTP();
  $mail->Host       = $config['SMTP_HOST'] ?? '';
  $mail->SMTPAuth   = true;
  $mail->Username   = $config['SMTP_USER'] ?? '';
  $mail->Password   = $config['SMTP_PASS'] ?? '';
  $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; // 465
  $mail->Port       = (int)($config['SMTP_PORT'] ?? 465);

  // De/Para
  $from = $config['SMTP_USER'] ?? 'noreply@fincacasiana.com';
  $mail->setFrom($from, $config['FROM_NAME'] ?? 'Reservas Web');
  $mail->addAddress($config['SMTP_TO'] ?? $from, 'Reservas');
  $mail->addReplyTo($email, $nombre);

  // Contenido
  $mail->isHTML(true);
  $mail->Subject = sprintf('Nueva reserva: %s - Cabaña %s', $nombre, $cabinId);

  $html = sprintf(
    '<h2>Nueva Reserva</h2>
     <p><b>Nombre:</b> %s</p>
     <p><b>Email:</b> %s</p>
     <p><b>Teléfono:</b> %s</p>
     <p><b>Cabaña:</b> %s</p>
     <p><b>Desde:</b> %s</p>
     <p><b>Hasta:</b> %s</p>
     <p><b>Días:</b> %d</p>%s',
    htmlspecialchars($nombre),
    htmlspecialchars($email),
    htmlspecialchars($telefono),
    htmlspecialchars($cabinId),
    htmlspecialchars($startISO),
    htmlspecialchars($endISO),
    count($daysList),
    $notes ? ('<p><b>Notas:</b> ' . nl2br(htmlspecialchars($notes)) . '</p>') : ''
  );
  $mail->Body = $html;

  $text = "Nueva Reserva\n"
        . "Nombre: $nombre\n"
        . "Email: $email\n"
        . "Teléfono: $telefono\n"
        . "Cabaña: $cabinId\n"
        . "Desde: $startISO\n"
        . "Hasta: $endISO\n"
        . "Días: ".count($daysList)."\n";
  if ($notes) $text .= "Notas: $notes\n";
  $mail->AltBody = $text;

  $mail->send();

  // responde ok + info para actualizar UI sin esperar GET
  echo json_encode([
    'ok' => true,
    'message' => 'Email enviado',
    'cabinId' => $cabinId,
    'days' => $daysList
  ]);
} catch (Throwable $e) {
  // ROLLBACK: si el mail falla, liberamos los días que acabamos de bloquear
  try {
    $fh = @fopen($file, 'c+');
    if ($fh) {
      flock($fh, LOCK_EX);
      $raw2 = stream_get_contents($fh);
      $store2 = $raw2 ? json_decode($raw2, true) : [];
      if (!is_array($store2)) $store2 = [];
      $cur = isset($store2[$cabinId]) && is_array($store2[$cabinId]) ? $store2[$cabinId] : [];
      $setDays = array_flip($daysList);
      $cur = array_values(array_filter($cur, fn($d) => !isset($setDays[$d])));
      $store2[$cabinId] = $cur;
      rewind($fh);
      ftruncate($fh, 0);
      fwrite($fh, json_encode($store2, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
      fflush($fh);
      flock($fh, LOCK_UN);
      fclose($fh);
    }
  } catch (Throwable $ignored) {}

  error_log('Mail ERROR: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'No se pudo enviar el mail']);
}
