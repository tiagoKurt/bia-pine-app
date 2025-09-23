<?php

namespace CpfScanner\Parsing\Factory;

use CpfScanner\Parsing\Contract\FileParserInterface;
use CpfScanner\Parsing\Parser\CsvParser;
use CpfScanner\Parsing\Parser\JsonParser;
use CpfScanner\Parsing\Parser\ExcelParser;
use CpfScanner\Parsing\Parser\PdfParser;
use CpfScanner\Parsing\Parser\TextParser;
use InvalidArgumentException;

class FileParserFactory
{
    public static function createParser(string $format): FileParserInterface
    {
        $format = strtolower(trim($format));

        return match ($format) {
            'csv', 'txt' => new CsvParser(),
            'json' => new JsonParser(),
            'xls', 'xlsx', 'ods' => new ExcelParser(),
            'pdf' => new PdfParser(),
            default => new TextParser(),
        };
    }

    public static function createParserFromFile(string $filePath): FileParserInterface
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return self::createParser($extension);
    }

    public static function getSupportedFormats(): array
    {
        return [
            'csv', 'txt', 'json', 'xls', 'xlsx', 'ods', 'pdf'
        ];
    }

    public static function isFormatSupported(string $format): bool
    {
        return in_array(strtolower($format), self::getSupportedFormats());
    }
}
