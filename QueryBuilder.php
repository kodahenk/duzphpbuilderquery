<?php

class QueryBuilder
{
    private $mappingTable;
    private $selectColumns = [];
    private $joinQueries = [];
    private $mainTable;
    private $pdo; // PDO nesnesi

    public function __construct(string $mainTable, PDO $pdo)
    {
        // Konfigürasyon dosyasını yükle
        $this->mappingTable = require 'config/mapping.php';
        $this->mainTable = $mainTable;
        $this->pdo = $pdo; // PDO nesnesini al
        $this->buildQuery();
    }
    
    private function buildQuery()
    {
        // Ana tablonun sütunlarını seç
        foreach ($this->mappingTable[$this->mainTable]['columns'] as $column) {
            $this->selectColumns[] = "{$this->mainTable}.{$column} AS {$this->mainTable}_{$column}";
        }
        
        // İlişkileri ekle
        $this->addRelations($this->mainTable);
    }
    
    private function addRelations(string $table)
    {
        if (isset($this->mappingTable[$table]['relations'])) {
            foreach ($this->mappingTable[$table]['relations'] as $relationName => $relation) {
                $alias = "{$relation['related_table']}_{$relationName}";
                
                // Sütunları seç
                foreach ($this->mappingTable[$relation['related_table']]['columns'] as $column) {
                    $this->selectColumns[] = "{$alias}.{$column} AS {$relation['related_table']}_{$column}";
                }
                
                // JOIN ifadesini oluştur
                $this->joinQueries[] = "LEFT JOIN {$relation['related_table']} AS {$alias} ON {$table}.{$relation['foreign_key']} = {$alias}.{$relation['local_key']}";
                
                // İlişkili tablonun ilişkilerini ekle
                $this->addRelations($relation['related_table']);
            }
        }
    }
    
    public function getSQL(): string
    {
        $selectQuery = implode(', ', $this->selectColumns);
        $joinQuery = implode(' ', $this->joinQueries);
        return "SELECT $selectQuery FROM {$this->mainTable} $joinQuery";
    }
    
    public function execute(): array
    {
        $sql = $this->getSQL();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
