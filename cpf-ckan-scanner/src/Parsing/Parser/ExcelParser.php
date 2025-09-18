<?php

namespace App\Parsing\Parser;

use App\Parsing\Contract\FileParserInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

class ExcelParser implements FileParserInterface
{
    public function getText(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Arquivo não encontrado: {$filePath}");
        }

        $textContent = '';
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            
            foreach ($spreadsheet->getAllSheets() as $worksheet) {
                $rows = $worksheet->toArray();
                
                foreach ($rows as $row) {
                    $filteredRow = array_filter($row, function($value) {
                        return $value !== null && $value !== '';
                    });
                    
                    if (!empty($filteredRow)) {
                        $textContent .= implode(' ', $filteredRow) . "\n";
                    }
                }
            }
            
        } catch (SpreadsheetException $e) {
            throw new \Exception("Erro ao processar arquivo Excel '{$filePath}': " . $e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception("Erro inesperado ao processar '{$filePath}': " . $e->getMessage());
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
        return ['xls', 'xlsx', 'ods'];
    }
}
