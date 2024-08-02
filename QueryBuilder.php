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

    public function with(array $relations)
    {

        if (!empty($relations)) {
            if (!empty($this->table['relations'])) {
                foreach ($relations as $relation) {
                    if (array_key_exists($relation, $this->table['relations'])) {
                        $this->relations[$relation] = $this->table['relations'][$relation];
                    }
                }
            }
        }

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

        // TODO: relation varsa  ilgili tabloya ait local key'i ekle
        if (!empty($this->relations)) {
            foreach ($this->relations as $relation => $relationData) {
                $this->columns[] = $this->table['relations'][$relation]['local_key'];
            }
        }

        $this->columns = array_unique($this->columns);

        $query = "SELECT " . implode(', ', $this->columns) . " FROM " . $this->tablename;

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
        $ids = array_unique($ids);

        // Fetch related data
        $relatedQuery = "SELECT * FROM $relatedTable WHERE $foreignKey IN (" . implode(',', $ids) . ")";

        $relatedResults = $this->db->query($relatedQuery);

        // Attach related results to main results
        foreach ($results as &$result) {
            $result['_' . $relation] = array_filter($relatedResults, function ($related) use ($result, $foreignKey, $localKey) {
                return $related[$foreignKey] == $result[$localKey];
            });
        }

        return $results;
    }
}
