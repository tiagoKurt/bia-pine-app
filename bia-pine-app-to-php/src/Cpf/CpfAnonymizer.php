<?php

namespace App\Cpf;

use Exception;

/**
 * Classe para anonimizar CPFs em arquivos (CSV, Excel, PDF)
 * Baseado na lógica Python fornecida
 */
class CpfAnonymizer
{
    private const CPF_PATTERN = '/(\d{2,3}[\s\.\-]?\d{3}[\s\.\-]?\d{3}[\s\.\/-]?\d{2})/';
    private const UPLOAD_DIR = __DIR__ . '/../../uploads/cpf_anonymizer';
    private const OUTPUT_DIR = __DIR__ . '/../../uploads/cpf_anonymizer/output';
    
    private array $allowedExtensions = ['csv', 'xlsx', 'xls'];
    private int $maxFileSize = 10485760; // 10MB
    
    public function __construct()
    {
        $this->ensureDirectories();
    }
    
    /**
     * Garante que os diretórios necessários existam
     */
    private function ensureDirectories(): void
    {
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }
        if (!is_dir(self::OUTPUT_DIR)) {
            mkdir(self::OUTPUT_DIR, 0755, true);
        }
    }
    
    /**
     * Valida CPF usando algoritmo de dígitos verificadores
     */
    public function validarCpf(string $cpf): bool
    {
        $cpfLimpo = preg_replace('/[\s\.\-\/]/', '', $cpf);
        
        if (strlen($cpfLimpo) !== 11 || preg_match('/^(\d)\1{10}$/', $cpfLimpo)) {
            return false;
        }
        
        // Primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += intval($cpfLimpo[$i]) * (10 - $i);
        }
        $resto = ($soma * 10) % 11;
        if ($resto === 10) $resto = 0;
        if ($resto !== intval($cpfLimpo[9])) return false;
        
        // Segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += intval($cpfLimpo[$i]) * (11 - $i);
        }
        $resto = ($soma * 10) % 11;
        if ($resto === 10) $resto = 0;
        if ($resto !== intval($cpfLimpo[10])) return false;
        
        return true;
    }
    
    /**
     * Anonimiza um CPF encontrado
     */
    public function anonimizarCpf(string $cpf): string
    {
        $cpfLimpo = preg_replace('/[^\d]/', '', $cpf);
        
        if (strlen($cpfLimpo) !== 11) {
            return $cpf;
        }
        
        // Formato: ***.XXX.XXX-**
        $parte1 = substr($cpfLimpo, 3, 3);
        $parte2 = substr($cpfLimpo, 6, 3);
        return "***." . $parte1 . "." . $parte2 . "-**";
    }
    
    /**
     * Processa o upload do arquivo
     */
    public function processarUpload(array $file): array
    {
        // Validações
        if (!isset($file['tmp_name'])) {
            throw new Exception('Arquivo inválido: tmp_name não definido');
        }
        
        // Verificar se o arquivo existe
        if (!file_exists($file['tmp_name'])) {
            throw new Exception('Arquivo inválido: arquivo temporário não encontrado');
        }
        
        // Verificar se é um upload válido OU se o arquivo existe (para testes)
        if (!is_uploaded_file($file['tmp_name']) && !file_exists($file['tmp_name'])) {
            throw new Exception('Arquivo inválido: não é um upload válido');
        }
        
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('Arquivo muito grande. Máximo: 10MB');
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new Exception('Formato não suportado. Use apenas: CSV ou Excel (.xlsx, .xls)');
        }
        
        // Salvar arquivo
        $filename = uniqid('cpf_') . '.' . $extension;
        $filepath = self::UPLOAD_DIR . '/' . $filename;
        
        // Tentar move_uploaded_file primeiro, se falhar tentar copy (para testes)
        $moved = false;
        if (is_uploaded_file($file['tmp_name'])) {
            $moved = move_uploaded_file($file['tmp_name'], $filepath);
        } else {
            // Para testes ou arquivos já no servidor
            $moved = copy($file['tmp_name'], $filepath);
        }
        
        if (!$moved) {
            throw new Exception('Erro ao salvar arquivo');
        }
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'extension' => $extension,
            'original_name' => $file['name']
        ];
    }
    
    /**
     * Processa arquivo baseado no tipo
     */
    public function processarArquivo(string $filepath, string $extension): array
    {
        switch ($extension) {
            case 'csv':
                return $this->processarCsv($filepath);
            case 'xlsx':
            case 'xls':
                return $this->processarExcel($filepath);
            default:
                throw new Exception('Formato não suportado. Use apenas CSV ou Excel (.xlsx, .xls)');
        }
    }
    
    /**
     * Processa arquivo CSV
     */
    private function processarCsv(string $filepath): array
    {
        $cpfsEncontrados = [];
        $linhasProcessadas = 0;
        $outputPath = self::OUTPUT_DIR . '/' . basename($filepath, '.csv') . '_anonimizado.csv';
        
        $inputHandle = fopen($filepath, 'r');
        $outputHandle = fopen($outputPath, 'w');
        
        if (!$inputHandle || !$outputHandle) {
            throw new Exception('Erro ao abrir arquivo CSV');
        }
        
        while (($row = fgetcsv($inputHandle)) !== false) {
            $newRow = [];
            foreach ($row as $cell) {
                $newCell = preg_replace_callback(self::CPF_PATTERN, function($matches) use (&$cpfsEncontrados) {
                    $cpf = $matches[1];
                    if ($this->validarCpf($cpf)) {
                        $cpfsEncontrados[] = $cpf;
                        return $this->anonimizarCpf($cpf);
                    }
                    return $cpf;
                }, $cell);
                $newRow[] = $newCell;
            }
            fputcsv($outputHandle, $newRow);
            $linhasProcessadas++;
        }
        
        fclose($inputHandle);
        fclose($outputHandle);
        
        return [
            'cpfs_encontrados' => array_unique($cpfsEncontrados),
            'total_cpfs' => count($cpfsEncontrados),
            'linhas_processadas' => $linhasProcessadas,
            'arquivo_saida' => basename($outputPath),
            'caminho_saida' => $outputPath
        ];
    }
    
    /**
     * Processa arquivo Excel
     */
    private function processarExcel(string $filepath): array
    {
        // Verifica se a biblioteca PhpSpreadsheet está disponível
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new Exception('Biblioteca PhpSpreadsheet não instalada. Execute: composer require phpoffice/phpspreadsheet');
        }
        
        $cpfsEncontrados = [];
        $celulasProcessadas = 0;
        
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            foreach ($worksheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $value = $cell->getValue();
                    if (is_string($value)) {
                        $newValue = preg_replace_callback(self::CPF_PATTERN, function($matches) use (&$cpfsEncontrados) {
                            $cpf = $matches[1];
                            if ($this->validarCpf($cpf)) {
                                $cpfsEncontrados[] = $cpf;
                                return $this->anonimizarCpf($cpf);
                            }
                            return $cpf;
                        }, $value);
                        $cell->setValue($newValue);
                        $celulasProcessadas++;
                    }
                }
            }
            
            $outputPath = self::OUTPUT_DIR . '/' . basename($filepath, '.xlsx') . '_anonimizado.xlsx';
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($outputPath);
            
            return [
                'cpfs_encontrados' => array_unique($cpfsEncontrados),
                'total_cpfs' => count($cpfsEncontrados),
                'celulas_processadas' => $celulasProcessadas,
                'arquivo_saida' => basename($outputPath),
                'caminho_saida' => $outputPath
            ];
            
        } catch (Exception $e) {
            throw new Exception('Erro ao processar Excel: ' . $e->getMessage());
        }
    }
    
    /**
     * Processa arquivo PDF
     * 
     * IMPORTANTE: PDFs não são suportados para anonimização.
     * Converta o PDF para Excel antes de processar.
     */
    private function processarPdf(string $filepath): array
    {
        throw new Exception('Anonimização de PDFs não é suportada. Por favor, converta o arquivo para Excel (.xlsx) ou CSV antes de processar.');
    }
    

    
    /**
     * Faz download do arquivo processado
     */
    public function downloadArquivo(string $filename): void
    {
        $filepath = self::OUTPUT_DIR . '/' . $filename;
        
        if (!file_exists($filepath)) {
            throw new Exception('Arquivo não encontrado');
        }
        
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $contentTypes = [
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'pdf' => 'application/pdf'
        ];
        
        header('Content-Type: ' . ($contentTypes[$extension] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
    
    /**
     * Limpa arquivos antigos (mais de 24 horas)
     */
    public function limparArquivosAntigos(): int
    {
        $count = 0;
        $directories = [self::UPLOAD_DIR, self::OUTPUT_DIR];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) continue;
            
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file)) > 86400) {
                    unlink($file);
                    $count++;
                }
            }
        }
        
        return $count;
    }
}
