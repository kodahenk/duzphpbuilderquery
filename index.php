<?php

class QueryBuilderService
{
    protected $pdo;
    protected $table;
    protected $select = '*';
    protected $whereConditions = [];
    protected $joinConditions = [];
    protected $limit;
    protected $offset;
    protected $orderBy;
    protected $orderDirection = 'ASC';
    protected $relations = [];
    protected $relationData = [];

    public function __construct($dsn, $username, $password)
    {
        try {
            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public function buildQuery($request)
    {
        // Get URL parameters
        $this->table = $request['table'];
        $this->select = $request['select'] ?? '*';
        $this->limit = $request['limit'] ?? null;
        $this->offset = isset($request['page']) ? ($request['page'] - 1) * $this->limit : null;
        $this->orderBy = $request['order_by'] ?? null;
        $this->orderDirection = $request['order_direction'] ?? 'ASC';

        // Get JSON body parameters
        $conditions = $request['conditions'] ?? [];
        $this->relations = $request['relations'] ?? [];
        $condition_logic = $request['condition_logic'] ?? null;
        $format = $request['format'] ?? 'json';

        // Apply conditions
        $this->applyConditions($conditions, $condition_logic);

        // Apply dynamic where conditions from body
        $this->applyDynamicWhere($request);

        // Apply relations
        $this->applyRelations($this->relations);

        // Build and execute query
        $results = $this->executeQuery();

        // Retrieve relations data
        $this->retrieveRelations($this->relations);

        // Merge relations data into results
        $resultsWithRelations = $this->mergeRelationsIntoResults($results);

        // Return results in specified format
        return $this->formatResults($resultsWithRelations, $format);
    }

    protected function applyConditions($conditions, $condition_logic)
    {
        if ($conditions) {
            $conditionGroups = [];
            $currentGroup = [];
            $operator = 'AND';

            foreach ($conditions as $condition) {
                if (isset($condition['group']) && $condition['group']) {
                    if ($currentGroup) {
                        $conditionGroups[] = [$operator => $currentGroup];
                        $currentGroup = [];
                    }
                    $operator = strtoupper($condition['group']) === 'OR' ? 'OR' : 'AND';
                } else {
                    $currentGroup[] = [
                        'field' => $condition['field'],
                        'operator' => $condition['operator'],
                        'value' => $condition['value']
                    ];
                }
            }
            if ($currentGroup) {
                $conditionGroups[] = [$operator => $currentGroup];
            }

            $this->applyConditionGroups($conditionGroups, $condition_logic);
        }
    }

    protected function applyConditionGroups($conditionGroups, $condition_logic)
    {
        $conditions = [];
        foreach ($conditionGroups as $group) {
            $groupConditions = [];
            foreach ($group as $operator => $conditionsArray) {
                $groupConditions[] = implode(' AND ', array_map(function($cond) {
                    return "{$cond['field']} {$cond['operator']} ?";
                }, $conditionsArray));
                $this->whereConditions = array_merge($this->whereConditions, array_map(function($cond) {
                    return $cond['value'];
                }, $conditionsArray));
            }
            $conditions[] = '(' . implode(' ' . $operator . ' ', $groupConditions) . ')';
        }
        $this->whereConditions = array_merge($this->whereConditions, $conditions);
    }

    protected function applyDynamicWhere($data)
    {
        foreach ($data as $key => $value) {
            if (strpos($key, 'where__') === 0) {
                $field = substr($key, 7);
                $this->whereConditions[] = "$field = ?";
                $this->whereConditions[] = $value;
            }
        }
    }

    protected function applyRelations($relations, $parentTable = null)
    {
        $currentTable = $parentTable ?: $this->table;
        
        foreach ($relations as $relation => $relationDetails) {
            $relatedTable = $relationDetails['table'];
            $foreignKey = $relationDetails['foreign_key'];
            $localKey = $relationDetails['local_key'];
            $joinType = isset($relationDetails['type']) ? strtoupper($relationDetails['type']) : 'LEFT JOIN';

            $this->joinConditions[] = "$joinType $relatedTable ON $currentTable.$localKey = $relatedTable.$foreignKey";

            if (isset($relationDetails['select'])) {
                $this->select .= ', ' . implode(', ', array_map(function($col) use ($relatedTable) {
                    return "$relatedTable.$col";
                }, explode(',', $relationDetails['select'])));
            }

            if (isset($relationDetails['conditions']) && is_array($relationDetails['conditions'])) {
                foreach ($relationDetails['conditions'] as $condition) {
                    $this->whereConditions[] = "$relatedTable.{$condition['field']} {$condition['operator']} ?";
                    $this->whereConditions[] = $condition['value'];
                }
            }

            if (isset($relationDetails['relations'])) {
                // Recursively apply nested relations
                $this->applyRelations($relationDetails['relations'], $relatedTable);
            }
        }
    }

    protected function retrieveRelations($relations, $parentData = [])
    {
        foreach ($relations as $relation => $relationDetails) {
            $relatedTable = $relationDetails['table'];
            $foreignKey = $relationDetails['foreign_key'];
            $localKey = $relationDetails['local_key'];
            $select = $relationDetails['select'] ?? '*';

            // Build the query to retrieve related data
            $query = "SELECT $select FROM $relatedTable WHERE $foreignKey IN (" . implode(',', array_map('intval', $this->getForeignKeys($relatedTable, $localKey))) . ")";
            $stmt = $this->pdo->query($query);
            $this->relationData[$relation] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (isset($relationDetails['relations'])) {
                // Recursively retrieve nested relations
                $this->retrieveRelations($relationDetails['relations'], $this->relationData[$relation]);
            }
        }
    }

    protected function getForeignKeys($relatedTable, $localKey)
    {
        $sql = "SELECT DISTINCT $localKey FROM {$this->table}";
        $stmt = $this->pdo->query($sql);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), $localKey);
    }

    protected function executeQuery()
    {
        $sql = "SELECT {$this->select} FROM {$this->table}";

        // Apply JOIN clauses for relations
        if (!empty($this->joinConditions)) {
            $sql .= ' ' . implode(' ', $this->joinConditions);
        }

        if (!empty($this->whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', array_map(function($cond) {
                return $cond;
            }, array_filter($this->whereConditions, function($value) {
                return is_string($value);
            })));
        }

        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy} {$this->orderDirection}";
        }

        if ($this->limit) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset) {
            $sql .= " OFFSET {$this->offset}";
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_filter($this->whereConditions, function($value) {
                return is_scalar($value);
            }));
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Log error
            error_log("SQL Error: " . $e->getMessage());
            return [];
        }
    }

    protected function mergeRelationsIntoResults($results)
    {
        foreach ($results as &$result) {
            foreach ($this->relations as $relation => $relationDetails) {
                $foreignKey = $relationDetails['foreign_key'];
                $result[$relation] = array_filter($this->relationData[$relation] ?? [], function($item) use ($foreignKey, $result) {
                    return $item[$foreignKey] == $result[$foreignKey];
                });

                // Recursively merge nested relations
                if (isset($relationDetails['relations'])) {
                    foreach ($result[$relation] as &$relatedItem) {
                        foreach ($relationDetails['relations'] as $nestedRelation => $nestedRelationDetails) {
                            $relatedItem[$nestedRelation] = array_filter($this->relationData[$nestedRelation] ?? [], function($item) use ($nestedRelationDetails, $relatedItem) {
                                return $item[$nestedRelationDetails['foreign_key']] == $relatedItem[$nestedRelationDetails['local_key']];
                            });
                        }
                    }
                }
            }
        }

        return $results;
    }

    protected function formatResults($results, $format)
    {
        switch (strtolower($format)) {
            case 'json':
                return json_encode($results, JSON_PRETTY_PRINT);
            case 'xml':
                $xml = new SimpleXMLElement('<root/>');
                array_walk_recursive($results, function($value, $key) use ($xml) {
                    $xml->addChild($key, htmlspecialchars($value));
                });
                return $xml->asXML();
            default:
                return $results;
        }
    }
}

// Usage example
$request = [
    'table' => 'users',
    'relations' => [
        'posts' => [
            'table' => 'posts',
            'foreign_key' => 'user_id',
            'local_key' => 'id',
        ]
    ]
];



// 

$dsn = 'mysql:host=localhost;dbname=devorhan';
$username = 'root';
$password = '';

$service = new QueryBuilderService($dsn, $username, $password);
$response = $service->buildQuery($request);


echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
