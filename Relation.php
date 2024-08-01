<?php

class Relation
{
    protected $foreignKey;
    protected $localKey;
    protected $relatedTable;
    protected $relations = [];
    protected $db;

    public function __construct($foreignKey, $localKey, $relatedTable, $relations = [])
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        $this->relatedTable = $relatedTable;
        $this->relations = $relations;
        $this->db = new Database(); // Create a new Database instance
    }

    public function getQuery($ids)
    {
        // Build query for the related table
        $query = "SELECT * FROM " . $this->relatedTable;
        
        if (!empty($ids)) {
            $query .= " WHERE " . $this->foreignKey . " IN (" . implode(',', $ids) . ")";
        }

        return $query;
    }

    public function load($results)
    {
        $ids = array_column($results, 'id');
        $query = $this->getQuery($ids);
        $relatedResults = $this->db->query($query);

        // Attach related data
        foreach ($results as &$result) {
            $result[$this->relatedTable] = array_filter($relatedResults, function ($related) use ($result) {
                return $related[$this->foreignKey] == $result['id'];
            });
        }

        return $results;
    }
}
