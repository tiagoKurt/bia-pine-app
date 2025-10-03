<?php

namespace App\Cpf\Scanner\Parser;

use App\Cpf\Scanner\Parser\FileParserInterface;
use App\Cpf\Scanner\Parser\CsvParser;
use App\Cpf\Scanner\Parser\JsonParser;
use App\Cpf\Scanner\Parser\ExcelParser;
use App\Cpf\Scanner\Parser\PdfParser;
use App\Cpf\Scanner\Parser\TextParser;
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
