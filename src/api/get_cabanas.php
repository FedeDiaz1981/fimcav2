<?php
header('Content-Type: application/json');
$dbfile = __DIR__ . '/../../data/db.sqlite';
$db = new PDO('sqlite:' . $dbfile);
$res = $db->query('SELECT * FROM cabins');
echo json_encode($res->fetchAll(PDO::FETCH_ASSOC));