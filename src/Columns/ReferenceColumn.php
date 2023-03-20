<?php

namespace Fiasco\TabularOpenapi\Columns;

use cebe\openapi\spec\Reference;
use Fiasco\TabularOpenapi\SchemaException;
use Fiasco\TabularOpenapi\TableOptions;
use Fiasco\TabularOpenapi\Values\Reference as ValuesReference;
use Generator;
use TypeError;

class ReferenceColumn implements ColumnInterface {
    protected array $values;
    public readonly string $ref;

    public function __construct(
        public readonly string $prefix,
        public readonly string $name, 
        public readonly Reference $reference, 
        public readonly string $tableName,
        public readonly int $options = TableOptions::STORE_REF->value,
        public readonly bool $nullable = true
    )
    {
        $this->ref = $reference->getReference();
    }

    public function insert(int $index, $value) {
        if (!$this->nullable && is_null($value)) {
            throw new SchemaException("Null value is not allowed in {$this->tableName}.{$this->name}.");
        }

        if (!in_array(gettype($value), ['object', 'array'])) {
            throw new SchemaException("{$this->tableName}.{$this->name} value must be of type object or array: Value given: ".gettype($value).print_r($value, 1));
        }
        $this->values[$index] = $value;
    }

    public function get(int $index):Generator {
        if (!array_key_exists($index, $this->values)) {
            throw new TypeError("Row index of $index invalid for column: {$this->tableName}.{$this->name}.");
        }
        yield new ValuesReference($index, $this->ref, $this->values[$index], $this->name);
    }
}