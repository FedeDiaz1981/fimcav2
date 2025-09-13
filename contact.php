<?php
declare(strict_types=1);

// ---------------------------------------------
// contact.php
// Endpoint para recibir reservas (JSON o form-data)
// Asegurate de guardar sin BOM (UTF-8 without BOM).
// ---------------------------------------------

// Content type JSON
header('Content-Type: application/json; charset=utf-8');

// ----------------- CORS -----------------------
// En producción reemplazá/ajustá los orígenes permitidos.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = [
  'http://localhost:4321',
  'http://127.0.0.1:4321',
  'http://localhost:8000',    // si probás con php -S
  'http://127.0.0.1:8000',
  // 'https://tu-dominio.com', // <- poner tu dominio en producción (https)
];

if ($origin) {
  if (in_array($origin, $allowed, true) || preg_match('#^https?://localhost(:\d+)?$#', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    header('Access-Control-Allow-Credentials: false');
    header('Access-Control-Max-Age: 86400');
  }
}

// Responder preflight OPTIONS y terminar
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}
// ----------------- /CORS ---------------------

// Sólo POST a partir de aquí
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

// --- Rate limit simple por IP (archivo) ---
$xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
if ($xff && strpos($xff, ',') !== false) { $xff = trim(explode(',', $xff)[0]); }
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $xff ?: ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

$window = 15 * 60; // 15 min
$max = 30; // máximo solicitudes por ventana
$bucketDir = __DIR__ . '/.ratelimit';
if (!is_dir($bucketDir)) @mkdir($bucketDir, 0700);
$bucketFile = $bucketDir . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $ip);
$now = time();
$slot = ['ts' => $now, 'count' => 0];
if (is_file($bucketFile)) {
  $slot = json_decode((string)file_get_contents($bucketFile), true) ?: $slot;
  if (($now - (int)$slot['ts']) > $window) {
    $slot = ['ts' => $now, 'count' => 0];
  }
}
if ((int)$slot['count'] >= $max) {
  http_response_code(429);
  echo json_encode(['ok' => false, 'error' => 'Too many requests']);
  exit;
}
$slot['count'] = (int)$slot['count'] + 1;
@file_put_contents($bucketFile, json_encode($slot), LOCK_EX);

// --- Cargar config ---
// Ruta actual: config.php en el mismo directorio que contact.php
// Si movés config.php fuera de public, reemplazá por __DIR__ . '/../config.php'
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Missing config.php']);
  exit;
}
$config = require $configFile;

// --- Normalizar entrada (JSON o FormData) ---
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $input = json_decode($raw ?: '[]', true) ?: [];
} else {
  $input = $_POST;
}

// --- Honeypot ---
$honeypot = trim((string)($input['sitio'] ?? $input['_gotcha'] ?? ''));
if ($honeypot !== '') {
  echo json_encode(['ok' => true]); // silencioso contra bots
  exit;
}

// --- Campos esperados ---
$nombre   = trim((string)($input['nombre']   ?? ''));
$email    = trim((string)($input['email']    ?? ''));
$telefono = trim((string)($input['telefono'] ?? ''));
$cabinId  = trim((string)($input['cabinId']  ?? $input['cabaña'] ?? $input['cabin'] ?? ''));
$startISO = trim((string)($input['startISO'] ?? ''));
$endISO   = trim((string)($input['endISO']   ?? ''));
$days     = (int)($input['days'] ?? $input['nights'] ?? 0); // compatibilidad
$notes    = trim((string)($input['notes'] ?? ''));

// Validaciones básicas
if ($nombre === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $startISO === '' || $endISO === '' || $days < 1) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
  exit;
}

// Limites
if (strlen($notes) > 8000) {
  http_response_code(413);
  echo json_encode(['ok' => false, 'error' => 'Notas demasiado largas']);
  exit;
}

// Generar array de fechas desde start (incl) hasta end (excl)
function parse_iso_date(string $s) : ?DateTime {
  $d = DateTime::createFromFormat('!Y-m-d', $s);
  return $d ?: null;
}
$sd = parse_iso_date($startISO);
$ed = parse_iso_date($endISO);
if (!$sd || !$ed) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Fechas inválidas']);
  exit;
}
if ($ed <= $sd) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Rango de fechas inválido']);
  exit;
}

// construir lista de fechas (incluir start, excluir end)
$dates = [];
$cur = clone $sd;
while ($cur < $ed) {
  $dates[] = $cur->format('Y-m-d');
  $cur->modify('+1 day');
}

// Sanitize helper para HTML seguro
$safe = static function(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

// --- PHPMailer: intentar localizar en varios lugares ---
$possible = [
  __DIR__ . '/phpmailer/src/PHPMailer.php',         // recomendado: phpmailer/ junto a contact.php
  __DIR__ . '/public/phpmailer/src/PHPMailer.php',  // si mantuviste dentro de public/
  __DIR__ . '/../phpmailer/src/PHPMailer.php',      // si moviste phpmailer un nivel arriba (public_html case)
];

$found = false;
foreach ($possible as $p) {
  if (is_file($p)) {
    // deducir base dir de src
    $base = dirname($p, 1); // .../phpmailer/src
    require $base . '/PHPMailer.php';
    require $base . '/SMTP.php';
    require $base . '/Exception.php';
    $found = true;
    break;
  }
}

if (!$found) {
  // Responder JSON en vez de provocar fatal con require
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'PHPMailer no encontrado. Colocá la carpeta phpmailer/src/ en una de las rutas: ' . implode(', ', $possible),
  ]);
  exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


function buildMailer(array $config, bool $useStartTLS = false, bool $debug = false): PHPMailer {
  $mail = new PHPMailer(true);
  $mail->CharSet   = 'UTF-8';
  $mail->Encoding  = 'base64';
  $mail->setLanguage('es');

  $mail->isSMTP();
  $mail->Host     = (string)$config['SMTP_HOST'];
  $mail->SMTPAuth = true;
  $mail->Username = (string)$config['SMTP_USER'];
  $mail->Password = (string)$config['SMTP_PASS'];

  if ($useStartTLS) {
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
  } else {
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = (int)($config['SMTP_PORT'] ?? 465);
  }

  // From debe ser el mismo buzón autenticado (por ejemplo SMTP_USER)
  $fromName = isset($config['FROM_NAME']) && is_string($config['FROM_NAME']) ? $config['FROM_NAME'] : 'Web';
  $mail->setFrom((string)$config['SMTP_USER'], $fromName);

  // Destinatario principal
  $mail->addAddress((string)$config['SMTP_TO'], 'Reservas');

  if ($debug) {
    $mail->SMTPDebug   = 2;
    $mail->Debugoutput = function($s){ error_log('[SMTP] '.$s); };
  }

  return $mail;
}

// Construir cuerpo del mail
$cabinLabel = $cabinId !== '' ? $safe($cabinId) : '-';
$humanDates = implode(', ', $dates);
$htmlDatesList = '<ul>' . implode('', array_map(function($d) use ($safe) { return '<li>' . $safe($d) . '</li>'; }, $dates)) . '</ul>';

$subject = sprintf('Nueva reserva: %s - %s (%d días)', $safe($nombre), $cabinLabel, count($dates));
$bodyHtml = sprintf(
  '<h2>Nueva reserva</h2>
   <p><b>Nombre:</b> %s</p>
   <p><b>Email:</b> %s</p>
   <p><b>Teléfono:</b> %s</p>
   <p><b>Cabaña:</b> %s</p>
   <p><b>Desde:</b> %s — <b>Hasta (excl):</b> %s</p>
   <p><b>Días reservados:</b> %s (%d)</p>
   <p><b>Fechas:</b></p>
   %s
   <p><b>Notas:</b><br/>%s</p>',
  $safe($nombre),
  $safe($email),
  $safe($telefono !== '' ? $telefono : '-'),
  $cabinLabel,
  $safe($startISO),
  $safe($endISO),
  $safe($humanDates),
  count($dates),
  $htmlDatesList,
  nl2br($safe($notes))
);
$altBody = "Nueva reserva\nNombre: $nombre\nEmail: $email\nTeléfono: ".($telefono ?: '-')."\nCabaña: $cabinLabel\nDesde: $startISO\nHasta (excl): $endISO\nDías: ".count($dates)."\nFechas: $humanDates\n\nNotas:\n$notes";

// Envío con fallback 465 -> 587
$debugMode = (isset($_GET['debug']) && $_GET['debug'] === '1');

try {
  $mail = buildMailer($config, false, $debugMode);
  $mail->isHTML(true);
  $mail->Subject = $subject;
  $mail->Body    = $bodyHtml;
  $mail->AltBody = $altBody;
  $mail->addReplyTo($email, $nombre);

  try {
    $mail->send();
    echo json_encode(['ok' => true, 'transport' => 'smtps465']);
    exit;
  } catch (Exception $e1) {
    error_log('Mailer first attempt (465) error: '. $e1->getMessage() .' | '. ($mail->ErrorInfo ?? ''));
    // intento STARTTLS
    $mail = buildMailer($config, true, $debugMode);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $bodyHtml;
    $mail->AltBody = $altBody;
    $mail->addReplyTo($email, $nombre);
    $mail->send();
    echo json_encode(['ok' => true, 'transport' => 'starttls587']);
    exit;
  }
} catch (Exception $e) {
  error_log('Mailer error final: ' . $e->getMessage());
  if (isset($mail) && property_exists($mail, 'ErrorInfo') && $mail->ErrorInfo) {
    error_log('Mailer ErrorInfo: ' . $mail->ErrorInfo);
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'No se pudo enviar el mail']);
}
