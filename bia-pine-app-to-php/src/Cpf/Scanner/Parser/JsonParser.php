<?php

namespace App\Cpf\Scanner\Parser;

use App\Cpf\Scanner\Parser\FileParserInterface;

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
        // Use um separador forte e único para garantir que números sejam isolados
        $separator = " |SEPARATOR| "; 
        
        if (is_array($data)) {
            foreach ($data as $value) {
                // Garante que a chamada recursiva use o separador
                $text .= $this->extractStringsRecursive($value); 
            }
        } elseif (is_string($data)) {
            $text .= $data . $separator;
        } elseif (is_numeric($data)) {
            // Envolve o número com o separador para garantir que a Regex o isole
            $text .= $separator . (string)$data . $separator; 
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
