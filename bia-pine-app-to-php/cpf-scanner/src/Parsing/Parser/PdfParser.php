<?php

namespace CpfScanner\Parsing\Parser;

use CpfScanner\Parsing\Contract\FileParserInterface;
use Smalot\PdfParser\Parser;
use Exception as PdfParserException;

class PdfParser implements FileParserInterface
{
    public function getText(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Arquivo não encontrado: {$filePath}");
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            
            if (!$pdf) {
                throw new \Exception("Não foi possível analisar o arquivo PDF");
            }
            
            return $pdf->getText();
            
        } catch (PdfParserException $e) {
            throw new \Exception("Erro ao processar arquivo PDF '{$filePath}': " . $e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception("Erro inesperado ao processar '{$filePath}': " . $e->getMessage());
        }
    }

    public function supports(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, $this->getSupportedFormats());
    }

    public function getSupportedFormats(): array
    {
        return ['pdf'];
    }
}
