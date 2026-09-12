<?php

namespace App\Pricing\Data;

use App\Pricing\Exceptions\InvalidPortfolioSnapshot;

final readonly class Group
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
        if (trim($id) === '') {
            throw new InvalidPortfolioSnapshot('Group IDs cannot be empty.');
        }
    }
}
