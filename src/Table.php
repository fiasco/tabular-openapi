<?php

namespace Fiasco\TabularOpenapi;

use cebe\openapi\spec\Schema;
use Fiasco\TabularOpenapi\Columns\Column;
use Fiasco\TabularOpenapi\Columns\ColumnInterface;
use Generator;

class Table {
    public readonly array $columns;
    public readonly string $name;
    public readonly string $uuid;
    protected int $rows = 0;

    public function __construct(string $_table_name, ColumnInterface ...$columns) 
    {
        $this->name = $_table_name;
        // Internal column for tracking reference tables.
        if (count($columns)) {
            $columns['_source'] = new Column('_source', new Schema(['type' => 'string', 'nullable' => true]), current($columns)->schema, $this->name);
            $columns['_source_index'] = new Column('_source_index', new Schema(['type' => 'integer', 'nullable' => true]), current($columns)->schema, $this->name);
        }
        
        $this->columns = array_combine(array_map(fn($c) =>$c->name, $columns), $columns);
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $this->uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Add a row to the table;
     * 
     * @return int the row index the row was inserted into the table.
     */
    public function addRow(array $values, ?string $source = null, ?int $source_index = null):int {
        $column_keys = array_keys($this->columns);
        $column_values = [];
        foreach ($values as $column => $value) {
            if (is_int($column)) {
                $column = $column_keys[$column];
            }
            if (!isset($this->columns[$column])) {
                throw new SchemaException("Unknown column {$this->name}.$column. Available: " . implode(', ', $column_keys));
            }
            $column_values[$column] = $value;
        }
        if (in_array('_source', $column_keys)) {
            $column_values['_source'] = $source;
            $column_values['_source_index'] = $source_index;
        }
        foreach ($column_keys as $key) {
            $this->columns[$key]->insert($this->rows, $column_values[$key] ?? null, $this->uuid);
        }
        return $this->rows++;
    }

    /**
     * Get the total number of rows in the table.
     */
    public function getRowsTotal():int
    {
        return $this->rows;
    }

    /**
     * Fetch all rows from the table.
     */
    public function fetchAll():Generator
    { 
        for ($i=0; $i < $this->rows; $i++) {
            yield $this->fetch($i);
        }
    }

    /**
     * Fetch a row at a given row index.
     */
    public function fetch(int $i) {
        return array_merge(['_row' => $i], array_map(function (ColumnInterface $column) use ($i) {
            return $column->get($i);
        }, $this->columns));
    }
}