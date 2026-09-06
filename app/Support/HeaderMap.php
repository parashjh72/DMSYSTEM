<?php

namespace App\Support;

/**
 * Resolves a spreadsheet's header row to canonical field names by header *name*,
 * not position — uploaded files are not guaranteed to keep column order (§17).
 */
class HeaderMap
{
    /**
     * @param  array<int, string>  $headers  raw header cells, in file order
     * @return array{map: array<string,int>, unmatched: array<int,string>, missing: array<int,string>}
     *                                                                                                 map: canonical field => 0-based column index
     */
    public static function resolve(array $headers): array
    {
        $aliases = config('import.header_aliases');
        $required = config('import.required_fields', []);

        $normalizedHeaders = [];
        foreach ($headers as $index => $raw) {
            $normalizedHeaders[$index] = self::normalize((string) $raw);
        }

        $map = [];
        $matchedColumns = [];

        foreach ($aliases as $field => $spellings) {
            foreach ($spellings as $spelling) {
                $target = self::normalize($spelling);
                $hit = array_search($target, $normalizedHeaders, true);
                if ($hit !== false && ! in_array($hit, $matchedColumns, true)) {
                    $map[$field] = $hit;
                    $matchedColumns[] = $hit;

                    continue 2;
                }
            }
        }

        $unmatched = [];
        foreach ($headers as $index => $raw) {
            if (! in_array($index, $matchedColumns, true) && trim((string) $raw) !== '') {
                $unmatched[$index] = (string) $raw;
            }
        }

        $missing = array_values(array_filter(
            $required,
            fn (string $field) => ! array_key_exists($field, $map),
        ));

        return ['map' => $map, 'unmatched' => $unmatched, 'missing' => $missing];
    }

    public static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
