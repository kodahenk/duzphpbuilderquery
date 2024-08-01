<?php

// Autoload sınıfları (örneğin, Composer kullanıyorsanız bu adımı atlayabilirsiniz)
// require 'vendor/autoload.php';

// Konfigürasyon ve sınıf dosyalarını dahil et
require 'helpers.php';
require 'config/mapping.php';
require 'QueryBuilder.php';
require 'Relation.php';

// Configuration array
$config = require 'config/mapping.php';

$queryBuilder = new QueryBuilder('posts');

$queryBuilder->select($config['posts']['columns'])
    ->with($config['posts']['relations'])
    ->where(['category_id' => 1])
    ->limit(10)
    ->offset(0);

// Get results
$results = $queryBuilder->get();



// Sonuçları ekrana yazdır
echo '<pre>';
print_r($results);
echo '</pre>';
