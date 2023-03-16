<?php

namespace Fiasco\TabularOpenapi\Columns;

use Fiasco\TabularOpenapi\Schema;
use TypeError;

class MixedValueFormatColumn implements ColumnInterface {
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
            'NULL' => 'plain',
            'string' => 'plain',
            'integer' => 'plain',
            'boolean' => 'plain',
            'double' => 'plain',
            'object' => 'json',
            'array' => 'json',
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