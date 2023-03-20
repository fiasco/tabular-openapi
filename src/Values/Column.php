<?php

namespace Fiasco\TabularOpenapi\Values;

use Fiasco\TabularOpenapi\Columns\ColumnInterface;

class Column {
    public function __construct(public readonly ColumnInterface $value, public readonly int $index = 0)
    {
        
    }
}