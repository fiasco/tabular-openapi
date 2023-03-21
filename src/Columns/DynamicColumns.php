<?php

namespace Fiasco\TabularOpenapi\Columns;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Fiasco\TabularOpenapi\SchemaException;
use Fiasco\TabularOpenapi\Values\Column;
use Generator;
use UnexpectedValueException;

class DynamicColumns implements ColumnInterface
{
    protected array $columns;
    public readonly string $name;

    public function __construct(
        public readonly Schema|Reference|bool $additionalProperties,
        public readonly string $tableName,
        public readonly string $columnPrefix = '',
        string $name = '_dynamic'
    ) {
        $this->name = $columnPrefix.$name;
    }

    public function get(int $index):Generator
    {
        foreach ($this->columns ?? [] as $column) {
            yield new Column($column, $index);
        }
    }

    public function insert(int $index, $value)
    {
        if (!is_object($value) && !is_array($value)) {
            throw new SchemaException(get_class($this) . " expects an object or array to be passed as an insertable value. " . ucfirst(gettype($value)) . " given for '{$this->tableName}.{$this->name}'.");
        }
        $fields = is_object($value) ? get_object_vars($value) : $value;
        foreach ($fields as $field => $value) {
            $this->getDynamicColumn($field, $value)->insert($index, $value);
        }
    }

    protected function getDynamicColumn($name, $value):ColumnInterface
    {
        if (isset($this->columns[$name])) {
            return $this->columns[$name];
        }
        switch (true) {
                // OpenApi schema has additionalProperties with a defined schema and a schema exists for the given column.
            case ($this->additionalProperties instanceof Schema) && isset($this->additionalProperties->{$name}):
                $schema = $this->additionalProperties->{$name};
                break;

                // OpenApi schema allows additional properties but hasn't defined what they are.
            case $this->additionalProperties === true:
                $schema = new Schema(['type' => $this->getSchemaType($value)]);
                break;

                // OpenApi points to a another place in the schema which likely refers to inserting into a foreign table.
            case $this->additionalProperties instanceof Reference:
                $this->columns[$name] = new ReferenceColumn($this->columnPrefix . $name, $this->additionalProperties, $this->tableName);
                return $this->columns[$name];
                break;
            default:
                throw new UnexpectedValueException("Property 'additionalProperties' of " . get_class($this) . " contains an unexpected value: ".gettype($this->additionalProperties));
        }
        // Complex data types.
        if ($schema->type && $schema->type == 'object') {
            $this->columns[$name] ??= new ObjectColumn($schema, $this->tableName, $this->columnPrefix . $name, $name);
        } elseif ($schema->type && $schema->type == 'array') {
            $this->columns[$name] ??= new CollapsedColumn($this->columnPrefix . $name, $schema, $this->tableName);
        }
        
        $this->columns[$name] ??= new PaddedColumn(
            name: $this->columnPrefix . $name,
            schema: $schema,
            tableName: $this->tableName
        );
        return $this->columns[$name];
    }

    protected function getSchemaType($value): string
    {
        return match (gettype($value)) {
            'NULL' => 'string',
            'double' => 'number',
            'array' => array_is_list($value) ? 'array' : 'object',
            default => gettype($value)
        };
    }
}
