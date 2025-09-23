<?php

namespace CpfScanner\Parsing\Contract;

interface FileParserInterface
{
    public function getText(string $filePath): string;
    public function supports(string $filePath): bool;
    public function getSupportedFormats(): array;
}
