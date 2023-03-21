<?php

namespace Fiasco\TabularOpenapi\Columns;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Fiasco\TabularOpenapi\SchemaException;
use Fiasco\TabularOpenapi\Values\Reference as ValuesReference;
use Generator;

class ObjectColumn implements ColumnInterface {
    protected array $references;
    protected DynamicColumns|CollapsedReferenceColumn $dynamic;
    protected array $columns;

    public function __construct(
        public readonly Schema|Reference $schema,
        public readonly string $tableName,
        public readonly string $name,
        public readonly string $columnPrefix = '',
    ) {
        foreach ($schema->properties ?? [] as $column => $info) {
            $this->columns[$column] = $this->buildColumn($column, $info);
        }
        if (($schema->additionalProperties ?? false) instanceof Reference) {
            $this->dynamic = new CollapsedReferenceColumn(
                name: $name,
                reference: $schema->additionalProperties,
                tableName: $tableName
            );
        }
        elseif ($schema->additionalProperties ?? false) {
            $this->dynamic = new DynamicColumns(
                additionalProperties: $schema->additionalProperties ?? false,
                tableName: $tableName,
                columnPrefix: $name.'.'
            );
        }
    }

    /**
     * Build a column based on the schema data type of the column.
     */
    protected function buildColumn(string $name, Schema $info) {
        return match ($info->type) {
            'object' => new static($info, $this->tableName, $this->name.'.', $name),
            'array' => new CollapsedColumn($this->name . '.' . $name, $info, $this->tableName),
            default => new Column($this->name . '.' . $name, $info, $this->tableName)
        };
    }

    /**
     * {@inheritdoc}
     */
    public function insert(int $index, $value)
    {
        // Store references since there will be no known column structure.
        if ($this->schema instanceof Reference) {
            $this->references[$index] = $value;
            return;
        }
        if (!is_object($value) && !is_array($value)) {
            throw new SchemaException(get_class($this) . " expects an object or array to be passed as an insertable value. " . ucfirst(gettype($value)) . " given for '{$this->tableName}.{$this->name}'.");
        }
        $fields = is_object($value) ? get_object_vars($value) : $value;
        $dynamic = [];
        foreach ($fields as $field => $value) {
            if (!isset($this->columns[$field])) {
                $dynamic[$field] = $value;
                continue;
            }
            $this->columns[$field]->insert($index, $value);
        }
        if (!isset($this->dynamic) || empty($dynamic)) {
            return;
        }
        $this->dynamic->insert($index, $dynamic);
    }

    /**
     * {@inheritdoc}
     */
    public function get(int $index):Generator {
        // Return reference objects when schema is a reference.
        if ($this->schema instanceof Reference) {
            yield new ValuesReference($index, $this->schema->getReference(), $this->references[$index], $this->name);
        }
        foreach ($this->columns ?? [] as $column) {
            yield $column->get($index);
        }
        if (isset($this->dynamic)) {
            foreach ($this->dynamic->get($index) as $column) {
                yield $column;
            }
        }
    }
}