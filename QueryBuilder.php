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

    private function setRelationsArray(array $relations, $table = null)
    {
        $tableRelations = $table ? $table['relations'] : $this->table['relations'];

        $recursiveRelations = [];

        foreach ($relations as $relation => $relationOptions) {
            $relationName = is_string($relation) ? $relation : $relationOptions;
            $relationParts = explode('.', $relationName);
            $currentRelationName = array_shift($relationParts);

            if (isset($tableRelations[$currentRelationName])) {
                $currentRelation = $tableRelations[$currentRelationName];

                if (!empty($relationParts)) {
                    // Geriye kalan ilişki parçalarını recursive olarak işlemek
                    $nestedRelation = implode('.', $relationParts);
                    $currentRelation['relations'] = $this->setRelationsArray([$nestedRelation], $currentRelation);
                } else {
                    $currentRelation['relations'] = [];
                }

                // Relation options (limit, offset, columns) varsa ekle
                if (is_array($relationOptions)) {
                    if (isset($relationOptions['columns'])) {
                        $relationOptions['columns'][] = $currentRelation['foreign_key'];
                        $currentRelation['columns'] = array_unique($relationOptions['columns']);
                    } else {
                        $currentRelation['columns'] = ['*'];
                    }

                    if (isset($relationOptions['limit'])) {
                        $currentRelation['limit'] = $relationOptions['limit'];
                    }

                    if (isset($relationOptions['offset'])) {
                        $currentRelation['offset'] = $relationOptions['offset'];
                    }
                } else {
                    $currentRelation['columns'] = ['*'];
                }

                $recursiveRelations[$currentRelationName] = $currentRelation;
            }
        }

        return $recursiveRelations;
    }

    public function with(array $relations)
    {
        $this->relations = $this->setRelationsArray($relations);
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
        if (!empty($relationData['relations'])) {
            foreach ($relationData['relations'] as $nestedRelation => $nestedRelationData) {
                $results = $this->loadRelation($results, $nestedRelation, $nestedRelationData);
            }
        }

        $relatedTable = $relationData['related_table'];
        $foreignKey = $relationData['foreign_key'];
        $localKey = $relationData['local_key'];

        $ids = array_column($results, $localKey);
        $ids = array_unique($ids);

        $columns = implode(', ', $relationData['columns']);
        $relatedQuery = "SELECT $columns FROM $relatedTable WHERE $foreignKey IN (" . implode(',', $ids) . ")";
        
        if (isset($relationData['limit'])) {
            $relatedQuery .= " LIMIT " . $relationData['limit'];
        }
        if (isset($relationData['offset'])) {
            $relatedQuery .= " OFFSET " . $relationData['offset'];
        }

        $relatedResults = $this->db->query($relatedQuery);

        foreach ($results as &$result) {
            $result['_' . $relation] = array_filter($relatedResults, function ($related) use ($result, $foreignKey, $localKey) {
                return $related[$foreignKey] == $result[$localKey];
            });
        }

        return $results;
    }
}
