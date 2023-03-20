<?php

namespace Fiasco\TabularOpenapi\Columns;

use cebe\openapi\spec\Schema as SpecSchema;
use Fiasco\TabularOpenapi\Values\Value;
use Generator;

class PaddedColumn extends Column {
    protected array $values;

    public function __construct(
        string $name, 
        SpecSchema $schema, 
        string $tableName,
        public string|bool|int|null $pad = null
    )
    {
        parent::__construct(name: $name, schema: $schema, tableName: $tableName);
    }

    public function get(int $index):Generator {
        if (!array_key_exists($index, $this->values)) {
            yield new Value($this->pad);
            return;
        }
        yield new Value($this->values[$index]);
    }
}