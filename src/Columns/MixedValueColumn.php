<?php

namespace Fiasco\TabularOpenapi\Columns;

use Fiasco\TabularOpenapi\Schema;
use TypeError;

class MixedValueColumn implements ColumnInterface {
    protected array $values;

    public function __construct(
        public readonly string $name, 
        public readonly Schema $schema,
        public readonly string $tableName
    )
    {
    }

    public function insert(int $index, $value, string $table_uuid)
    {
        $value = match(gettype($value)) {
            'NULL' => $value,
            'string' => $value,
            'integer' => $value,
            'boolean' => $value,
            'double' => $value,
            'object' => json_encode($value),
            'array' => json_encode($value),
        };
        $this->values[$index] = $value;
    }

    public function get(int $index) {
        if (!array_key_exists($index, $this->values)) {
            throw new TypeError("Row index of $index invalid for column: {$this->tableName}.{$this->name}.");
        }
        return $this->values[$index];
    }
}