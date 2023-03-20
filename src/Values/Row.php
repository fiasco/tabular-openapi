<?php

namespace Fiasco\TabularOpenapi\Values;

use Generator;

class Row {
    public function __construct(public readonly Generator $value, public readonly int $index)
    {
        
    }
}