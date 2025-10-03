<?php

namespace App\Cpf\Scanner\Parser;

interface FileParserInterface
{
    public function getText(string $filePath): string;
    public function supports(string $filePath): bool;
    public function getSupportedFormats(): array;
}
