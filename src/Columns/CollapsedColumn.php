<?php

namespace Fiasco\TabularOpenapi\Columns;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Fiasco\TabularOpenapi\Values\Row;
use Generator;

class CollapsedColumn extends Column {

    public function get(int $index):Generator {
        foreach ($this->values[$index] as $i => $cell) {
            yield new Row($cell->get($i), $i);
        }
    }

    public function insert(int $index, $value) {
        $cells = [];
        foreach ($value as $i => $item) {
            if ($this->schema->items instanceof Reference) {
                $cell = new ObjectColumn($this->schema->items, $this->tableName, 'items', $this->name.'.');
            }
            $type = $this->schema->items instanceof Schema ? $this->schema->items->type : false;
            $cell ??= match ($type) {
                'object' => new ObjectColumn($this->schema->items, $this->tableName, 'items', $this->name.'.'),
                'array' => new CollapsedColumn($this->name . ".items", $this->schema->items ?? new Schema(['type' => 'string']), $this->tableName),
                default => new Column($this->name . ".items", $this->schema->items ?? new Schema(['type' => 'string']), $this->tableName)
            };
            $cell->insert($i, $item);
            $cells[] = $cell;
        }
        $this->values[$index] = $cells;
    }
}