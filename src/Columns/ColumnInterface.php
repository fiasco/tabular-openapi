<?php

namespace Fiasco\TabularOpenapi\Columns;

use Generator;

interface ColumnInterface {
    public function insert(int $index, $value);
    public function get(int $index):Generator;
}