<?php

class QueryBuilderService
{
    protected $pdo;
    protected $table;
    protected $select = '*';
    protected $whereConditions = [];
    protected $relations = [];
    protected $limit;
    protected $offset;
    protected $orderBy;
    protected $orderDirection = 'ASC';

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

        // Build and execute query
        $results = $this->executeQuery();

        // Return results in specified format
        return $this->formatResults($results, $format);
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
        foreach ($conditionGroups as $group) {
            foreach ($group as $operator => $conditions) {
                $groupConditions = [];
                foreach ($conditions as $condition) {
                    $groupConditions[] = "{$condition['field']} {$condition['operator']} ?";
                    $this->whereConditions[] = $condition['value'];
                }
                $this->whereConditions = array_merge($this->whereConditions, $groupConditions);
            }
        }
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

    protected function applyRelations(&$sql)
    {
        $joins = [];
        foreach ($this->relations as $relation => $relationDetails) {
            $relatedTable = $relationDetails['table'];
            $foreignKey = $relationDetails['foreign_key'];
            $localKey = $relationDetails['local_key'];
            $joins[] = "LEFT JOIN $relatedTable ON {$this->table}.$localKey = $relatedTable.$foreignKey";

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
        }

        if (!empty($joins)) {
            $sql .= ' ' . implode(' ', $joins);
        }
    }

    protected function executeQuery()
    {
        $sql = "SELECT {$this->select} FROM {$this->table}";

        // Apply JOIN clauses for relations
        $this->applyRelations($sql);

        if (!empty($this->whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $this->whereConditions);
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
            $stmt->execute($this->whereConditions);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Log error
            error_log("SQL Error: " . $e->getMessage());
            return [];
        }
    }

    protected function formatResults($results, $format)
    {
        if ($format === 'json') {
            return json_encode($results);
        } elseif ($format === 'xml') {
            $xml = new SimpleXMLElement('<root/>');
            array_walk_recursive($results, array($xml, 'addChild'));
            return $xml->asXML();
        }

        return $results;
    }
}

// Usage example
$request = [
    'table' => 'users',
    'select' => 'id,name,email',
    'limit' => 10,
    'page' => 1,
    'where__status' => 'active',
    'relations' => [
        'posts' => [
            'table' => 'posts',
            'foreign_key' => 'user_id',
            'local_key' => 'id',
            'select' => 'id,title',
            'conditions' => [
                [
                    'field' => 'published',
                    'operator' => '=',
                    'value' => 1
                ]
            ]
        ]
    ]
];

$dsn = 'mysql:host=localhost;dbname=your_database';
$username = 'your_username';
$password = 'your_password';

$service = new QueryBuilderService($dsn, $username, $password);
$response = $service->buildQuery($request);
echo $response;
