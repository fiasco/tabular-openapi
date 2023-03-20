<?php

namespace Fiasco\TabularOpenapi\Values;

class Value {
    public function __construct(public readonly string|bool|int|float|null $value, public readonly int $index = 0)
    {
    }
}