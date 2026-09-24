<?php

declare(strict_types=1);

namespace App\Services;

use Normalizer;

/**
 * V6: detects Kenyan phone numbers and national ID numbers in free text so they are never stored (D-08, NFR-02).
 * Text is first normalised (full-width digits to ASCII, invisible characters removed). Patterns (docs/02 V6, D-36):
 *  - phone: +254, 254, (0) or 0, then 7 or 1, then 8 digits; spaces, dots, hyphens, slashes or brackets allowed between
 *    digits
 *  - national ID: a stand-alone run of 7 or 8 digits, the grouped form "12 345 678", or 6 to 9 digits after "ID",
 *    "ID no" or "ID number"; a run preceded by a currency word (KES, KSh, Sh) is treated as an amount
 */
final class PiiGuard
{
    public const PHONE = 'phone number';

    public const NATIONAL_ID = 'national ID number';

    private const SEP = '[\s.\-\/()]*';

    private const STANDALONE_ID_PATTERN = '/(?<![\d.,:\/\-])(?<!KES\s)(?<!KES)(?<!KSh\s)(?<!KSh)(?<!Ksh\s)(?<!Sh\s)(?:\d{7,8}|\d{2}\s\d{3}\s\d{3})(?![\d.,:\/\-]?\d)/';

    private const LABELLED_ID_PATTERN = '/\bID(?:\s*(?:no\.?|number|#))?\s*[:.\-]?\s*\d(?:\s?\d){5,8}\b/i';

    /**
     * @return list<string> kinds of personal identifier found, empty when clean
     */
    public function findings(?string $text): array
    {
        if ($text === null || $text === '') {
            return [];
        }

        $text = self::normalise($text);
        $found = [];
        $phone = '/(?<![\d+])(?:(?:\+?\s*254|\(\+?254\))'.self::SEP.'(?:\(?0\)?'.self::SEP.')?|\(?0\)?'.self::SEP.')[17](?:'.self::SEP.'\d){8}(?!\d)/';

        if (preg_match($phone, $text) === 1) {
            $found[] = self::PHONE;
            $text = (string) preg_replace($phone, ' ', $text);
        }

        if (preg_match(self::LABELLED_ID_PATTERN, $text) === 1 || preg_match(self::STANDALONE_ID_PATTERN, $text) === 1) {
            $found[] = self::NATIONAL_ID;
        }

        return $found;
    }

    private static function normalise(string $text): string
    {
        // Text that is not valid UTF-8 (for example a Windows-1252 file) would make every Unicode pattern fail silently.
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        $normalised = class_exists(Normalizer::class) ? Normalizer::normalize($text, Normalizer::FORM_KC) : $text;
        $text = is_string($normalised) ? $normalised : $text;

        // Zero-width and other invisible format characters could split a number to slip past the patterns.
        return (string) preg_replace('/[\p{Cf}]/u', '', $text);
    }
}
