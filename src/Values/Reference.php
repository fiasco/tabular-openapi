<?php

namespace Fiasco\TabularOpenapi\Values;

class Reference {
    public function __construct(
        public readonly int $index, 
        public readonly string $ref, 
        public readonly mixed $value,
        public readonly string $name)
    {
        
    }
}