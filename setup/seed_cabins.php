<?php
// setup/seed_cabins.php
// Script para poblar la base de datos con cabañas por defecto

$dbfile = __DIR__ . '/../data/db.sqlite';
if (!file_exists($dbfile)) {
    echo "Error: Base de datos no encontrada. Ejecutá primero create_db.php\n";
    exit(1);
}

try {
    $db = new PDO('sqlite:' . $dbfile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si ya hay cabañas
    $stmt = $db->query("SELECT COUNT(*) FROM cabins");
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo "Ya existen $count cabañas en la base de datos.\n";
        echo "¿Deseas continuar y agregar más? (y/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim(strtolower($line)) !== 'y') {
            echo "Operación cancelada.\n";
            exit(0);
        }
    }
    
    // Datos de cabañas por defecto
    $cabins = [
        ['id' => '1', 'name' => 'Cabaña Familiar 1', 'capacity' => 4],
        ['id' => '2', 'name' => 'Cabaña Familiar 2', 'capacity' => 4],
        ['id' => '3', 'name' => 'Cabaña Confort 3', 'capacity' => 2],
        ['id' => '4', 'name' => 'Cabaña Confort 4', 'capacity' => 2],
        ['id' => '5', 'name' => 'Cabaña Confort 5', 'capacity' => 2],
        ['id' => '6', 'name' => 'Cabaña Confort 6', 'capacity' => 2],
    ];
    
    $stmt = $db->prepare("INSERT OR REPLACE INTO cabins (id, name, capacity) VALUES (?, ?, ?)");
    
    foreach ($cabins as $cabin) {
        $stmt->execute([$cabin['id'], $cabin['name'], $cabin['capacity']]);
        echo "Cabaña {$cabin['id']}: {$cabin['name']} (capacidad: {$cabin['capacity']})\n";
    }
    
    echo "\n¡Cabañas insertadas exitosamente!\n";
    echo "Total de cabañas: " . count($cabins) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
