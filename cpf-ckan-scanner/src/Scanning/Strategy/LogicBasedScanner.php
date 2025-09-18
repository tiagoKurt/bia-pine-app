<?php

namespace App\Scanning\Strategy;

use App\Scanning\Contract\CpfScannerInterface;

class LogicBasedScanner implements CpfScannerInterface
{
    public function scan(string $textContent): array
    {
        if (empty(trim($textContent))) {
            return [];
        }

        $pattern = '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/';
        preg_match_all($pattern, $textContent, $matches);

        $validCpfs = [];
        $candidates = $matches[0] ?? [];

        foreach ($candidates as $cpf) {
            if ($this->isValidCpf($cpf)) {
                $validCpfs[] = $this->normalizeCpf($cpf);
            }
        }

        return array_unique($validCpfs);
    }

    private function isValidCpf(string $cpf): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += $cpf[$i] * (10 - $i);
        }
        $resto = $soma % 11;
        $digitoVerificador1 = ($resto < 2) ? 0 : 11 - $resto;

        if ($cpf[9] != $digitoVerificador1) {
            return false;
        }

        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += $cpf[$i] * (11 - $i);
        }
        $resto = $soma % 11;
        $digitoVerificador2 = ($resto < 2) ? 0 : 11 - $resto;
        
        if ($cpf[10] != $digitoVerificador2) {
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
        $pattern = '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/';
        preg_match_all($pattern, $textContent, $matches);
        
        $candidates = $matches[0] ?? [];
        $validCount = 0;
        
        foreach ($candidates as $cpf) {
            if ($this->isValidCpf($cpf)) {
                $validCount++;
            }
        }
        
        return [
            'candidates_found' => count($candidates),
            'valid_cpfs' => $validCount,
            'invalid_cpfs' => count($candidates) - $validCount
        ];
    }
}
