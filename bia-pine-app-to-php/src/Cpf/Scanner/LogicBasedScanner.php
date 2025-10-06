<?php

namespace App\Cpf\Scanner;

use App\Cpf\Scanner\CpfScannerInterface;

class LogicBasedScanner implements CpfScannerInterface
{
    public function scan(string $textContent): array
    {
        if (empty(trim($textContent))) {
            return [];
        }

        // Limpeza de separadores e normalização
        $textContent = str_replace(' |SEPARATOR| ', ' ', $textContent);
        $textContent = preg_replace('/\s+/', ' ', $textContent);

        // Padrões mais específicos para CPF, priorizando formatos com pontuação
        $patterns = [
            // Formato tradicional com pontos e hífen: 000.000.000-00
            '/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/',
            // Formato com pontos sem hífen: 000.000.00000
            '/\b\d{3}\.\d{3}\.\d{5}\b/',
            // Formato apenas com hífen: 000000000-00
            '/\b\d{9}-\d{2}\b/',
            // Formato com espaços estruturados: 000 000 000 00
            '/\b\d{3}\s\d{3}\s\d{3}\s\d{2}\b/',
            // 11 dígitos seguidos (mais restritivo - apenas com word boundaries)
            '/\b\d{11}\b/',
        ];

        $validCpfs = [];
        $seenCpfs = []; // Para evitar duplicatas
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $textContent, $matches);
            
            foreach ($matches[0] as $cpf) {
                if (empty($cpf)) continue;
                
                $normalizedCpf = $this->normalizeCpf($cpf);
                
                // Pular se já processamos este CPF
                if (isset($seenCpfs[$normalizedCpf])) {
                    continue;
                }
                
                $seenCpfs[$normalizedCpf] = true;
                
                // SEMPRE validar o dígito verificador antes de aceitar
                if ($this->isValidCpf($normalizedCpf)) {
                    $validCpfs[] = $normalizedCpf;
                }
            }
        }

        return array_values(array_unique($validCpfs));
    }

    /**
     * Valida CPF usando o algoritmo oficial brasileiro com dígitos verificadores.
     * 
     * Algoritmo:
     * 1. Primeiro dígito: multiplica os 9 primeiros dígitos por 10,9,8,7,6,5,4,3,2
     *    Soma tudo, divide por 11 e pega o resto. Se resto < 2, DV=0, senão DV=11-resto
     * 2. Segundo dígito: multiplica os 10 primeiros dígitos por 11,10,9,8,7,6,5,4,3,2
     *    Soma tudo, divide por 11 e pega o resto. Se resto < 2, DV=0, senão DV=11-resto
     */
    private function isValidCpf(string $cpf): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // Deve ter exatamente 11 dígitos
        if (strlen($cpf) !== 11) {
            return false;
        }

        // Rejeita CPFs com todos os dígitos iguais (111.111.111-11, etc)
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Validação do primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += intval($cpf[$i]) * (10 - $i);
        }
        $resto = $soma % 11;
        $digitoVerificador1 = ($resto < 2) ? 0 : 11 - $resto;

        if (intval($cpf[9]) != $digitoVerificador1) {
            return false;
        }

        // Validação do segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += intval($cpf[$i]) * (11 - $i);
        }
        $resto = $soma % 11;
        $digitoVerificador2 = ($resto < 2) ? 0 : 11 - $resto;
        
        if (intval($cpf[10]) != $digitoVerificador2) {
            return false;
        }

        return true;
    }

    private function normalizeCpf(string $cpf): string
    {
        return preg_replace('/[^0-9]/', '', $cpf);
    }

    public function getStats(string $textContent): array
    {
        // Usa os mesmos padrões da função scan
        $patterns = [
            '/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/',
            '/\b\d{3}\.\d{3}\.\d{5}\b/',
            '/\b\d{9}-\d{2}\b/',
            '/\b\d{3}\s\d{3}\s\d{3}\s\d{2}\b/',
            '/\b\d{11}\b/',
        ];
        
        $allCandidates = [];
        $validCount = 0;
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $textContent, $matches);
            $candidates = $matches[0] ?? [];
            
            foreach ($candidates as $cpf) {
                $normalizedCpf = $this->normalizeCpf($cpf);
                if (!in_array($normalizedCpf, $allCandidates)) {
                    $allCandidates[] = $normalizedCpf;
                    if ($this->isValidCpf($normalizedCpf)) {
                        $validCount++;
                    }
                }
            }
        }
        
        return [
            'candidates_found' => count($allCandidates),
            'valid_cpfs' => $validCount,
            'invalid_cpfs' => count($allCandidates) - $validCount
        ];
    }

    /**
     * Método para retornar CPFs com status para armazenamento em massa.
     * Retorna apenas CPFs válidos (com dígitos verificadores corretos).
     */
    public function scanForStorage(string $textContent): array
    {
        if (empty(trim($textContent))) {
            return [];
        }

        // Limpeza (essencial para o uso de separadores em parsers como CSV/JSON)
        $textContent = str_replace(' |SEPARATOR| ', ' ', $textContent);
        $textContent = preg_replace('/\s+/', ' ', $textContent); 

        // Padrões mais específicos para CPF
        $patterns = [
            '/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/',
            '/\b\d{3}\.\d{3}\.\d{5}\b/',
            '/\b\d{9}-\d{2}\b/',
            '/\b\d{3}\s\d{3}\s\d{3}\s\d{2}\b/',
            '/\b\d{11}\b/',
        ];

        $uniqueNormalizedCpfs = [];
        $cpfsToStore = [];
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $textContent, $matches);
            $candidates = $matches[0] ?? [];

            foreach ($candidates as $cpf) {
                $normalizedCpf = $this->normalizeCpf($cpf);
                
                // Processa apenas CPFs únicos
                if (!isset($uniqueNormalizedCpfs[$normalizedCpf])) {
                    $uniqueNormalizedCpfs[$normalizedCpf] = true;
                    
                    // SEMPRE valida o dígito verificador
                    $isValid = $this->isValidCpf($normalizedCpf);
                    
                    // Retorna apenas CPFs válidos
                    if ($isValid) {
                        $cpfsToStore[] = [
                            'cpf' => $normalizedCpf,
                            'isValid' => true
                        ];
                    }
                }
            }
        }

        return $cpfsToStore;
    }
}
