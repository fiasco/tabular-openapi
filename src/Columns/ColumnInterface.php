<?php

namespace Fiasco\TabularOpenapi\Columns;

interface ColumnInterface {
    public function insert(int $index, $value, string $table_uuid);
    public function get(int $index);
}