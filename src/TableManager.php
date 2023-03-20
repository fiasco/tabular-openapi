<?php

namespace Fiasco\TabularOpenapi;

use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Fiasco\TabularOpenapi\Columns\CollapsedColumn;
use Fiasco\TabularOpenapi\Columns\Column;
use Fiasco\TabularOpenapi\Columns\DynamicColumns;
use Fiasco\TabularOpenapi\Columns\ObjectColumn;

class TableManager {
    public readonly OpenApi $openApi;
    protected array $tables;
    protected array $references;

    public function __construct(public readonly string $uri)
    {
        $this->openApi = Reader::readFromJsonFile($uri, OpenApi::class, false);
    }

    public function getTable(string $name):Table
    {
        if (isset($this->tables[$name])) {
            return $this->tables[$name];
        }
        $table_schema = $this->getReference('#/components/schemas/'.$name);
        if ($table_schema->type != 'object') {
            throw new SchemaException("Schema for '$name' must be of type: object.");
        }
        $columns = [];
        foreach ($table_schema->properties ?? [] as $column => $info) {
            if ($info instanceof Reference) {
                $columns[] = new ObjectColumn(schema: $info, tableName: $name, name: $column);
                continue;
            }
            $columns[] = match ($info->type) {
                'object' => new ObjectColumn(schema: $info, tableName: $name, name: $column),
                'array' => new CollapsedColumn(name: $column, schema: $info, tableName: $name),
                default => new Column(name: $column, schema: $info, tableName: $name)
            };
        } 
        if ($table_schema->additionalProperties) {
            $columns[] = new DynamicColumns($table_schema->additionalProperties, $name);
        }
        $this->tables[$name] = new Table($name, ...$columns);
        return $this->tables[$name];
    }

    public function getTables():array
    {
        return $this->tables;
    }

    protected function getReference($ref):Schema
    {
        $reference = new Reference(['$ref' => $ref]);
        $context = new ReferenceContext($this->openApi, $this->uri);
        return clone $reference->resolve($context);
    }

    public function buildLookupTable():Table
    {
        do {
            $found = $this->findReferences();
        }
        while ($found > 0);

        $lookup_table = new Table('tabular_openapi_lookup',
            new Column(name: 'foreign_table', schema: new Schema(['type' => 'string']), tableName: 'tabular_openapi_lookup'),
            new Column(name: 'foreign_column', schema: new Schema(['type' => 'string']), tableName: 'tabular_openapi_lookup'),
            new Column(name: 'reference', schema: new Schema(['type' => 'string']), tableName: 'tabular_openapi_lookup'),
            new Column(name: 'uuid', schema: new Schema(['type' => 'string']), tableName: 'tabular_openapi_lookup'),
            new Column(name: 'row', schema: new Schema(['type' => 'integer']), tableName: 'tabular_openapi_lookup'),
        );


        foreach ($this->references as $foreign_table => $columns) {
            foreach ($columns as $foreign_column => $refs) {
                foreach ($refs as $ref => $ids) {
                    foreach ($ids as $id => $row_id) {
                        $lookup_table->insertRow([
                            'foreign_table' => $foreign_table,
                            'foreign_column' => $foreign_column,
                            'reference' => $ref,
                            'uuid' => $id,
                            'row' => $row_id
                        ]);
                    }
                }
            }
        }

        return $lookup_table;
    }

    protected function findReferences():int
    {
        $found = 0;
        foreach ($this->getTables() as $foreign_table) {
            if (isset($this->references[$foreign_table->name])) {
                continue;
            }
            // Traverse the generator to produce references.
            foreach ($foreign_table->fetchAll() as $row) {

            }
            foreach ($foreign_table->getReferences() as $ref => $columns) {
                $table_name = str_replace('#/components/schemas/', '', $ref);
                $table = $this->getTable($table_name);
                foreach ($columns as $column_name => $objects) {
                    foreach ($objects as $id => $object) {
                        if (isset($this->references[$foreign_table->name][$column_name][$ref][$id])) {
                            continue;
                        }
                        $object = is_object($object) ? get_object_vars($object) : $object;
                        $this->references[$foreign_table->name][$column_name][$ref][$id] = $table->insertRow($object);
                        $found++;
                    }
                }
            }
        }
        return $found;
    }

}