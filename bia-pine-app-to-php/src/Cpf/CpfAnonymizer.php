<?php

namespace App\Cpf;

use Exception;

/**
 * Classe para anonimizar CPFs em arquivos (CSV, Excel, PDF)
 * Baseado na lógica Python fornecida
 */
class CpfAnonymizer
{
    // Padrões específicos para CPF (MESMO padrão usado no scanner CKAN)
    // Evita capturar CNPJs que têm 14 dígitos
    private const CPF_PATTERNS = [
        '/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/',      // 000.000.000-00
        '/\b\d{3}\.\d{3}\.\d{5}\b/',            // 000.000.00000
        '/\b\d{9}-\d{2}\b/',                    // 000000000-00
        '/\b\d{3}\s\d{3}\s\d{3}\s\d{2}\b/',     // 000 000 000 00
        '/\b\d{11}\b/',                         // 00000000000 (apenas 11 dígitos com word boundaries)
    ];
    
    private const UPLOAD_DIR = __DIR__ . '/../../uploads/cpf_anonymizer';
    private const OUTPUT_DIR = __DIR__ . '/../../uploads/cpf_anonymizer/output';
    
    private array $allowedExtensions = ['csv', 'xlsx', 'xls'];
    private int $maxFileSize = 104857600; // 100MB
    
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
     * IMPORTANTE: Usa o MESMO algoritmo da análise CKAN (validaCPF em functions.php)
     */
    public function validarCpf(string $cpf): bool
    {
        // Remover caracteres não numéricos (mesmo padrão do CKAN)
        $cpf = preg_replace('/[^0-9]/is', '', $cpf);

        // Verificar se tem 11 dígitos
        if (strlen($cpf) != 11) {
            return false;
        }

        // Verificar se todos os dígitos são iguais (CPF inválido)
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Validar dígitos verificadores (algoritmo oficial brasileiro)
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

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
            throw new Exception('Arquivo muito grande. Máximo: 100MB');
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
        
        // Manter nome original com sufixo _anonimizado
        $pathInfo = pathinfo($filepath);
        $nomeOriginal = $pathInfo['filename'];
        $outputPath = self::OUTPUT_DIR . '/' . $nomeOriginal . '_anonimizado.csv';
        
        // Detectar encoding e delimitador
        $content = file_get_contents($filepath);
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        
        // Log para debug
        error_log("CPF Anonymizer: Processando arquivo CSV: " . basename($filepath));
        error_log("CPF Anonymizer: Encoding detectado: " . ($encoding ?: 'UTF-8'));
        
        // Detectar delimitador
        $firstLine = strtok($content, "\n");
        $delimiters = [',', ';', "\t", '|'];
        $delimiter = ',';
        $maxCount = 0;
        foreach ($delimiters as $del) {
            $count = substr_count($firstLine, $del);
            if ($count > $maxCount) {
                $maxCount = $count;
                $delimiter = $del;
            }
        }
        
        error_log("CPF Anonymizer: Delimitador detectado: " . ($delimiter === "\t" ? 'TAB' : $delimiter));
        
        $inputHandle = fopen($filepath, 'r');
        $outputHandle = fopen($outputPath, 'w');
        
        if (!$inputHandle || !$outputHandle) {
            throw new Exception('Erro ao abrir arquivo CSV');
        }
        
        // Processar linha por linha mantendo estrutura original
        while (($row = fgetcsv($inputHandle, 0, $delimiter)) !== false) {
            $newRow = [];
            foreach ($row as $cell) {
                // Converter encoding se necessário
                if ($encoding && $encoding !== 'UTF-8') {
                    $cell = mb_convert_encoding($cell, 'UTF-8', $encoding);
                }
                
                // Substituir apenas CPFs válidos usando a mesma lógica do scanner CKAN
                $newCell = $this->anonimizarCpfsNaString($cell, $cpfsEncontrados);
                $newRow[] = $newCell;
            }
            fputcsv($outputHandle, $newRow, $delimiter);
            $linhasProcessadas++;
        }
        
        fclose($inputHandle);
        fclose($outputHandle);
        
        // Remover duplicatas e contar
        $cpfsUnicos = array_unique($cpfsEncontrados);
        
        error_log("CPF Anonymizer: CPFs encontrados (com duplicatas): " . count($cpfsEncontrados));
        error_log("CPF Anonymizer: CPFs únicos: " . count($cpfsUnicos));
        error_log("CPF Anonymizer: Linhas processadas: " . $linhasProcessadas);
        
        return [
            'cpfs_encontrados' => $cpfsUnicos,
            'total_cpfs' => count($cpfsUnicos), // Usar CPFs únicos
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
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new Exception('Biblioteca PhpSpreadsheet não instalada. Execute: composer require phpoffice/phpspreadsheet');
        }
        
        $cpfsEncontrados = [];
        $celulasModificadas = 0;
        
        try {
            error_log("CPF Anonymizer: Processando arquivo Excel: " . basename($filepath));
            
            // Carregar arquivo mantendo formatação original
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
            
            // Processar todas as planilhas
            foreach ($spreadsheet->getAllSheets() as $worksheet) {
                $sheetName = $worksheet->getTitle();
                error_log("CPF Anonymizer: Processando planilha: " . $sheetName);
                
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                
                // Iterar apenas pelas células com conteúdo
                for ($row = 1; $row <= $highestRow; $row++) {
                    for ($col = 'A'; $col <= $highestColumn; $col++) {
                        $cell = $worksheet->getCell($col . $row);
                        $value = $cell->getValue();
                        
                        // Processar apenas strings
                        if (is_string($value) && !empty($value)) {
                            $originalValue = $value;
                            
                            // Substituir apenas CPFs válidos usando a mesma lógica do scanner CKAN
                            $newValue = $this->anonimizarCpfsNaString($value, $cpfsEncontrados);
                            
                            // Atualizar apenas se houve mudança
                            if ($newValue !== $originalValue) {
                                $cell->setValue($newValue);
                                $celulasModificadas++;
                            }
                        }
                    }
                }
            }
            
            // Manter nome original com sufixo _anonimizado
            $pathInfo = pathinfo($filepath);
            $nomeOriginal = $pathInfo['filename'];
            $extensao = $pathInfo['extension'];
            $outputPath = self::OUTPUT_DIR . '/' . $nomeOriginal . '_anonimizado.' . $extensao;
            
            // Salvar mantendo formato original
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, ucfirst(strtolower($extensao)));
            $writer->save($outputPath);
            
            // Remover duplicatas e contar
            $cpfsUnicos = array_unique($cpfsEncontrados);
            
            error_log("CPF Anonymizer: CPFs encontrados (com duplicatas): " . count($cpfsEncontrados));
            error_log("CPF Anonymizer: CPFs únicos: " . count($cpfsUnicos));
            error_log("CPF Anonymizer: Células modificadas: " . $celulasModificadas);
            
            return [
                'cpfs_encontrados' => $cpfsUnicos,
                'total_cpfs' => count($cpfsUnicos), // Usar CPFs únicos
                'celulas_modificadas' => $celulasModificadas,
                'arquivo_saida' => basename($outputPath),
                'caminho_saida' => $outputPath
            ];
            
        } catch (Exception $e) {
            throw new Exception('Erro ao processar Excel: ' . $e->getMessage());
        }
    }
    
    /**
     * Detecta CPFs válidos em uma string usando EXATAMENTE a mesma lógica do scanner CKAN
     * IMPORTANTE: Implementação idêntica ao LogicBasedScanner
     */
    private function detectarCpfsValidos(string $textContent): array
    {
        if (empty(trim($textContent))) {
            return [];
        }

        // Limpeza idêntica ao scanner CKAN
        $textContent = str_replace(' |SEPARATOR| ', ' ', $textContent);
        $textContent = preg_replace('/\s+/', ' ', $textContent);

        $validCpfs = [];
        $seenCpfs = []; // Para evitar duplicatas (escopo global à função)
        
        // Processar cada padrão EXATAMENTE como o scanner CKAN
        foreach (self::CPF_PATTERNS as $pattern) {
            preg_match_all($pattern, $textContent, $matches);
            
            foreach ($matches[0] as $cpf) {
                if (empty($cpf)) continue;
                
                $normalizedCpf = preg_replace('/[^0-9]/', '', $cpf);
                
                // Pular se já processamos este CPF (MESMA lógica do scanner)
                if (isset($seenCpfs[$normalizedCpf])) {
                    continue;
                }
                
                $seenCpfs[$normalizedCpf] = true;
                
                // SEMPRE validar o dígito verificador antes de aceitar
                if ($this->validarCpf($normalizedCpf)) {
                    $validCpfs[] = $normalizedCpf;
                }
            }
        }

        return array_values(array_unique($validCpfs));
    }
    
    /**
     * Anonimiza CPFs em uma string usando EXATAMENTE a mesma detecção do scanner CKAN
     */
    private function anonimizarCpfsNaString(string $text, array &$cpfsEncontrados): string
    {
        // Primeiro, detectar todos os CPFs válidos usando a mesma lógica do scanner
        $cpfsValidos = $this->detectarCpfsValidos($text);
        
        // Adicionar à lista de CPFs encontrados
        foreach ($cpfsValidos as $cpf) {
            $cpfsEncontrados[] = $cpf;
        }
        
        // Agora substituir cada CPF válido encontrado
        foreach ($cpfsValidos as $cpf) {
            $cpfFormatado = $this->formatarCpfParaBusca($cpf);
            $anonimizado = $this->anonimizarCpf($cpf);
            
            // Substituir todas as ocorrências deste CPF específico
            foreach (self::CPF_PATTERNS as $pattern) {
                $text = preg_replace_callback($pattern, function($matches) use ($cpf, $anonimizado) {
                    $cpfEncontrado = preg_replace('/[^0-9]/', '', $matches[0]);
                    if ($cpfEncontrado === $cpf) {
                        return $anonimizado;
                    }
                    return $matches[0];
                }, $text);
            }
        }
        
        return $text;
    }
    
    /**
     * Formata CPF para busca (mantém formatação original se possível)
     */
    private function formatarCpfParaBusca(string $cpf): string
    {
        // Retorna o CPF limpo (apenas números)
        return $cpf;
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
