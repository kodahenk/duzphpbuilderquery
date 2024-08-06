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
            if (!empty($mappingTableData[$relation])) {
                $filteredRelations[$relation] = $mappingTableData[$relation];
                if (!empty($relationData['columns'])) {
                    $filteredRelations[$relation]['columns'] = $relationData['columns'];
                    $filteredRelations[$relation]['columns'][] = $mappingTableData[$relation]['foreign_key'];
                } else {
                    $filteredRelations[$relation]['columns'] = ['*'];
                }

                if (!empty($relationData['limit'])) {
                    $filteredRelations[$relation]['limit'] = $relationData['limit'];
                }

                if (!empty($relationData['offset'])) {
                    $filteredRelations[$relation]['offset'] = $relationData['offset'];
                }

                if (isset($filteredRelations[$relation]['relations'])) {
                    unset($filteredRelations[$relation]['relations']);
                }

                if (!empty($relationData['relations']) AND !empty($mappingTableData[$relation]['relations'])) {
                    $filteredRelations[$relation]['relations'] = $this->relationFilter($relationData['relations'], $mappingTableData[$relation]['relations'], $mappingTableData[$relation]['related_table']);
                }
            }
        }

        return $filteredRelations;
    }



    public function with(array $requestTableData)
    {
        $this->relations = $this->relationFilter($requestTableData, $this->table['relations'], $this->tablename);
        devoLog($this->relations);
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
        $columns = implode(', ', $relationData['columns']);

        $relatedQuery = "SELECT {$columns} FROM {$relationData['related_table']} WHERE {$relationData['foreign_key']} IN (" . implode(',', $ids) . ")";

        if (isset($relationData['limit'])) {
            $relatedQuery .= " LIMIT " . $relationData['limit'];
        }
        if (isset($relationData['offset'])) {
            $relatedQuery .= " OFFSET " . $relationData['offset'];
        }

        devoLog($relatedQuery);
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
