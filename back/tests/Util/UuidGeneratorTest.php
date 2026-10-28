<?php

namespace App\Tests\Util;

use App\Util\UuidGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UuidGeneratorTest extends TestCase
{
    #[DataProvider('uuidSamples')]
    public function testGeneratedUuidUsesVersionFourAndValidVariant(int $sample): void
    {
        $uuid = UuidGenerator::generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid,
            'Sample '.$sample.' must follow the UUID v4 layout.'
        );
    }

    public static function uuidSamples(): iterable
    {
        yield 'first value' => [1];
        yield 'second value' => [2];
        yield 'third value' => [3];
    }
}
