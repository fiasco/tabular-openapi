<?php

namespace Fiasco\TabularOpenapi\Columns;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Fiasco\TabularOpenapi\Values\Reference as ValuesReference;
use Generator;

class ObjectColumn extends DynamicColumns {
    protected array $references;

    public function __construct(
        public readonly Schema|Reference $schema,
        string $tableName,
        string $name,
        string $columnPrefix = '',
    ) {
        parent::__construct(
            additionalProperties: $schema->additionalProperties ?? false,
            tableName: $tableName,
            columnPrefix: $columnPrefix,
            name: $name
        );
        foreach ($schema->properties ?? [] as $column => $info) {
            $this->columns[$column] = $this->buildColumn($column, $info);
        } 
    }

    /**
     * Build a column based on the schema data type of the column.
     */
    protected function buildColumn(string $name, Schema $info) {
        return match ($info->type) {
            'object' => new static($info, $this->tableName, $this->name.'_', $name),
            'array' => new CollapsedColumn($this->name . '_' . $name, $info, $this->tableName),
            default => new Column($this->name . '_' . $name, $info, $this->tableName)
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
        parent::insert($index, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function get(int $index):Generator {
        // Return reference objects when schema is a reference.
        if ($this->schema instanceof Reference) {
            yield new ValuesReference($index, $this->schema->getReference(), $this->references[$index], $this->name);
        }
        return parent::get($index);
    }
}