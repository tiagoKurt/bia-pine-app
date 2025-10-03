<?php

namespace App\Cpf\Scanner;

interface CpfScannerInterface
{
    public function scan(string $textContent): array;
}
