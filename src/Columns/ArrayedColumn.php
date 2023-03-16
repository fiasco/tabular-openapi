<?php

namespace Fiasco\TabularOpenapi\Columns;

use cebe\openapi\spec\Schema as SpecSchema;
use Fiasco\TabularOpenapi\Schema;
use Fiasco\TabularOpenapi\SchemaException;
use Fiasco\TabularOpenapi\Table;

class ArrayedColumn extends Column {
    public readonly Table $referenceTable;

    public function __construct(
        public readonly string $name, 
        public readonly SpecSchema $openApiSchema, 
        public readonly Schema $schema,
        public readonly string $tableName
    )
    {
        $key = $schema->getNormalizedColumns('key', new SpecSchema(['type' => 'string', 'nullable' => 'false']), $tableName.'_'.$name);
        $value = $schema->getNormalizedColumns('value', $openApiSchema, $tableName.'_'.$name);
        $format = ['format' => new MixedValueFormatColumn(name: 'format', schema: $schema, tableName:  $tableName.'_'.$name)];
        $this->referenceTable = new Table($tableName.'_'.$name, ...array_merge($key, $value, $format));
        $schema->addTable($this->referenceTable);
    }

    public function insert(int $index, $value, string $table_uuid) {
        $valid = match($this->openApiSchema->type ?? 'array') {
            'object' => is_object($value) || is_array($value),
            'array' => is_object($value) || is_array($value),
            default => false
        };
        if (!$valid) {
            throw new SchemaException("Cannot insert ".gettype($value)." into {$this->tableName}.{$this->name} of type {$this->openApiSchema->type}.");
        }
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        $i = [];
        if (!empty($value)) {
            foreach ($value as $key => $value) {
                $row = ['key' => $key, 'value' => $value, 'format' => $value];
                $i[] = $this->referenceTable->addRow($row, $table_uuid, $index);
            }
        }
        parent::insert($index, $i, $table_uuid);
    }

    public function get(int $index) {
        return count(parent::get($index));
    }
}