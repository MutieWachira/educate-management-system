<?php
declare(strict_types=1);
//PHP is normally loosely typed.
//That means PHP will try to help by converting values for you 
//sometimes in ways you didn’t intend
//turns on strict type checking for a file.



$host = "localhost";
$db   = "ems_db";
$user = "root";
$pass = ""; // XAMPP default is empty
$charset = "utf8mb4";

//Use MySQL, connect to this host, open this database, use this charset.
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  die("DB Connection failed.");
}
