<?php

namespace Fiasco\TabularOpenapi;

use cebe\openapi\spec\Reference as SpecReference;
use Exception;
use Fiasco\TabularOpenapi\Columns\ColumnInterface;
use Fiasco\TabularOpenapi\Columns\DynamicColumns;
use Fiasco\TabularOpenapi\Values\Column as ValuesColumn;
use Fiasco\TabularOpenapi\Values\Reference;
use Fiasco\TabularOpenapi\Values\Row;
use Fiasco\TabularOpenapi\Values\Value;
use Generator;
use TypeError;

class Table {
    public readonly array $columns;
    public readonly string $name;
    public readonly string $uuid;
    protected int $rows = 0;
    protected array $externalRows;

    public function __construct(string $_table_name, ColumnInterface ...$columns) 
    {
        $this->name = $_table_name;
        $this->columns = array_combine(array_map(fn($c) =>$c->name, $columns), $columns);
        $this->uuid = $this->generateUuid();
    }

    /**
     * Add a row to the table;
     * 
     * @return int the row index the row was inserted into the table.
     */
    public function insertRow(array $values):int {
        $column_keys = array_keys($this->columns);
        $column_values = [];
        foreach ($values as $column => $value) {
            if (is_int($column)) {
                $column = $column_keys[$column];
            }
            // Support for additionalProperties. See DynamicColumns.
            if (!isset($this->columns[$column]) && isset($this->columns['_dynamic']) && ($this->columns['_dynamic'] instanceof DynamicColumns)) {
                $column_values['_dynamic'][$column] = $value;
                continue;
            }
            if (!isset($this->columns[$column])) {
                throw new SchemaException("Unknown column {$this->name}.$column. Available: " . implode(', ', $column_keys));
            }
            $column_values[$column] = $value;
        }
        foreach ($column_keys as $key) {
            try {
                $this->columns[$key]->insert($this->rows, $column_values[$key] ?? null);
            }
            catch (TypeError $e) {
                throw new SchemaException("{$this->name}.{$key} cannot insert value of type ".gettype($column_values[$key])." into instance of ".get_class($this->columns[$key]), 0, $e);
            }
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
            foreach ($this->fetch($i) as $row) {
                yield $row;
            }
        }
    }

    /**
     * Fetch a row at a given row index.
     */
    public function fetch(int $i):Generator {
        $grid = [];
        foreach ($this->columns as $column) {
            foreach ($column->get($i) as $value) {
                $this->updateGrid($grid, $column, $value);
            }
        }

        $rows = [];
        $max = 0;
        foreach ($grid as $column => $values) {
            $max = max($max, count($values));
            foreach ($values as $i => $value) {
                $rows[$i][$column] = $value;
            }
        }

        foreach ($rows as $i => &$row) {
            foreach ($grid as $column => $values) {
                $row[$column] ??= end($grid[$column]);
            }

            $row['_row'] = $i;
            yield $row;
        }
    }

    protected function getGridValue($value, int $depth, string $column_name):Row|ValuesColumn|Reference|Value {
        $gridValue = match (true) {
            $value instanceof Generator => new Row($value, $depth),
            $value instanceof ColumnInterface => new ValuesColumn($value, $depth),
            $value instanceof SpecReference => new Reference($depth, $value->getReference(), $value, $column_name),
            is_string($value) || is_bool($value) || is_int($value) || is_double($value) || is_null($value) => new Value($value, $depth),
            $value instanceof Reference => $value,
            $value instanceof Row => new Row($value->value, $depth),
            $value instanceof ValuesColumn => new ValuesColumn($value->value, $depth),
            $value instanceof Value => new Value($value->value, $depth),
            default => throw new Exception("Unknown grid value ".gettype($value).'('.get_class($value).')'),
        };
        return $gridValue;
    }

    /**
     * Update the grid of rows retrived from a single row fetch across all column types.
     */
    protected function updateGrid(array &$grid, ColumnInterface $column, Row|Value|ValuesColumn|Reference $data) {
        switch (true) {
            // A Row value means we add the index as another row on the grid.
            case $data instanceof Row:
                foreach ($data->value as $depth => $item) {
                    $this->updateGrid($grid, $column, $this->getGridValue($item, $data->index, $column->name));
                }
                break;
            // A simple Value means we can place the cell verbatim on the first row.
            case $data instanceof Value:
                $grid[$column->name][$data->index] = $data->value;
                break;
            // A Column means we're expanding the grid to include an additional column.
            case $data instanceof ValuesColumn:
                foreach ($data->value->get($data->index) as $column_data) {
                    $this->updateGrid($grid, $data->value, $this->getGridValue($column_data, $column_data->index, $column->name));
                }
                break;
            case $data instanceof Reference:
                $this->externalRows[$data->ref][$data->name][$data->key ?? $data->uuid] = $data->value;
                $grid[$column->name][$data->index] = $data->key ?? $data->uuid;
                break;
        }
    }

    /**
     * Retrieve an object value of an reference schema.
     */
    public function getReference(string $ref, string $column_name, string $id):array
    {
        return $this->externalRows[$ref][$column_name][$id] ?? throw new Exception("No such reference: $id of $ref for {$this->name}.$column_name.");
    }

    public function getReferences():array
    {
        return $this->externalRows ?? [];
    }

    /**
     * Generate a UUIDv4 string.
     */
    protected function generateUuid():string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}