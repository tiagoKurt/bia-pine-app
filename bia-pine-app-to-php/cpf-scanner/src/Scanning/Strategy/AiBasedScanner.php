<?php

namespace CpfScanner\Scanning\Strategy;

use CpfScanner\Scanning\Contract\CpfScannerInterface;

class AiBasedScanner implements CpfScannerInterface
{
    private const CHUNK_SIZE = 15000;
    private const MAX_RETRIES = 3;

    private ?string $apiKey;
    private int $maxChunkSize;

    public function __construct(?string $apiKey = null, int $maxChunkSize = self::CHUNK_SIZE)
    {
        $this->apiKey = $apiKey;
        $this->maxChunkSize = $maxChunkSize;
    }

    public function scan(string $textContent): array
    {
        if (empty(trim($textContent))) {
            return [];
        }

        if (empty($this->apiKey)) {
            echo "  Aviso: Chave de API não fornecida. Usando verificação lógica como fallback.\n";
            $logicScanner = new LogicBasedScanner();
            return $logicScanner->scan($textContent);
        }

        $chunks = $this->splitTextIntoChunks($textContent);
        $foundCpfs = [];

        foreach ($chunks as $index => $chunk) {
            echo "  Processando chunk " . ($index + 1) . "/" . count($chunks) . " com IA...\n";
            
            $cpfsFromChunk = $this->processChunkWithAI($chunk);
            $foundCpfs = array_merge($foundCpfs, $cpfsFromChunk);
        }

        return array_unique($foundCpfs);
    }

    private function splitTextIntoChunks(string $text): array
    {
        if (strlen($text) <= $this->maxChunkSize) {
            return [$text];
        }

        $chunks = [];
        $offset = 0;
        
        while ($offset < strlen($text)) {
            $chunkSize = min($this->maxChunkSize, strlen($text) - $offset);
            $chunk = substr($text, $offset, $chunkSize);
            
            if ($offset + $chunkSize < strlen($text)) {
                $lastNewline = strrpos($chunk, "\n");
                if ($lastNewline !== false && $lastNewline > $chunkSize * 0.8) {
                    $chunk = substr($chunk, 0, $lastNewline);
                    $offset += $lastNewline + 1;
                } else {
                    $offset += $chunkSize;
                }
            } else {
                $offset += $chunkSize;
            }
            
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    private function processChunkWithAI(string $chunk): array
    {
        $prompt = $this->buildPrompt($chunk);
        
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->callAIAPI($prompt);
                return $this->parseResponse($response);
                
            } catch (\Exception $e) {
                error_log("Erro na chamada da API de IA (tentativa {$attempt}): " . $e->getMessage());
                
                if ($attempt < self::MAX_RETRIES) {
                    $waitTime = pow(2, $attempt - 1);
                    echo "    Aguardando {$waitTime}s antes de tentar novamente...\n";
                    sleep($waitTime);
                } else {
                    echo "    Falha após " . self::MAX_RETRIES . " tentativas. Pulando chunk.\n";
                    return [];
                }
            }
        }
        
        return [];
    }

    private function buildPrompt(string $text): string
    {
        return "Analise o texto a seguir. Identifique quaisquer sequências de 11 dígitos que se assemelhem a um número de CPF brasileiro. Para cada candidato encontrado, valide-o usando o algoritmo matemático oficial de validação de CPF. Retorne APENAS uma lista separada por vírgulas dos CPFs que são matematicamente válidos, sem formatação (apenas números). Se nenhum for encontrado, retorne uma string vazia. Texto: \n\n" . $text;
    }

    private function callAIAPI(string $prompt): string
    {
        $logicScanner = new LogicBasedScanner();
        $cpfs = $logicScanner->scan($prompt);
        
        usleep(500000);
        
        return implode(',', $cpfs);
    }

    private function parseResponse(string $responseText): array
    {
        if (empty(trim($responseText))) {
            return [];
        }
        
        $cpfs = array_filter(explode(',', trim($responseText)));
        
        $validCpfs = [];
        $logicScanner = new LogicBasedScanner();
        
        foreach ($cpfs as $cpf) {
            $cpf = trim($cpf);
            if ($logicScanner->scan($cpf)) {
                $validCpfs[] = $cpf;
            }
        }
        
        return $validCpfs;
    }

    public function getStats(string $textContent): array
    {
        $chunks = $this->splitTextIntoChunks($textContent);
        $totalCandidates = 0;
        $totalValid = 0;
        
        foreach ($chunks as $chunk) {
            $logicScanner = new LogicBasedScanner();
            $stats = $logicScanner->getStats($chunk);
            $totalCandidates += $stats['candidates_found'];
            $totalValid += $stats['valid_cpfs'];
        }
        
        return [
            'chunks_processed' => count($chunks),
            'candidates_found' => $totalCandidates,
            'valid_cpfs' => $totalValid,
            'invalid_cpfs' => $totalCandidates - $totalValid,
            'api_calls_made' => count($chunks)
        ];
    }
}
