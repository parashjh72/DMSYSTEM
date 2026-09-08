<?php

namespace App\Services\Import;

use Generator;
use InvalidArgumentException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Thin streaming wrapper over OpenSpout. Every method opens the file, streams the
 * rows it needs, and closes — memory stays flat regardless of file size (§2).
 */
class SpreadsheetReader
{
    public function __construct(
        private readonly string $absolutePath,
        private readonly string $type, // csv | xlsx
    ) {
        if (! in_array($type, ['csv', 'xlsx'], true)) {
            throw new InvalidArgumentException("Unsupported file type [{$type}].");
        }
    }

    /** @return array<int, string> first row, trimmed */
    public function headers(): array
    {
        foreach ($this->rows() as $row) {
            return array_map(static fn ($v) => trim((string) $v), $row);
        }

        return [];
    }

    /** Number of data rows (excludes the header). Streams the whole file once. */
    public function countDataRows(): int
    {
        $count = -1; // header
        foreach ($this->rows() as $_) {
            $count++;
        }

        return max(0, $count);
    }

    /**
     * Yield [rowNumber => cells] for data rows in [$startRow, $endRow] inclusive.
     * rowNumber is 1-based over data rows (header is row 0 and never yielded).
     *
     * @return Generator<int, array<int, mixed>>
     */
    public function dataRows(int $startRow = 1, ?int $endRow = null): Generator
    {
        $isHeader = true;
        $dataIndex = 0;
        foreach ($this->rows() as $row) {
            if ($isHeader) {          // first row is the header — never a data row
                $isHeader = false;

                continue;
            }
            $dataIndex++;             // 1-based over data rows
            if ($dataIndex < $startRow) {
                continue;
            }
            if ($endRow !== null && $dataIndex > $endRow) {
                break;
            }
            yield $dataIndex => $row;
        }
    }

    /** @return Generator<int, array<int, mixed>> raw rows incl. header */
    private function rows(): Generator
    {
        $reader = $this->makeReader();
        $reader->open($this->absolutePath);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    yield $row->toArray();
                }

                break; // first sheet only
            }
        } finally {
            $reader->close();
        }
    }

    private function makeReader(): ReaderInterface
    {
        if ($this->type === 'csv') {
            return new CsvReader(new CsvOptions(
                SHOULD_PRESERVE_EMPTY_ROWS: false,
            ));
        }

        return new XlsxReader(new XlsxOptions(
            SHOULD_FORMAT_DATES: false, // keep DateTime objects; DateNormalizer handles them
            SHOULD_PRESERVE_EMPTY_ROWS: false,
            tempFolder: $this->tempFolder(),
        ));
    }

    /** OpenSpout unzips XLSX here; create it, fall back to the system temp dir. */
    private function tempFolder(): string
    {
        $dir = storage_path('app/openspout');

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return is_writable($dir) ? $dir : sys_get_temp_dir();
    }
}
