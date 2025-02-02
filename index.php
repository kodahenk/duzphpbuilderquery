<?php

// Autoload sınıfları (örneğin, Composer kullanıyorsanız bu adımı atlayabilirsiniz)
// require 'vendor/autoload.php';

// Konfigürasyon ve sınıf dosyalarını dahil et
require 'helpers.php';
require 'Database.php';
require 'config/mapping.php';
require 'QueryBuilder.php';

// Configuration array
$config = require 'config/mapping.php';

$queryBuilder = new QueryBuilder('users');

$queryBuilder
    ->select(["name", "email"])
    ->with([
        'posts' => [
            'limit' => 3,
            'offset' => 0,
            'columns' => ['title'],
            'relations' => [
                'user' => [
                    'relations' => [
                        'likes' => [],
                    ],
                ],
                
            ],
        ],
        'comments' => [
            'limit' => 3,
            'offset' => 0,
        ],
    ])
    // ->where(['id' => 1])
    ->limit(1);
// ->offset(0);
// Get results
$results = $queryBuilder->get();



// Sonuçları json olaravek ver
// header json
header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
