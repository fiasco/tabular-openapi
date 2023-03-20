<?php

namespace Fiasco\TabularOpenapi;

enum TableOptions:int {
    case STORE_REF = 1;
    case STORE_RESOLVED = 2;
    case FETCH_FIRST_ONLY = 4;

    public function has(int $option):bool {
        return ($this->value & $option) > 0;
    }
}