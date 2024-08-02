<?php

$requestTableName = 'usesdfdsf2r';
$requestRelationData = [
    'likes' => [],
    'posdts' => [],
];

$mappingTableArray = require 'config/mapping.php';

$filteredRequestMappingArray = filterTable($mappingTableArray, $requestTableName);




function getData(array $table, array $IDs = [])
{
    $data = [];
    $relations = $table['relations'];

    foreach ($relations as $relation => $relationData) {
        $relationTable = $relationData['related_table'];
        $relationColumns = $relationData['columns'];

        $data[$relationTable] = $relationColumns;

        // limit var mı sql limit
        // offset var mı sql offset
        // where var mı sql where

        

        if (!empty($relationData['relations'])) {
            $data['_' . $relationTable] = getData($relationData);
        }
    }

    return $data;
}


function filterTable(array $mappingTableArray, string $requestTableName)
{
    return [];
}
