<?php

declare(strict_types=1);

$dbPath = $argv[1] ?? __DIR__ . '/../var/database/test.db';
$sqlPath = $argv[2] ?? __DIR__ . '/../migrations/seeds/seed_telecel_planning.sql';

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = file_get_contents($sqlPath);
$pdo->exec($sql);
echo "Seed OK\n";
