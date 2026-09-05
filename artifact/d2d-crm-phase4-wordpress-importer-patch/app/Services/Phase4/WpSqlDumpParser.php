<?php

namespace App\Services\Phase4;

use Generator;
use RuntimeException;

class WpSqlDumpParser
{
    /**
     * Stream associative rows from phpMyAdmin INSERT statements for one table.
     * The parser intentionally ignores every other table in the dump.
     */
    public function rows(string $filePath, string $table): Generator
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('SQL source file is not readable: '.$filePath);
        }

        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            throw new RuntimeException('Unable to open SQL source file.');
        }

        $capturing = false;
        $statement = '';
        $inString = false;
        $escaped = false;
        $tablePattern = '/^\s*INSERT\s+INTO\s+`?'.preg_quote($table, '/').'`?\s*/i';

        try {
            while (($line = fgets($handle)) !== false) {
                if (! $capturing) {
                    if (! preg_match($tablePattern, $line)) {
                        continue;
                    }
                    $capturing = true;
                    $statement = '';
                    $inString = false;
                    $escaped = false;
                }

                $statement .= $line;

                if ($this->containsTerminatingSemicolon($line, $inString, $escaped)) {
                    foreach ($this->parseInsertStatement($statement, $table) as $row) {
                        yield $row;
                    }

                    $capturing = false;
                    $statement = '';
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private function containsTerminatingSemicolon(string $chunk, bool &$inString, bool &$escaped): bool
    {
        $length = strlen($chunk);

        for ($i = 0; $i < $length; $i++) {
            $char = $chunk[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($inString && $char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === "'") {
                $inString = ! $inString;
                continue;
            }

            if (! $inString && $char === ';') {
                return true;
            }
        }

        return false;
    }

    private function parseInsertStatement(string $statement, string $table): Generator
    {
        $pattern = '/INSERT\s+INTO\s+`?'.preg_quote($table, '/').'`?\s*\((.*?)\)\s*VALUES\s*(.*);\s*$/is';
        if (! preg_match($pattern, $statement, $matches)) {
            return;
        }

        $columns = array_map(
            static fn (string $column) => trim(trim($column), "` \t\r\n"),
            explode(',', $matches[1])
        );

        foreach ($this->parseTuples($matches[2]) as $values) {
            if (count($values) !== count($columns)) {
                continue;
            }

            yield array_combine($columns, $values);
        }
    }

    private function parseTuples(string $input): Generator
    {
        $length = strlen($input);
        $i = 0;

        while ($i < $length) {
            $this->skipWhitespaceAndCommas($input, $i, $length);
            if ($i >= $length) {
                break;
            }

            if ($input[$i] !== '(') {
                $i++;
                continue;
            }

            $i++;
            $values = [];

            while ($i < $length) {
                $this->skipWhitespace($input, $i, $length);

                if ($i < $length && $input[$i] === ')') {
                    $i++;
                    break;
                }

                $values[] = $this->parseValue($input, $i, $length);
                $this->skipWhitespace($input, $i, $length);

                if ($i < $length && $input[$i] === ',') {
                    $i++;
                    continue;
                }

                if ($i < $length && $input[$i] === ')') {
                    $i++;
                    break;
                }
            }

            yield $values;
        }
    }

    private function parseValue(string $input, int &$i, int $length): mixed
    {
        if ($i < $length && $input[$i] === "'") {
            $i++;
            $value = '';

            while ($i < $length) {
                $char = $input[$i++];

                if ($char === '\\' && $i < $length) {
                    $next = $input[$i++];
                    $value .= match ($next) {
                        'n' => "\n",
                        'r' => "\r",
                        't' => "\t",
                        '0' => "\0",
                        'Z' => chr(26),
                        default => $next,
                    };
                    continue;
                }

                if ($char === "'") {
                    break;
                }

                $value .= $char;
            }

            return $value;
        }

        $start = $i;
        while ($i < $length && $input[$i] !== ',' && $input[$i] !== ')') {
            $i++;
        }

        $raw = trim(substr($input, $start, $i - $start));
        if (strcasecmp($raw, 'NULL') === 0) {
            return null;
        }
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }

        return $raw;
    }

    private function skipWhitespaceAndCommas(string $input, int &$i, int $length): void
    {
        while ($i < $length && (ctype_space($input[$i]) || $input[$i] === ',')) {
            $i++;
        }
    }

    private function skipWhitespace(string $input, int &$i, int $length): void
    {
        while ($i < $length && ctype_space($input[$i])) {
            $i++;
        }
    }
}
