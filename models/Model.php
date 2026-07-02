<?php

namespace App\Models;

use App\Core\Database;
use PDO;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';

    protected function getDb(): PDO
    {
        return Database::getInstance();
    }

    protected function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function find(string $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function all(?string $orderBy = null): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($orderBy) {
            $sql .= " ORDER BY " . preg_replace('/[^a-zA-Z0-9_]/', '', $orderBy);
        }
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function where(array $conditions, ?string $orderBy = null): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $column => $value) {
                $paramName = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
                $whereParts[] = "{$column} = :{$paramName}";
                $params[$paramName] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereParts);
        }

        if ($orderBy) {
            $sql .= " ORDER BY " . preg_replace('/[^a-zA-Z0-9_]/', '', $orderBy);
        }

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): string
    {
        if (!isset($data[$this->primaryKey])) {
            $data[$this->primaryKey] = $this->generateUuid();
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($data);

        return $data[$this->primaryKey];
    }

    public function update(string $id, array $data): bool
    {
        $setParts = [];
        $params = ['id' => $id];

        foreach ($data as $column => $value) {
            if ($column === $this->primaryKey) {
                continue;
            }
            $setParts[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = :id",
            $this->table,
            implode(', ', $setParts),
            $this->primaryKey
        );

        $stmt = $this->getDb()->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(string $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->getDb()->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}
