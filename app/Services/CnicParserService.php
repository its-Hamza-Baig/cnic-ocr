<?php

namespace App\Services;

use App\DTOs\ParsedCnic;

class CnicParserService
{
    /**
     * @var list<string>
     */
    private array $nameLabels = [
        'Holder\'s Name',
        'Holders Name',
        'Full Name',
        'Name',
        'NAME',
        'Nane',
        'نام',
    ];

    /**
     * @var list<string>
     */
    private array $fatherLabels = [
        'Father / Husband Name',
        'Father/Husband Name',
        'Father\'s Name',
        'Father Name',
        'Fathers Name',
        'Father Nane',
        'Husband Name',
        'S/O',
        'D/O',
        'W/O',
        'والد کا نام',
        'والد',
        'شوہر کا نام',
        'شوہر',
    ];

    /**
     * @var list<string>
     */
    private array $addressLabels = [
        'Permanent Address',
        'Present Address',
        'Current Address',
        'Address',
        'موجودہ پتہ',
        'مستقل پتہ',
        'پتہ',
    ];

    /**
     * @var list<string>
     */
    private array $ignoredValues = [
        'pakistan',
        'islamic republic of pakistan',
        'nadra',
        'identity card',
        'national identity card',
        'gender',
        'male',
        'female',
        'country of stay',
        'date of birth',
        'date of issue',
        'date of expiry',
        'identity number',
        'signature',
        'holder signature',
        'holder\'s signature',
    ];

    public function parse(string $frontText, ?string $backText = null): ParsedCnic
    {
        $combined = trim($frontText."\n".($backText ?? ''));
        $mrz = $this->parseMrz($combined);
        $labeledName = $this->extractLabeledValue($combined, $this->nameLabels, skipIfContains: ['father', 'husband', 'والد', 'شوہر'], personName: true);
        $name = $this->chooseHolderName($labeledName, $mrz['name'], $frontText);

        return new ParsedCnic(
            cnic: $mrz['cnic'] ?? $this->extractCnic($combined),
            name: $name,
            fatherHusbandName: $this->extractFatherName($combined, $frontText, $name),
            address: $this->extractAddress($combined, $backText),
        );
    }

    public function extractCnic(string $text): ?string
    {
        $mrz = $this->parseMrz($text);

        if ($mrz['cnic'] !== null) {
            return $mrz['cnic'];
        }

        if (preg_match('/(?:identity\s*number|شناختی\s*نمبر).{0,80}?([0-9OIl]{5}\s*[-—–]\s*[0-9OIl]{7}\s*[-—–]\s*[0-9OIl])/isu', $text, $labeled) === 1) {
            $formatted = $this->formatDigits($labeled[1]);

            if ($formatted !== null) {
                return $formatted;
            }
        }

        $candidates = [];

        if (preg_match_all('/([0-9OIl]{5})\s*[-—–]\s*([0-9OIl]{7})\s*[-—–]\s*([0-9OIl])/', $text, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $formatted = $this->formatDigits($match[1].$match[2].$match[3]);

                if ($formatted !== null) {
                    $candidates[] = $formatted;
                }
            }
        }

        if (preg_match_all('/(?<![0-9OIl])([0-9OIl]{13})(?![0-9OIl])/', $text, $compact) > 0) {
            foreach ($compact[1] as $digits) {
                $formatted = $this->formatDigits($digits);

                if ($formatted !== null) {
                    $candidates[] = $formatted;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        $counts = array_count_values($candidates);
        arsort($counts);

        return array_key_first($counts);
    }

    /**
     * @return array{cnic: ?string, name: ?string}
     */
    public function parseMrz(string $text): array
    {
        $result = ['cnic' => null, 'name' => null];
        $lines = preg_split("/\n/", str_replace(["\r\n", "\r"], "\n", $text)) ?: [];

        foreach ($lines as $line) {
            $clean = strtoupper(preg_replace('/\s+/', '', $line) ?? '');

            if (preg_match('/[I1]<PAK[A-Z0-9<]*?([0-9O]{13})<*$/i', $clean, $document) === 1) {
                $result['cnic'] = $this->formatDigits($document[1]);
            }

            if (str_starts_with($clean, 'I<') || str_starts_with($clean, '1<') || preg_match('/^\d/', $clean) === 1) {
                continue;
            }

            if (preg_match('/^([A-Z]{2,})<<([A-Z]{2,}(?:<[A-Z]+)*)/', $clean, $name) === 1) {
                $surname = $name[1];
                $given = trim(str_replace('<', ' ', $name[2]));
                $result['name'] = mb_convert_case(mb_strtolower(trim($given.' '.$surname)), MB_CASE_TITLE, 'UTF-8');
            }
        }

        return $result;
    }

    private function chooseHolderName(?string $labeled, ?string $mrzName, string $frontText): ?string
    {
        $latin = $this->preferredUnlabeledHolderName($this->extractLatinPersonNames($frontText));
        $best = null;
        $bestScore = 0;

        foreach ([
            [$labeled, 0],
            [$mrzName, 30],
            [$latin, 15],
        ] as [$candidate, $bonus]) {
            if (! $this->isUsablePersonName($candidate)) {
                continue;
            }

            $score = $this->scoreCandidate($candidate, true) + $bonus;

            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function extractFatherName(string $combined, string $frontText, ?string $holderName): ?string
    {
        $labeled = $this->extractLabeledValue($combined, $this->fatherLabels, personName: true);

        if ($this->isUsablePersonName($labeled) && ! $this->samePerson($labeled, $holderName)) {
            return $labeled;
        }

        $latinNames = array_values(array_filter(
            $this->extractLatinPersonNames($frontText),
            fn (string $candidate) => ! $this->samePerson($candidate, $holderName)
        ));

        foreach ($latinNames as $candidate) {
            if (preg_match('/\p{Ll}/u', $candidate) === 1) {
                return $candidate;
            }
        }

        if ($holderName !== null && $latinNames !== []) {
            return $latinNames[0];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractLatinPersonNames(string $text): array
    {
        if (preg_match_all('/\b([\p{Lu}][\p{L}\'\-]{2,}(?:[^\S\n]+[\p{Lu}][\p{L}\'\-]{2,}){1,2})\b/u', $text, $matches) === 0) {
            return [];
        }

        $names = [];
        $seen = [];

        foreach ($matches[1] as $candidate) {
            if (! $this->isUsablePersonName($candidate)) {
                continue;
            }

            $normalized = mb_strtolower($candidate);

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $names[] = $candidate;
        }

        return $names;
    }

    /**
     * @param  list<string>  $names
     */
    private function preferredUnlabeledHolderName(array $names): ?string
    {
        foreach ($names as $name) {
            if (preg_match('/\p{Ll}/u', $name) === 1) {
                return $name;
            }
        }

        return count($names) >= 2 ? $names[0] : null;
    }

    private function isUsablePersonName(?string $value): bool
    {
        return $value !== null && $value !== '' && $this->scoreCandidate($value, true) > 0;
    }

    private function samePerson(?string $left, ?string $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        return mb_strtolower(preg_replace('/\s+/', ' ', trim($left)) ?? '') === mb_strtolower(preg_replace('/\s+/', ' ', trim($right)) ?? '');
    }

    private function extractAddress(string $combined, ?string $backText): ?string
    {
        $source = $backText ?: $combined;
        $block = $this->extractAddressBlock($source);

        if ($block !== null) {
            return $block;
        }

        $labeled = $this->extractLabeledValue($combined, $this->addressLabels);

        if ($labeled !== null) {
            return $labeled;
        }

        return $this->extractUnlabeledAddress($source);
    }

    private function extractAddressBlock(string $text): ?string
    {
        $lines = preg_split("/\n/", str_replace(["\r\n", "\r"], "\n", $text)) ?: [];
        $blocks = [];
        $current = null;
        $currentKind = null;

        foreach ($lines as $line) {
            $trimmed = trim(preg_replace('/\s+/', ' ', $line) ?? '');
            $started = $this->addressBlockStart($trimmed);

            if ($started !== null) {
                if ($current !== null) {
                    $blocks[] = ['kind' => $currentKind, 'text' => $current];
                }

                $currentKind = $started['kind'];
                $current = $started['remainder'];

                continue;
            }

            if ($current === null) {
                continue;
            }

            if ($this->looksLikeAddressStop($trimmed)) {
                $blocks[] = ['kind' => $currentKind, 'text' => $current];
                $current = null;
                $currentKind = null;

                continue;
            }

            if ($trimmed !== '') {
                $current = trim($current.' '.$trimmed);
            }
        }

        if ($current !== null) {
            $blocks[] = ['kind' => $currentKind, 'text' => $current];
        }

        $best = null;
        $bestLetters = 0;
        $present = null;

        foreach ($blocks as $block) {
            $textBlock = trim(preg_replace('/\s+/', ' ', $block['text']) ?? '', " .,:;|~\"'`=-");
            $letters = preg_match_all('/\p{L}/u', $textBlock);

            if ($letters < 12 || preg_match('/\b(registrar|holder is entitled|identity number)\b/i', $textBlock) === 1) {
                continue;
            }

            if ($block['kind'] === 'present') {
                $present = $textBlock;
            }

            if ($letters > $bestLetters) {
                $best = $textBlock;
                $bestLetters = $letters;
            }
        }

        $best = $present ?? $best;

        if ($best === null) {
            return null;
        }

        return mb_substr($best, 0, (int) config('cnic.address_max_length', 2000));
    }

    /**
     * @return array{kind: string, remainder: string}|null
     */
    private function addressBlockStart(string $line): ?array
    {
        if (preg_match('/^(present|permanent|current)\s+address\s*[:\-]?\s*(.*)$/iu', $line, $match) === 1) {
            return ['kind' => strtolower($match[1]) === 'permanent' ? 'permanent' : 'present', 'remainder' => trim($match[2])];
        }

        if (preg_match('/^موجودہ\s*پتہ\s*[:\-]?\s*(.*)$/u', $line, $match) === 1) {
            return ['kind' => 'present', 'remainder' => trim($match[1])];
        }

        if (preg_match('/^مستقل\s*پتہ\s*[:\-]?\s*(.*)$/u', $line, $match) === 1) {
            return ['kind' => 'permanent', 'remainder' => trim($match[1])];
        }

        if (preg_match('/^(address|پتہ)\s*[:\-]?\s*(.*)$/iu', $line, $match) === 1) {
            return ['kind' => 'generic', 'remainder' => trim($match[2])];
        }

        return null;
    }

    private function looksLikeAddressStop(string $line): bool
    {
        return $this->addressBlockStart($line) !== null
            || $this->looksLikeFieldLabel($line)
            || preg_match('/the holder is entitled|registrar general|identity number|شناختی\s*نمبر/i', $line) === 1
            || preg_match('/^[I1]<PAK/i', $line) === 1
            || preg_match('/[A-Z0-9<]{20,}/', $line) === 1
            || preg_match('/^[0-9OIl]{5}\s*[-—–]\s*[0-9OIl]{7}\s*[-—–]\s*[0-9OIl]/', $line) === 1;
    }

    private function extractUnlabeledAddress(string $text): ?string
    {
        $lines = preg_split("/\n/", str_replace(["\r\n", "\r"], "\n", $text)) ?: [];
        $candidates = [];

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');

            if ($line === '' || in_array($line, $this->addressLabels, true) || $this->looksLikeFieldLabel($line)) {
                continue;
            }

            $arabic = preg_match_all('/\p{Arabic}/u', $line);
            $latin = preg_match_all('/[A-Za-z]/', $line);
            $looksLikeAddress = false;

            foreach (['پتہ', 'ڈاک', 'تحصیل', 'ضلع', 'گلی', 'مکان', 'خانہ', 'پور', 'نمبر', 'district', 'tehsil', 'street', 'house', 'pakistan'] as $hint) {
                if (mb_stripos($line, $hint) !== false) {
                    $looksLikeAddress = true;
                    break;
                }
            }

            if (! $looksLikeAddress) {
                continue;
            }

            $script = $arabic >= 8 ? $arabic : $latin;

            if ($script < 8) {
                continue;
            }

            $candidates[] = ['script' => $script, 'line' => $line];
        }

        usort($candidates, fn (array $a, array $b) => $b['script'] <=> $a['script']);

        $picked = [];
        $total = 0;

        foreach (array_slice($candidates, 0, 3) as $candidate) {
            if (in_array($candidate['line'], $picked, true)) {
                continue;
            }

            $picked[] = $candidate['line'];
            $total += $candidate['script'];
        }

        if ($picked === [] || $total < 12) {
            return null;
        }

        return mb_substr(implode(' ', $picked), 0, (int) config('cnic.address_max_length', 2000));
    }

    /**
     * @param  list<string>  $labels
     * @param  list<string>  $skipIfContains
     */
    private function extractLabeledValue(string $text, array $labels, array $skipIfContains = [], bool $personName = false): ?string
    {
        usort($labels, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = preg_split("/\n/", $normalized) ?: [];
        $best = null;
        $bestScore = 0;

        foreach ($labels as $label) {
            $quoted = preg_quote($label, '/');

            if (preg_match('/(?:^|\n)\s*'.$quoted.'\s*[:\-]\s*(.+)/ui', $normalized, $sameLine) === 1) {
                $this->considerCandidate($sameLine[1], $labels, $best, $bestScore, $personName);
            }

            if (preg_match('/(?:^|\n)\s*'.$quoted.'\s+(.+)/ui', $normalized, $inline) === 1) {
                $this->considerCandidate($inline[1], $labels, $best, $bestScore, $personName);
            }

            foreach ($lines as $index => $line) {
                if (! $this->lineHasLabel($line, $label, $skipIfContains)) {
                    continue;
                }

                $inlineRemainder = preg_replace('/^.*?'.$quoted.'\s*[:\-]?\s*/ui', '', $line) ?? '';
                $this->considerCandidate($inlineRemainder, $labels, $best, $bestScore, $personName);

                for ($i = $index + 1; $i < min($index + 6, count($lines)); $i++) {
                    if ($this->looksLikeFieldLabel($lines[$i])) {
                        break;
                    }

                    $this->considerCandidate($lines[$i], $labels, $best, $bestScore, $personName);
                }
            }
        }

        return $best;
    }

    /**
     * @param  list<string>  $labels
     */
    private function considerCandidate(string $raw, array $labels, ?string &$best, int &$bestScore, bool $personName = false): void
    {
        $candidate = $this->cleanValue($raw, $labels, $personName);

        if ($candidate === null) {
            return;
        }

        $score = $this->scoreCandidate($candidate, $personName);

        if ($score > $bestScore) {
            $best = $candidate;
            $bestScore = $score;
        }
    }

    private function scoreCandidate(string $value, bool $personName = false): int
    {
        if ($personName) {
            if (mb_strlen($value) > 60) {
                return 0;
            }

            if (preg_match('/\b(name|father|husband|pakistan|identity|national|islamic|republic|date|gender|country|address|card|saudi|arabia|district|street|house|tehsil)\b/i', $value) === 1) {
                return 0;
            }

            if (preg_match('/پاکستان|جمہوریہ|شناختی کارڈ|پتہ/u', $value) === 1) {
                return 0;
            }
        } elseif (preg_match('/\b(name|father|husband|identity|national|islamic|republic|date|gender|country|card)\b/i', $value) === 1) {
            return 0;
        }

        $words = preg_split('/\s+/', $value) ?: [];
        $strongWords = 0;
        $weakWords = 0;

        foreach ($words as $word) {
            $word = trim($word, '.,;:=-');

            if ($word === '') {
                continue;
            }

            if ($personName && mb_strlen($word) < 3) {
                return 0;
            }

            if (preg_match('/^[\p{Lu}\p{Lo}][\p{L}\'\-]{1,}$/u', $word) === 1) {
                $strongWords++;
            } else {
                $weakWords++;
            }
        }

        if ($personName && ($strongWords < 2 || preg_match_all('/\p{L}/u', $value) < 6)) {
            return 0;
        }

        if ($strongWords === 0 || $weakWords > $strongWords) {
            return 0;
        }

        $score = ($strongWords * 30) - ($weakWords * 20);

        if ($strongWords >= 2 && $strongWords <= 5 && $weakWords === 0) {
            $score += 40;
        }

        if ($personName && preg_match_all('/[A-Za-z]/', $value) >= 6) {
            $score += 20;
        }

        return max(0, $score);
    }

    /**
     * @param  list<string>  $skipIfContains
     */
    private function lineHasLabel(string $line, string $label, array $skipIfContains): bool
    {
        if (! $this->lineContainsLabel($line, $label)) {
            return false;
        }

        $lower = mb_strtolower($line);

        foreach ($skipIfContains as $needle) {
            if (str_contains($lower, mb_strtolower($needle))) {
                return false;
            }
        }

        return true;
    }

    private function lineContainsLabel(string $line, string $label): bool
    {
        if (preg_match('/\p{Arabic}/u', $label) === 1) {
            return mb_strpos($line, $label) !== false;
        }

        return preg_match('/\b'.preg_quote($label, '/').'\b/ui', $line) === 1;
    }

    private function looksLikeFieldLabel(string $line): bool
    {
        return preg_match('/\b(father\'?s? name|husband name|identity number|date of birth|date of issue|date of expiry|gender|country of stay|present address|permanent address|address)\b/i', $line) === 1
            || preg_match('/(والد کا نام|شوہر کا نام|موجودہ پتہ|مستقل پتہ|شناختی نمبر|تاریخ پیدائش|جنس)/u', $line) === 1;
    }

    /**
     * @param  list<string>  $labels
     */
    private function cleanValue(string $value, array $labels, bool $personName = false): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        $value = trim($value, " .,:;|~\"'`=-");

        if ($value === '') {
            return null;
        }

        if (preg_match('/^[a-z]{1,2}\s+([\p{L}].+)$/u', $value, $withoutNoise) === 1) {
            $value = $withoutNoise[1];
        }

        if ($personName && preg_match('/[\p{Lu}\p{Lo}][\p{L}\'\-]{2,}(?:\s+[\p{Lu}\p{Lo}][\p{L}\'\-]{2,})+/u', $value, $nameMatch) === 1) {
            $value = $nameMatch[0];
        } elseif ($personName) {
            return null;
        }

        foreach ($labels as $label) {
            if (strcasecmp($value, $label) === 0) {
                return null;
            }
        }

        if (preg_match('/\b(islamic republic|national identity|identity card|country of stay|date of (birth|issue|expiry)|gender)\b/i', $value) === 1) {
            return null;
        }

        if (in_array(mb_strtolower($value), $this->ignoredValues, true)) {
            return null;
        }

        if (preg_match('/^\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4}$/', $value) === 1) {
            return null;
        }

        if (preg_match('/^[0-9OIl]{5}\s*[-—–]?\s*[0-9OIl]{7}\s*[-—–]?\s*[0-9OIl]/', $value) === 1) {
            return null;
        }

        if (preg_match_all('/\p{L}/u', $value) < 3) {
            return null;
        }

        return mb_substr($value, 0, (int) config('cnic.address_max_length', 2000));
    }

    private function formatDigits(string $value): ?string
    {
        $digits = $this->ocrDigits($value);

        if (strlen($digits) < 13) {
            return null;
        }

        $digits = substr($digits, 0, 13);

        return substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12, 1);
    }

    private function ocrDigits(string $value): string
    {
        $mapped = strtr($value, [
            'O' => '0',
            'o' => '0',
            'I' => '1',
            'l' => '1',
        ]);

        return preg_replace('/\D+/', '', $mapped) ?? '';
    }
}
