<?php

namespace Fiasco\TabularOpenapi;

use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema as SpecSchema;
use Fiasco\TabularOpenapi\Columns\ArrayedColumn;
use Fiasco\TabularOpenapi\Columns\Column;
use Fiasco\TabularOpenapi\Columns\KeyedReferenceColumn;
use Fiasco\TabularOpenapi\Columns\MixedValueColumn;
use Fiasco\TabularOpenapi\Columns\MixedValueFormatColumn;
use Fiasco\TabularOpenapi\Columns\ReferenceColumn;

class Schema {
    public readonly OpenApi $openApi;
    protected array $tables;

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
        foreach ($table_schema->properties as $property => $info) {
            $columns = array_merge($columns, $this->getNormalizedColumns($property, $info, $name));
        }
        $this->tables[$name] = new Table($name, ...$columns);
        return $this->tables[$name];
    }

    public function addTable(Table $table) {
        $this->tables[$table->name] = $table;
    }

    public function getTables():array
    {
        return $this->tables;
    }

    public function getReference($ref):SpecSchema
    {
        $reference = new Reference(['$ref' => $ref]);
        $context = new ReferenceContext($this->openApi, $this->uri);
        return clone $reference->resolve($context);
    }

    public function getNormalizedColumns(string $name, SpecSchema|Reference $info, string $table_name):array {
        $columns = [];
        if ($info instanceof Reference) {
            $columns[$name] = new ReferenceColumn(
                name: $name, 
                reference: $info, 
                schema: $this,
                tableName: $table_name
            );
            return $columns;
        }

        switch ($info->type) {
            case 'string':
            case 'number':
            case 'integer':
            case 'boolean':
                $columns[$name] = new Column(name: $name, openApiSchema: $info, schema: $this, tableName: $table_name);
                break;
            case 'array':
                if ($info->items instanceof Reference) {
                    $columns[$name] = new KeyedReferenceColumn(name: $name, reference: $info->items, schema: $this, tableName: $table_name);
                }
                else {
                    $columns[$name] = new ArrayedColumn(name: $name, openApiSchema: $info->items ?? new SpecSchema([]), schema: $this, tableName: $table_name);
                }
                break;
            case 'object':
                // Flatten object into table schema.
                foreach ($info->properties as $property => $property_info) {
                    $columns = array_merge($columns, $this->getNormalizedColumns("{$name}_{$property}", $property_info, $table_name));
                }
                if ($info->additionalProperties instanceof Reference) {
                    $columns[$name] = new KeyedReferenceColumn(name: $name, reference: $info->additionalProperties, schema: $this, tableName: $table_name);
                
                }
                if (empty($columns)) {
                    $columns[$name] = new ArrayedColumn(name: $name, openApiSchema: new SpecSchema([]), schema: $this, tableName: $table_name);
                }
                break;
            default:
                $columns[$name] = new MixedValueColumn(name: $name, schema: $this, tableName: $table_name);
                break;
        }
        return $columns;
    }
}