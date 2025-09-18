<?php

namespace App\Parsing\Parser;

use App\Parsing\Contract\FileParserInterface;

class JsonParser implements FileParserInterface
{
    public function getText(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Arquivo não encontrado: {$filePath}");
        }

        $jsonContent = file_get_contents($filePath);
        if ($jsonContent === false) {
            throw new \Exception("Não foi possível ler o arquivo: {$filePath}");
        }

        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON inválido: " . json_last_error_msg());
        }

        return $this->extractStringsRecursive($data);
    }

    private function extractStringsRecursive(mixed $data): string
    {
        $text = '';
        
        if (is_array($data)) {
            foreach ($data as $value) {
                $text .= $this->extractStringsRecursive($value) . ' ';
            }
        } elseif (is_string($data)) {
            $text .= $data . ' ';
        } elseif (is_numeric($data)) {
            $text .= (string)$data . ' ';
        }

        return $text;
    }

    public function supports(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, $this->getSupportedFormats());
    }

    public function getSupportedFormats(): array
    {
        return ['json'];
    }
}
