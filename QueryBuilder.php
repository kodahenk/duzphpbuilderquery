<?php

class QueryBuilder
{
    protected $table = [];
    protected $columns = [];
    protected $relations = [];
    protected $conditions = [];
    protected $limit;
    protected $offset;
    protected $db;
    protected $tablename;
    protected $mappingTable;

    public function __construct($tablename)
    {
        $this->mappingTable = require 'config/mapping.php';
        $this->tablename = $tablename;
        $this->table = $this->mappingTable[$tablename];

        if (empty($columns)) {
            $this->columns = ['*'];
        }

        $this->db = new Database(); // Create a new Database instance
    }

    public function select(array $columns)
    {
        if (!empty($columns)) {
            $this->columns = $columns;
        }

        return $this;
    }

    private function relationFilter(array $requestTableData, array $mappingTableData, $relationTableName)
    {
        $filteredRelations = [];

        foreach ($requestTableData as $relation => $relationData) {
            if (isset($mappingTableData[$relationTableName]['relations'][$relation])) {

                $filteredRelations[$relation] = $mappingTableData[$relationTableName]['relations'][$relation];

                if (!empty($relationData['columns'])) {
                    $filteredRelations[$relation]['columns'] = $relationData['columns'];

                    if (!in_array($filteredRelations[$relation]['foreign_key'], $filteredRelations[$relation]['columns'])) {
                        $filteredRelations[$relation]['columns'][] = $filteredRelations[$relation]['foreign_key'];
                    }
                } else {
                    $filteredRelations[$relation]['columns'] = ['*'];
                }

                if (!empty($relationData['limit'])) {
                    $filteredRelations[$relation]['limit'] = $relationData['limit'];
                }

                if (!empty($relationData['offset'])) {
                    $filteredRelations[$relation]['offset'] = $relationData['offset'];
                }

                if (!empty($relationData['relations'])) {
                    foreach ($relationData['relations'] as $nestedRelation => $nestedRelationData) {
                        $filteredRelations[$relation]['relations'][$nestedRelation] = $this->relationFilter([$nestedRelation => $nestedRelationData], $mappingTableData, $relation);
                    }
                }
            }
        }

        return $filteredRelations;
    }


    public function with(array $tableData)
    {
        // $this->relations = $this->relationFilter($tableData, $this->mappingTable, $this->tablename);
        $this->relations = [
            'posts' => [
                'local_key' => 'id',
                'foreign_key' => 'user_id',
                'related_table' => 'posts',
                'relations' => [
                    'user' => [
                        'local_key' => 'user_id',
                        'foreign_key' => 'id',
                        'related_table' => 'users',
                        'relations' => [
                            'likes' => [
                                'local_key' => 'id',
                                'foreign_key' => 'user_id',
                                'related_table' => 'likes',
                            ]
                        ]
                    ]
                ]
            ],
            'likes' => [
                'local_key' => 'id',
                'foreign_key' => 'user_id',
                'related_table' => 'likes',
            ]
        ];
        return $this;
        return $this;
    }

    public function where(array $conditions)
    {
        $this->conditions = $conditions;
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function get()
    {
        if (!empty($this->relations)) {
            foreach ($this->relations as $relation => $relationData) {
                $this->columns[] = $this->table['relations'][$relation]['local_key'];
            }
        }

        $this->columns = array_unique($this->columns);

        $query = "SELECT " . implode(', ', $this->columns) . " FROM " . $this->tablename;

        if (!empty($this->conditions)) {
            $conditions = array_map(function ($key, $value) {
                return "$key = '$value'";
            }, array_keys($this->conditions), $this->conditions);
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        if ($this->limit) {
            $query .= " LIMIT " . $this->limit;
        }
        if ($this->offset) {
            $query .= " OFFSET " . $this->offset;
        }

        $results = $this->db->query($query);

        foreach ($this->relations as $relation => $relationData) {
            $results = $this->loadRelation($results, $relation, $relationData);
        }

        return $results;
    }

    protected function loadRelation($results, $relation, $relationData)
    {
        $ids = array_column($results, $relationData['local_key']);
        $ids = array_unique($ids);
    
        $relatedQuery = "SELECT * FROM {$relationData['related_table']} WHERE {$relationData['foreign_key']} IN (" . implode(',', $ids) . ")";
    
        if (isset($relationData['limit'])) {
            $relatedQuery .= " LIMIT " . $relationData['limit'];
        }
        if (isset($relationData['offset'])) {
            $relatedQuery .= " OFFSET " . $relationData['offset'];
        }
    
        $relatedResults = $this->db->query($relatedQuery);
    
        foreach ($results as &$result) {
            $result['_' . $relation] = [];
            foreach ($relatedResults as $related) {
                if ($related[$relationData['foreign_key']] == $result[$relationData['local_key']]) {
                    if (!empty($relationData['relations'])) {
                        foreach ($relationData['relations'] as $nestedRelation => $nestedRelationData) {
                            $related['_' . $nestedRelation] = $this->loadRelation([$related], $nestedRelation, $nestedRelationData)[0]['_' . $nestedRelation] ?? [];
                        }
                    }
                    $result['_' . $relation][] = $related;
                }
            }
        }
    
        return $results;
    }
    
}
