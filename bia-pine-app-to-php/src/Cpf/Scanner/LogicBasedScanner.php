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

        // Limpeza de separadores fortes, caso usados nos parsers
        $textContent = str_replace(' |SEPARATOR| ', ' ', $textContent);
        
        // Limpeza adicional de caracteres especiais que podem interferir na detecção
        $textContent = preg_replace('/\s+/', ' ', $textContent); // Normaliza espaços múltiplos

        // Padrões mais abrangentes para capturar CPFs em diferentes formatos
        $patterns = [
            // Formato tradicional: 000.000.000-00
            '/(?:\b|\D)(\d{3}\.?\d{3}\.?\d{3}-?\d{2})(?:\b|\D)/',
            // Formato sem pontuação: 00000000000
            '/(?:\b|\D)(\d{11})(?:\b|\D)/',
            // Formato com espaços: 000 000 000 00
            '/(?:\b|\D)(\d{3}\s?\d{3}\s?\d{3}\s?\d{2})(?:\b|\D)/',
            // Formato misto: 000.000.000 00 ou 000 000.000-00
            '/(?:\b|\D)(\d{3}[\.\s]?\d{3}[\.\s]?\d{3}[-\.\s]?\d{2})(?:\b|\D)/'
        ];

        $validCpfs = [];
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $textContent, $matches);
            $candidates = $matches[1] ?? [];

            foreach ($candidates as $cpf) {
                if ($this->isValidCpf($cpf)) {
                    $normalizedCpf = $this->normalizeCpf($cpf);
                    if (!in_array($normalizedCpf, $validCpfs)) {
                        $validCpfs[] = $normalizedCpf;
                    }
                }
            }
        }

        return $validCpfs;
    }

    private function isValidCpf(string $cpf): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }

        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Calcula o primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += intval($cpf[$i]) * (10 - $i);
        }
        $resto = $soma % 11;
        $digitoVerificador1 = ($resto < 2) ? 0 : 11 - $resto;

        if (intval($cpf[9]) != $digitoVerificador1) {
            return false;
        }

        // Calcula o segundo dígito verificador
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
            '/(?:\b|\D)(\d{3}\.?\d{3}\.?\d{3}-?\d{2})(?:\b|\D)/',
            '/(?:\b|\D)(\d{11})(?:\b|\D)/',
            '/(?:\b|\D)(\d{3}\s?\d{3}\s?\d{3}\s?\d{2})(?:\b|\D)/',
            '/(?:\b|\D)(\d{3}[\.\s]?\d{3}[\.\s]?\d{3}[-\.\s]?\d{2})(?:\b|\D)/'
        ];
        
        $allCandidates = [];
        $validCount = 0;
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $textContent, $matches);
            $candidates = $matches[1] ?? [];
            
            foreach ($candidates as $cpf) {
                $normalizedCpf = $this->normalizeCpf($cpf);
                if (!in_array($normalizedCpf, $allCandidates)) {
                    $allCandidates[] = $normalizedCpf;
                    if ($this->isValidCpf($cpf)) {
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
}
