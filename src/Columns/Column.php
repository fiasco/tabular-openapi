<?php

namespace Fiasco\TabularOpenapi\Columns;

use cebe\openapi\spec\Schema as SpecSchema;
use Fiasco\TabularOpenapi\Schema;
use Fiasco\TabularOpenapi\SchemaException;
use TypeError;

class Column implements ColumnInterface {
    protected array $values;

    public function __construct(
        public readonly string $name, 
        public readonly SpecSchema $openApiSchema, 
        public readonly Schema $schema,
        public readonly string $tableName
    )
    {
    }

    public function insert(int $index, $value, string $table_uuid) {
        $valid = match($this->openApiSchema->type) {
            'string' => is_string($value),
            'number' => is_numeric($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            default => true
        };
        if (!$valid && !(is_null($value) && $this->openApiSchema->nullable)) {
            throw new SchemaException("{$this->tableName}.{$this->name} value must be of type '{$this->openApiSchema->type}': Value given: ".gettype($value).print_r($value, 1));
        }
        $this->values[$index] = $value;
    }

    public function get(int $index) {
        if (!array_key_exists($index, $this->values)) {
            throw new TypeError("Row index of $index invalid for column: {$this->tableName}.{$this->name}.");
        }
        return $this->values[$index];
    }
}