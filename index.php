<?php

// Autoload sınıfları (örneğin, Composer kullanıyorsanız bu adımı atlayabilirsiniz)
// require 'vendor/autoload.php';

// Konfigürasyon ve sınıf dosyalarını dahil et
require 'helpers.php';
require 'Database.php';
require 'config/mapping.php';
require 'QueryBuilder.php';
require 'Relation.php';

// Configuration array
$config = require 'config/mapping.php';

$queryBuilder = new QueryBuilder('users');
$queryBuilder->select($config['users']['columns'])
->with($config['users']['relations'])
// ->where(['category_id' => 1]);
->limit(10);
// ->offset(0);
// Get results
$results = $queryBuilder->get();



// Sonuçları json olaravek ver
// header json
header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);