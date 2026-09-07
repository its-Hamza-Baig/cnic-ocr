<?php

namespace App\DTOs;

class ParsedCnic
{
    public function __construct(
        public readonly ?string $cnic,
        public readonly ?string $name,
        public readonly ?string $fatherHusbandName,
        public readonly ?string $address,
    ) {}
}
