<?php

namespace App\Cpf\Scanner\Parser;

use App\Cpf\Scanner\Parser\FileParserInterface;

class CsvParser implements FileParserInterface
{
    public function getText(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Arquivo não encontrado: {$filePath}");
        }

        $textContent = '';
        
        if (($handle = fopen($filePath, "r")) !== false) {
            try {
                while (($data = fgetcsv($handle)) !== false) {
                    $textContent .= implode(' ', array_filter($data, function($value) {
                        return $value !== null && $value !== '';
                    })) . "\n";
                }
            } finally {
                fclose($handle);
            }
        } else {
            throw new \Exception("Não foi possível abrir o arquivo: {$filePath}");
        }

        return $textContent;
    }

    public function supports(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, $this->getSupportedFormats());
    }

    public function getSupportedFormats(): array
    {
        return ['csv', 'txt'];
    }
}
