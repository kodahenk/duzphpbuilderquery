<?php

// Autoload sınıfları (örneğin, Composer kullanıyorsanız bu adımı atlayabilirsiniz)
// require 'vendor/autoload.php';

// Konfigürasyon ve sınıf dosyalarını dahil et
require 'helpers.php';
require 'config/mapping.php';
require 'QueryBuilder.php';

// PDO veritabanı bağlantısı oluşturun
$dsn = 'mysql:host=localhost;dbname=devorhan;charset=utf8';
$username = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// QueryBuilder'ı oluştur ve SQL sorgusunu çalıştır
$queryBuilder = new QueryBuilder('likes', $pdo);
$results = $queryBuilder->execute();


header('Content-Type: application/json');
echo json_encode($results);
