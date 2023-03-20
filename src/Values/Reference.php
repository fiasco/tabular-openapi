<?php

namespace Fiasco\TabularOpenapi\Values;

class Reference {
    public readonly string $uuid;
    public function __construct(
        public readonly int $index, 
        public readonly string $ref,
        public readonly mixed $value,
        public readonly string $name,
        public readonly ?string $key = null
    )
    {
        $this->uuid = self::generateUuid();
    }

    /**
     * Generate a UUIDv4 string.
     */
    public static function generateUuid():string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}