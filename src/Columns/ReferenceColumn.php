<?php

namespace Fiasco\TabularOpenapi\Columns;

use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\Reference;
use Fiasco\TabularOpenapi\Schema;
use Fiasco\TabularOpenapi\SchemaException;
use Fiasco\TabularOpenapi\Table;

class ReferenceColumn extends Column {
    public readonly Table $referenceTable;

    public function __construct(
        string $name, 
        public readonly Reference $reference, 
        Schema $schema,
        string $tableName,
    )
    {
        $table_name = str_replace('#/components/schemas/', '', $reference->getReference());
        $this->referenceTable = $schema->getTable($table_name);
        $context = new ReferenceContext($schema->openApi, $schema->uri);
        parent::__construct($name, clone $reference->resolve($context), $schema, $tableName);
    }

    public function insert(int $index, $value, string $table_uuid) {
        $valid = match($this->openApiSchema->type) {
            'object' => is_object($value) || is_array($value),
            default => false
        };
        if (!$valid) {
            throw new SchemaException("Cannot insert ".gettype($value)." into {$this->tableName}.{$this->name} of type {$this->openApiSchema->type}.");
        }
        if (!empty($value)) {
            $i = $this->referenceTable->addRow($value, $table_uuid, $index);   
        }
        parent::insert($index, [$this->referenceTable->uuid, $i ?? null], $table_uuid);
    }

    public function get(int $index) {
        return $this->referenceTable->uuid;
    }

    public function getTable():Table
    {
        return $this->referenceTable;
    }
}