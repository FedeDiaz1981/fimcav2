<?php
session_start();
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
$dbfile = __DIR__ . '/../../data/db.sqlite';
$db = new PDO('sqlite:' . $dbfile);
$cabanas = $db->query('SELECT * FROM cabins')->fetchAll(PDO::FETCH_ASSOC);
?>
<h1>Panel de administración</h1>
<a href="logout.php">Salir</a>
<ul>
    <?php foreach ($cabanas as $c): ?>
        <li><?= htmlspecialchars($c['name']) ?> (Capacidad: <?= $c['capacity'] ?>)</li>
    <?php endforeach; ?>
</ul>