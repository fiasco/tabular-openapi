<?php

namespace Fiasco\TabularOpenapi\Columns;

use Fiasco\TabularOpenapi\Values\Reference as ValuesReference;
use Generator;
use TypeError;

class CollapsedReferenceColumn extends ReferenceColumn {
    public function get(int $index):Generator {
        if (!array_key_exists($index, $this->values)) {
            throw new TypeError("Row index of $index invalid for column: {$this->tableName}.{$this->name}.");
        }

        foreach (array_keys($this->values[$index]) as $i => $key) {
            yield new ValuesReference($i, $this->ref, $this->values[$index][$key], $this->name, $key);
        }
    }
}