<?php

class QueryBuilder
{
    protected $table;
    protected $columns = [];
    protected $relations = [];
    protected $conditions = [];
    protected $limit;
    protected $offset;
    protected $db;

    public function __construct($table)
    {
        $this->table = $table;
        $this->db = new Database(); // Create a new Database instance
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
        $results = $this->db->query($query);

        // Load relations
        foreach ($this->relations as $relation => $relationData) {
            $results = $this->loadRelation($results, $relation, $relationData);
        }

        return $results;
    }

    protected function loadRelation($results, $relation, $relationData)
    {
       
        $relatedTable = $relationData['related_table'];
        $foreignKey = $relationData['foreign_key'];
        $localKey = $relationData['local_key'];

        // Extract IDs from results
        $ids = array_column($results, $localKey);

        

        // Fetch related data
        $relatedQuery = "SELECT * FROM $relatedTable WHERE $foreignKey IN (" . implode(',', $ids) . ")";
        $relatedResults = $this->db->query($relatedQuery);

        // Attach related results to main results
        foreach ($results as &$result) {
            $result[$relation] = array_filter($relatedResults, function ($related) use ($result, $foreignKey) {
                return $related[$foreignKey] == $result['id'];
            });
        }

        return $results;
    }
}
