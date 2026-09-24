<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PiiGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PiiGuardTest extends TestCase
{
    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function samples(): array
    {
        return [
            'mobile 07' => ['Call 0712345678 for details', [PiiGuard::PHONE]],
            'mobile with spaces' => ['reached on 0712 345 678 later', [PiiGuard::PHONE]],
            'international' => ['number +254 712 345 678', [PiiGuard::PHONE]],
            'international no plus' => ['254712345678', [PiiGuard::PHONE]],
            'new 01 prefix' => ['phone 0110-123-456', [PiiGuard::PHONE]],
            'hyphenated' => ['0722-123-456', [PiiGuard::PHONE]],
            'national id 8 digits' => ['holder of 23456789 was present', [PiiGuard::NATIONAL_ID]],
            'national id 7 digits' => ['card 1234567', [PiiGuard::NATIONAL_ID]],
            'labelled id' => ['ID no. 12345678', [PiiGuard::NATIONAL_ID]],
            'labelled id short' => ['ID: 876543', [PiiGuard::NATIONAL_ID]],
            'both' => ['0712345678 and ID 12345678', [PiiGuard::PHONE, PiiGuard::NATIONAL_ID]],
            'brackets' => ['call (0712) 345678', [PiiGuard::PHONE]],
            'international with (0)' => ['+254 (0) 712 345 678', [PiiGuard::PHONE]],
            'slashes' => ['0712/345/678', [PiiGuard::PHONE]],
            'double spaces' => ['0712  345  678', [PiiGuard::PHONE]],
            'grouped id' => ['holder 12 345 678', [PiiGuard::NATIONAL_ID]],
            'labelled spaced id' => ['ID 12 345 678', [PiiGuard::NATIONAL_ID]],
            'full-width digits' => ['call ０７１２３４５６７８', [PiiGuard::PHONE]],
            'zero-width split' => ["call 0712\u{200B}345678", [PiiGuard::PHONE]],
            'amount in shillings' => ['KES 2500000 taken', []],
            'amount ksh' => ['Ksh 1500000 stolen', []],
            'commas' => ['call 0712,345,678', [PiiGuard::PHONE]],
            'underscores' => ['call 0712_345_678', [PiiGuard::PHONE]],
            'asterisks' => ['call 0712*345*678', [PiiGuard::PHONE]],
            'arabic-indic digits' => ['call ٠٧١٢٣٤٥٦٧٨', [PiiGuard::PHONE]],
            'id with commas' => ['ID no 12,345,678', [PiiGuard::NATIONAL_ID]],
            'id with dots' => ['owner 12.345.678', [PiiGuard::NATIONAL_ID]],
            'id hyphenated' => ['owner 1234-5678', [PiiGuard::NATIONAL_ID]],
            'id split in two' => ['owner 1234 5678', [PiiGuard::NATIONAL_ID]],
            'iso date' => ['On 2026-09-15 at 19:30', []],
            'day-first date' => ['On 15/09/2026 near the stage', []],
            'amount with commas' => ['KES 2,500,000 taken', []],
            'clean text' => ['Phone snatched near the bus stage at 7pm', []],
            'time and year' => ['Around 19:30 on 12 March 2026', []],
            'money' => ['KES 15,000 taken', []],
            'short numbers' => ['House 45, gate 3', []],
            'empty' => ['', []],
        ];
    }

    /**
     * TC-UT-01: V6 patterns (docs/02).
     *
     * @param  list<string>  $expected
     */
    #[Test]
    #[DataProvider('samples')]
    public function it_detects_phone_and_id_numbers_and_ignores_ordinary_numbers(string $text, array $expected): void
    {
        $this->assertSame($expected, (new PiiGuard)->findings($text));
    }
}
