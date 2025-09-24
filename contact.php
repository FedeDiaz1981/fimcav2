<?php
declare(strict_types=1);

// Siempre JSON
header('Content-Type: application/json; charset=utf-8');

// CORS (sumá tu dominio real)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = [
  'http://localhost:4321',
  'http://127.0.0.1:4321',
  'https://tudominio.com',
  'https://www.tudominio.com',
];
if ($origin && in_array($origin, $allowed, true)) {
  header("Access-Control-Allow-Origin: $origin");
  header('Vary: Origin');
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Accept');
  header('Access-Control-Max-Age: 86400');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Método no permitido']);
  exit;
}

// Cargar config
$configFile = dirname(__DIR__) . '/config.php';
if (!file_exists($configFile)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Falta config.php']);
  exit;
}
$config = require $configFile;

// PHPMailer (Composer preferido; si no, carpeta phpmailer/src)
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload;
} else {
  $base = dirname(__DIR__) . '/phpmailer/src';
  foreach (['PHPMailer.php','SMTP.php','Exception.php'] as $f) {
    $p = $base . '/' . $f;
    if (!file_exists($p)) {
      http_response_code(500);
      echo json_encode(['ok'=>false,'error'=>'PHPMailer no disponible']);
      exit;
    }
    require_once $p;
  }
}

// Leer y validar payload
try {
  $data = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'JSON inválido']);
  exit;
}
$required = ['nombre','email','telefono','cabinId','startISO','endISO'];
foreach ($required as $f) {
  if (empty($data[$f])) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>"Campo requerido: $f"]);
    exit;
  }
}
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Email inválido']);
  exit;
}
$reISO = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($reISO,(string)$data['startISO']) || !preg_match($reISO,(string)$data['endISO'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Fecha inválida (YYYY-MM-DD)']);
  exit;
}

// Armar campos
$nombre   = trim((string)$data['nombre']);
$email    = trim((string)$data['email']);
$telefono = trim((string)$data['telefono']);
$cabinId  = (string)$data['cabinId'];
$startISO = (string)$data['startISO'];
$endISO   = (string)$data['endISO'];
$days     = max(1, (int)($data['days'] ?? 0));
$notes    = trim((string)($data['notes'] ?? ''));

// Enviar con Gmail (app-password)
try {
  $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
  $mail->CharSet  = 'UTF-8';
  $mail->Encoding = 'base64';
  $mail->setLanguage('es');

  $mail->isSMTP();
  $mail->Host       = 'smtp.gmail.com';   // Gmail
  $mail->SMTPAuth   = true;
  $mail->Username   = $config['SMTP_USER'] ?? '';
  $mail->Password   = $config['SMTP_PASS'] ?? '';

  // Opción 1 (recomendada con Gmail): STARTTLS/587
  $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;

  // Opción 2: SMTPS/465 (si preferís 465, descomentá y comenta arriba)
  // $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
  // $mail->Port       = 465;

  // Remitente = tu usuario Gmail (debe coincidir)
  $from = $config['SMTP_USER'] ?? 'noreply@example.com';
  $mail->setFrom($from, $config['FROM_NAME'] ?? 'Reservas Web');

  // Destino
  $to = $config['SMTP_TO'] ?? $from;
  $mail->addAddress($to, 'Reservas');

  // Reply-To al cliente
  $mail->addReplyTo($email, $nombre);

  $mail->isHTML(true);
  $mail->Subject = sprintf('Nueva reserva: %s - Cabaña %s', $nombre, $cabinId);

  $mail->Body = sprintf(
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
    $days,
    $notes ? ('<p><b>Notas:</b> ' . nl2br(htmlspecialchars($notes)) . '</p>') : ''
  );
  $mail->AltBody =
    "Nueva Reserva\n".
    "Nombre: $nombre\n".
    "Email: $email\n".
    "Teléfono: $telefono\n".
    "Cabaña: $cabinId\n".
    "Desde: $startISO\n".
    "Hasta: $endISO\n".
    "Días: $days\n".
    ($notes ? "Notas: $notes\n" : '');

  $mail->send();
  echo json_encode(['ok'=>true,'message'=>'Email enviado']);
} catch (\Throwable $e) {
  error_log('Mail ERROR: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'No se pudo enviar el mail']);
}
