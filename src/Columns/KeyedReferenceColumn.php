<?php

namespace Fiasco\TabularOpenapi\Columns;


class KeyedReferenceColumn extends ReferenceColumn {
    public function insert(int $index, $value, string $table_uuid) {
        $pairs = [];
        foreach ($value as $key => $row) {
            $i = $this->referenceTable->addRow($row, $table_uuid, $index);
            $pairs[] = [$i, $key];
        }
        Column::insert($index, $pairs, $table_uuid);
    }

    public function get(int $index) {
        return count(Column::get($index));
    }
}