<?php

class QueryBuilder
{
    protected $table;
    protected $columns = [];
    protected $relations = [];
    protected $conditions = [];
    protected $limit;
    protected $offset;

    public function __construct($table)
    {
        $this->table = $table;
    }

    public function select(array $columns)
    {
        $this->columns = $columns;
        return $this;
    }

    public function with(array $relations)
    {
        $this->relations = $relations;
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
        // Build the base query
        $query = "SELECT " . implode(', ', $this->columns) . " FROM " . $this->table;

        // Add conditions
        if (!empty($this->conditions)) {
            $conditions = array_map(function ($key, $value) {
                return "$key = '$value'";
            }, array_keys($this->conditions), $this->conditions);
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        // Add limit and offset
        if ($this->limit) {
            $query .= " LIMIT " . $this->limit;
        }
        if ($this->offset) {
            $query .= " OFFSET " . $this->offset;
        }

        // Execute the query and fetch results
        $results = $this->executeQuery($query);

        // Load relations
        foreach ($this->relations as $relation => $relationData) {
            $results = $this->loadRelation($results, $relation, $relationData);
        }

        return $results;
    }

    protected function executeQuery($query)
    {
        // For demonstration, just return the query string
        // Replace this with actual database execution
        return $query;
    }

    protected function loadRelation($results, $relation, $relationData)
    {
        // Load related data
        $relatedTable = $relationData['related_table'];
        $foreignKey = $relationData['foreign_key'];
        $localKey = $relationData['local_key'];

        $relatedQuery = "SELECT * FROM $relatedTable WHERE $foreignKey IN (" . implode(',', array_column($results, $localKey)) . ")";
        
        $relatedResults = $this->executeQuery($relatedQuery);

        // Attach related results to main results
        foreach ($results as &$result) {
            $result[$relation] = array_filter($relatedResults, function ($related) use ($result, $foreignKey) {
                return $related[$foreignKey] == $result['id'];
            });
        }

        return $results;
    }
}
