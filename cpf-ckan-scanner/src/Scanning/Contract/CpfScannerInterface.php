<?php

namespace App\Scanning\Contract;

interface CpfScannerInterface
{
    public function scan(string $textContent): array;
}
