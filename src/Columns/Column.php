<?php

namespace Fiasco\TabularOpenapi\Columns;

use cebe\openapi\spec\Schema as SpecSchema;
use DateTimeInterface;
use Exception;
use Fiasco\TabularOpenapi\SchemaException;
use Fiasco\TabularOpenapi\Values\Value;
use Generator;
use TypeError;
use UnitEnum;

class Column implements ColumnInterface {
    protected array $values;

    public function __construct(
        public readonly string $name, 
        public readonly SpecSchema $schema, 
        public readonly string $tableName
    )
    {
    }

    public function insert(int $index, $value) {
        // Normalize DateTime to string.
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('c');
        }

        if ($value instanceof UnitEnum) {
            $value = $value->value ?? $value->key;
        }

        if (is_null($value) && !$this->schema->nullable) {
            throw new SchemaException("{$this->tableName}.{$this->name} value cannot be null");
        }
        $valid = match($this->schema->type) {
            'string' => is_string($value),
            'number' => is_numeric($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            default => false
        };
        if (!$valid) {
            throw new SchemaException("{$this->tableName}.{$this->name} value must be of type '{$this->schema->type}': Value given: ".gettype($value).print_r($value, 1));
        }
        $this->values[$index] = $value;
    }

    public function get(int $index):Generator {
        if (!array_key_exists($index, $this->values)) {
            throw new TypeError("Row index of $index invalid for column: {$this->tableName}.{$this->name}.");
        }
        yield new Value($this->values[$index]);
    }

    public function count():int
    {
        return count($this->values);
    }
}