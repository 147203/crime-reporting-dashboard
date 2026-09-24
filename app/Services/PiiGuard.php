<?php

declare(strict_types=1);

namespace App\Services;

use Normalizer;

/**
 * V6: detects Kenyan phone numbers and national ID numbers in free text so they are never stored (D-08, NFR-02).
 *
 * Method (docs/02 V6, D-36):
 *  1. Normalise: invalid UTF-8 read as Windows-1252, compatibility forms folded (full-width digits), digits of any
 *     script mapped to ASCII, invisible format characters removed.
 *  2. Set aside what is clearly not an identifier: calendar dates (2026-09-15, 15/09/2026) and amounts written after a
 *     currency word (KES, KSh, Ksh, Sh).
 *  3. Read each group of digits joined by separators (space, dot, comma, hyphen, slash, underscore, asterisk, brackets)
 *     as one number, and test the digits alone: a phone is 254 (optionally followed by the trunk 0) or 0, then 7 or 1, then 8 digits (a leading + is
 *     allowed); a national ID is 7 or 8 digits, or 6 to 9 digits right after "ID", "ID no" or "ID number".
 */
final class PiiGuard
{
    public const PHONE = 'phone number';

    public const NATIONAL_ID = 'national ID number';

    private const GROUP = '/(?<![\p{L}\d])(\+?\d(?:[\s.,\-\/_*()]{0,2}\d)*)(?![\p{L}\d])/u';

    private const DATE = '/(?<!\d)(?:\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2}|\d{1,2}[-\/.]\d{1,2}[-\/.]\d{4})(?!\d)/';

    private const AMOUNT = '/\b(?:KES|KSh|Ksh|Sh|Shs)\.?\s*\d[\d,.\s]*/i';

    private const LABEL = '/\bID(?:\s*(?:no\.?|number|#))?\s*[:.\-]?\s*$/i';

    /**
     * @return list<string> kinds of personal identifier found, empty when clean
     */
    public function findings(?string $text): array
    {
        if ($text === null || $text === '') {
            return [];
        }

        $text = (string) preg_replace([self::DATE, self::AMOUNT], ' ', self::normalise($text));
        $found = [];

        if (preg_match_all(self::GROUP, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        foreach ($matches[1] as [$group, $offset]) {
            $digits = (string) preg_replace('/\D/', '', $group);
            $labelled = preg_match(self::LABEL, substr($text, max(0, $offset - 20), min(20, $offset))) === 1;

            if (preg_match('/^(?:2540?|0)[17]\d{8}$/', $digits) === 1) {
                $found[self::PHONE] = true;
            } elseif (preg_match('/^\d{7,8}$/', $digits) === 1 || ($labelled && preg_match('/^\d{6,9}$/', $digits) === 1)) {
                $found[self::NATIONAL_ID] = true;
            }
        }

        return array_values(array_filter([self::PHONE, self::NATIONAL_ID], fn (string $kind): bool => isset($found[$kind])));
    }

    private static function normalise(string $text): string
    {
        // Text that is not valid UTF-8 (for example a Windows-1252 file) would make every Unicode pattern fail silently.
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        $normalised = class_exists(Normalizer::class) ? Normalizer::normalize($text, Normalizer::FORM_KC) : $text;
        $text = is_string($normalised) ? $normalised : $text;

        // Digits of any script (Arabic-Indic and others) become ASCII, so the patterns see them.
        $text = (string) preg_replace_callback('/\p{Nd}/u', fn (array $m): string => (string) \IntlChar::charDigitValue($m[0]), $text);

        // Zero-width and other invisible format characters could split a number to slip past the patterns.
        return (string) preg_replace('/\p{Cf}/u', '', $text);
    }
}
