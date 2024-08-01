<?php

class Relation
{
    protected $foreignKey;
    protected $localKey;
    protected $relatedTable;
    protected $relations = [];

    public function __construct($foreignKey, $localKey, $relatedTable, $relations = [])
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        $this->relatedTable = $relatedTable;
        $this->relations = $relations;
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
        $relatedResults = $this->executeQuery($query);

        // Attach related data
        foreach ($results as &$result) {
            $result[$this->relatedTable] = array_filter($relatedResults, function ($related) use ($result) {
                return $related[$this->foreignKey] == $result['id'];
            });
        }

        return $results;
    }

    protected function executeQuery($query)
    {
        // For demonstration, just return the query string
        // Replace this with actual database execution
        return $query;
    }
}
