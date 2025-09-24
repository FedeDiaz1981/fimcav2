<?php
// setup/init.php
// Script completo para inicializar la base de datos y poblar con datos por defecto

echo "=== Inicialización del Sistema de Reservas ===\n\n";

// 1. Crear base de datos
echo "1. Creando base de datos...\n";
$dbfile = __DIR__ . '/../data/db.sqlite';
if (!is_dir(dirname($dbfile))) {
    mkdir(dirname($dbfile), 0750, true);
    echo "   Directorio data/ creado.\n";
}

try {
    $db = new PDO('sqlite:' . $dbfile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Crear tablas
    $db->exec("CREATE TABLE IF NOT EXISTS cabins (
        id TEXT PRIMARY KEY,
        name TEXT,
        capacity INTEGER
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cabinId TEXT,
        startISO TEXT,
        endISO TEXT,
        nights INTEGER,
        name TEXT,
        phone TEXT,
        mail TEXT,
        created_at TEXT
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS unavailable (
        cabinId TEXT,
        dateISO TEXT,
        PRIMARY KEY (cabinId, dateISO)
    )");
    
    echo "   ✓ Base de datos creada: " . realpath($dbfile) . "\n";
    
} catch (Exception $e) {
    echo "   ✗ Error creando base de datos: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Poblar cabañas
echo "\n2. Poblando cabañas...\n";
$cabins = [
    ['id' => '1', 'name' => 'Cabaña Familiar 1', 'capacity' => 4],
    ['id' => '2', 'name' => 'Cabaña Familiar 2', 'capacity' => 4],
    ['id' => '3', 'name' => 'Cabaña Confort 3', 'capacity' => 2],
    ['id' => '4', 'name' => 'Cabaña Confort 4', 'capacity' => 2],
    ['id' => '5', 'name' => 'Cabaña Confort 5', 'capacity' => 2],
    ['id' => '6', 'name' => 'Cabaña Confort 6', 'capacity' => 2],
];

try {
    $stmt = $db->prepare("INSERT OR REPLACE INTO cabins (id, name, capacity) VALUES (?, ?, ?)");
    
    foreach ($cabins as $cabin) {
        $stmt->execute([$cabin['id'], $cabin['name'], $cabin['capacity']]);
        echo "   ✓ {$cabin['name']} (ID: {$cabin['id']}, Capacidad: {$cabin['capacity']})\n";
    }
    
    echo "   ✓ " . count($cabins) . " cabañas insertadas.\n";
    
} catch (Exception $e) {
    echo "   ✗ Error poblando cabañas: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Verificar configuración
echo "\n3. Verificando configuración...\n";
$configFile = __DIR__ . '/../config.php';
if (file_exists($configFile)) {
    echo "   ✓ Archivo config.php encontrado.\n";
    $config = require $configFile;
    
    if (isset($config['SMTP_HOST']) && isset($config['SMTP_USER'])) {
        echo "   ✓ Configuración de email encontrada.\n";
    } else {
        echo "   ⚠ Configuración de email incompleta.\n";
    }
} else {
    echo "   ⚠ Archivo config.php no encontrado. Las notificaciones por email no funcionarán.\n";
}

// 4. Verificar PHPMailer
echo "\n4. Verificando PHPMailer...\n";
$phpmailerPath = __DIR__ . '/../phpmailer/src/PHPMailer.php';
if (file_exists($phpmailerPath)) {
    echo "   ✓ PHPMailer encontrado.\n";
} else {
    echo "   ⚠ PHPMailer no encontrado. Las notificaciones por email no funcionarán.\n";
}

// 5. Resumen final
echo "\n=== Resumen ===\n";
echo "✓ Base de datos: " . realpath($dbfile) . "\n";
echo "✓ Cabañas: " . count($cabins) . " insertadas\n";
echo "✓ API endpoints:\n";
echo "  - GET /api/reservas - Obtener cabañas y fechas no disponibles\n";
echo "  - POST /api/reservas - Crear nueva reserva\n";
echo "  - GET /api/cabanas - Obtener solo cabañas\n";

echo "\n=== Próximos pasos ===\n";
echo "1. Iniciar servidor PHP: php -S localhost:8000 -t public/\n";
echo "2. Iniciar servidor Astro: npm run dev\n";
echo "3. Probar la integración en http://localhost:4321\n";

echo "\n¡Sistema inicializado exitosamente! 🎉\n";
?>
