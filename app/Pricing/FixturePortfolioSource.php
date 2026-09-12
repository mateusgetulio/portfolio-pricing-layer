<?php

namespace App\Pricing;

use App\Pricing\Data\PortfolioSnapshot;
use App\Pricing\Exceptions\FixtureNotFound;
use App\Pricing\Exceptions\InvalidPortfolioSnapshot;
use JsonException;

final readonly class FixturePortfolioSource
{
    public function __construct(private string $directory) {}

    public function load(string $name): PortfolioSnapshot
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.$name.'.json';

        if (preg_match('/^[a-z0-9-]+$/', $name) !== 1 || ! is_file($path)) {
            throw new FixtureNotFound("No fixture named [{$name}].");
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidPortfolioSnapshot("Fixture [{$name}] is not valid JSON.");
        }

        if (! is_array($data)) {
            throw new InvalidPortfolioSnapshot("Fixture [{$name}] must contain a JSON object.");
        }

        return PortfolioSnapshot::fromArray($data);
    }
}
