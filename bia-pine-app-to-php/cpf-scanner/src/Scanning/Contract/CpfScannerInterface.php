<?php

namespace CpfScanner\Scanning\Contract;

interface CpfScannerInterface
{
    public function scan(string $textContent): array;
}
