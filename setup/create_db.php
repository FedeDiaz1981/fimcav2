<?php
// setup/create_db.php
// Ejecutar: php setup/create_db.php

$dbfile = __DIR__ . '/../data/db.sqlite';
if (!is_dir(dirname($dbfile))) mkdir(dirname($dbfile), 0750, true);

try {
  $db = new PDO('sqlite:' . $dbfile);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

  echo 'DB creada: ' . realpath($dbfile) . PHP_EOL;
} catch (Exception $e) {
  echo 'Error: ' . $e->getMessage() . PHP_EOL;
  exit(1);
}
