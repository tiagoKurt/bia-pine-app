<?php

namespace App;

class ConfigurationValidator
{
    private string $projectRoot;
    private array $validationResults = [];
    
    public function __construct()
    {
        $this->projectRoot = $this->findProjectRoot();
    }
    
    public function validateComposerConfig(): bool
    {
        $composerPath = $this->projectRoot . '/composer.json';
        
        if (!file_exists($composerPath)) {
            $this->addValidationResult(
                'composer_file',
                false,
                'Arquivo composer.json não encontrado',
                ['path' => $composerPath],
                'Execute "composer init" para criar o arquivo composer.json'
            );
            return false;
        }
        
        $content = file_get_contents($composerPath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addValidationResult(
                'composer_json_syntax',
                false,
                'Sintaxe JSON inválida no composer.json: ' . json_last_error_msg(),
                ['path' => $composerPath],
                'Corrija a sintaxe JSON no arquivo composer.json'
            );
            return false;
        }
        
        // Verificar configuração de autoload
        $hasAutoload = isset($data['autoload']['psr-4']['App\\']);
        $autoloadPath = $hasAutoload ? $data['autoload']['psr-4']['App\\'] : null;
        
        if (!$hasAutoload) {
            $this->addValidationResult(
                'composer_autoload_config',
                false,
                'Configuração de autoload PSR-4 para namespace App\\ não encontrada',
                ['expected_config' => ['autoload' => ['psr-4' => ['App\\' => 'src/']]]],
                'Adicione a configuração de autoload no composer.json e execute "composer dump-autoload"'
            );
            return false;
        }
        
        // Verificar se o diretório de autoload existe
        $autoloadDir = $this->projectRoot . '/' . rtrim($autoloadPath, '/');
        if (!is_dir($autoloadDir)) {
            $this->addValidationResult(
                'composer_autoload_directory',
                false,
                "Diretório de autoload não existe: {$autoloadDir}",
                ['path' => $autoloadDir, 'configured_path' => $autoloadPath],
                "Crie o diretório {$autoloadDir} ou ajuste a configuração no composer.json"
            );
            return false;
        }
        
        $this->addValidationResult(
            'composer_config',
            true,
            'Configuração do Composer válida',
            ['autoload_path' => $autoloadPath, 'autoload_dir' => $autoloadDir]
        );
        
        return true;
    }
    
    public function validateFilePermissions(): array
    {
        $criticalFiles = [
            'vendor/autoload.php' => 'Autoloader do Composer',
            'composer.json' => 'Configuração do Composer',
            'config.php' => 'Configuração da aplicação',
            'src/Bia.php' => 'Classe Bia',
            'src/Pine.php' => 'Classe Pine'
        ];
        
        $permissions = [];
        $allValid = true;
        
        foreach ($criticalFiles as $relativePath => $description) {
            $fullPath = $this->projectRoot . '/' . $relativePath;
            
            $result = [
                'path' => $fullPath,
                'relative_path' => $relativePath,
                'description' => $description,
                'exists' => file_exists($fullPath),
                'readable' => false,
                'writable' => false,
                'permissions' => null,
                'owner' => null
            ];
            
            if ($result['exists']) {
                $result['readable'] = is_readable($fullPath);
                $result['writable'] = is_writable($fullPath);
                $result['permissions'] = substr(sprintf('%o', fileperms($fullPath)), -4);
                
                if (function_exists('posix_getpwuid') && function_exists('fileowner')) {
                    $ownerInfo = posix_getpwuid(fileowner($fullPath));
                    $result['owner'] = $ownerInfo['name'] ?? 'unknown';
                }
            }
            
            $isValid = $result['exists'] && $result['readable'];
            
            $this->addValidationResult(
                "file_permissions_{$relativePath}",
                $isValid,
                $isValid 
                    ? "{$description} tem permissões adequadas"
                    : ($result['exists'] 
                        ? "{$description} existe mas não é legível"
                        : "{$description} não encontrado"),
                $result,
                !$isValid ? ($result['exists'] 
                    ? "Execute 'chmod 644 {$fullPath}' para corrigir permissões"
                    : "Verifique se o arquivo {$relativePath} foi criado corretamente") : null
            );
            
            $permissions[$relativePath] = $result;
            
            if (!$isValid) {
                $allValid = false;
            }
        }
        
        return $permissions;
    }
    
    public function validatePaths(): array
    {
        $paths = [
            'project_root' => $this->projectRoot,
            'vendor_dir' => $this->projectRoot . '/vendor',
            'src_dir' => $this->projectRoot . '/src',
            'public_dir' => $this->projectRoot . '/public',
            'composer_autoload' => $this->projectRoot . '/vendor/autoload.php',
            'config_file' => $this->projectRoot . '/config.php'
        ];
        
        $pathValidation = [];
        $allValid = true;
        
        foreach ($paths as $name => $path) {
            $exists = file_exists($path);
            $isDir = is_dir($path);
            $isFile = is_file($path);
            $readable = $exists && is_readable($path);
            
            $expectedType = in_array($name, ['vendor_dir', 'src_dir', 'public_dir']) ? 'directory' : 'file';
            $actualType = $isDir ? 'directory' : ($isFile ? 'file' : 'unknown');
            
            $isValid = $exists && $readable && (
                ($expectedType === 'directory' && $isDir) ||
                ($expectedType === 'file' && $isFile)
            );
            
            $pathValidation[$name] = [
                'path' => $path,
                'exists' => $exists,
                'readable' => $readable,
                'expected_type' => $expectedType,
                'actual_type' => $actualType,
                'valid' => $isValid
            ];
            
            $this->addValidationResult(
                "path_{$name}",
                $isValid,
                $isValid 
                    ? "Caminho {$name} válido"
                    : "Problema com caminho {$name}: " . ($exists 
                        ? ($readable 
                            ? "tipo incorreto (esperado: {$expectedType}, atual: {$actualType})"
                            : "não é legível")
                        : "não existe"),
                $pathValidation[$name],
                !$isValid ? "Verifique se o {$expectedType} {$path} existe e tem permissões adequadas" : null
            );
            
            if (!$isValid) {
                $allValid = false;
            }
        }
        
        return $pathValidation;
    }
    
    public function fixCommonIssues(): array
    {
        $fixes = [];
        
        // 1. Regenerar autoloader do Composer se necessário
        $composerAutoloadPath = $this->projectRoot . '/vendor/autoload.php';
        if (!file_exists($composerAutoloadPath)) {
            $fixes[] = [
                'issue' => 'missing_composer_autoloader',
                'action' => 'regenerate_autoloader',
                'command' => 'composer dump-autoload',
                'description' => 'Regenerar autoloader do Composer',
                'status' => 'manual_action_required'
            ];
        }
        
        // 2. Verificar e sugerir correção de permissões
        $permissions = $this->validateFilePermissions();
        foreach ($permissions as $file => $info) {
            if ($info['exists'] && !$info['readable']) {
                $fixes[] = [
                    'issue' => 'file_not_readable',
                    'action' => 'fix_permissions',
                    'command' => "chmod 644 {$info['path']}",
                    'description' => "Corrigir permissões do arquivo {$file}",
                    'status' => 'manual_action_required'
                ];
            }
        }
        
        // 3. Verificar configuração do composer.json
        if (!$this->validateComposerConfig()) {
            $fixes[] = [
                'issue' => 'invalid_composer_config',
                'action' => 'fix_composer_config',
                'description' => 'Corrigir configuração do composer.json',
                'status' => 'manual_action_required',
                'details' => 'Adicione a configuração de autoload PSR-4 para o namespace App\\'
            ];
        }
        
        // 4. Criar diretórios necessários se não existirem
        $requiredDirs = ['src', 'vendor', 'public'];
        foreach ($requiredDirs as $dir) {
            $dirPath = $this->projectRoot . '/' . $dir;
            if (!is_dir($dirPath)) {
                $fixes[] = [
                    'issue' => 'missing_directory',
                    'action' => 'create_directory',
                    'command' => "mkdir -p {$dirPath}",
                    'description' => "Criar diretório {$dir}",
                    'status' => 'can_auto_fix'
                ];
                
                // Tentar criar o diretório automaticamente
                if (@mkdir($dirPath, 0755, true)) {
                    $fixes[count($fixes) - 1]['status'] = 'auto_fixed';
                }
            }
        }
        
        return $fixes;
    }
    
    public function getValidationResults(): array
    {
        return $this->validationResults;
    }
    
    public function generateValidationReport(): string
    {
        $report = "=== RELATÓRIO DE VALIDAÇÃO DE CONFIGURAÇÃO ===\n\n";
        $report .= "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
        $report .= "Projeto: {$this->projectRoot}\n\n";
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($this->validationResults as $result) {
            if ($result['valid']) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        
        $report .= "RESUMO:\n";
        $report .= "✓ Validações bem-sucedidas: {$successCount}\n";
        $report .= "✗ Problemas encontrados: {$errorCount}\n\n";
        
        if ($errorCount > 0) {
            $report .= "PROBLEMAS ENCONTRADOS:\n\n";
            
            foreach ($this->validationResults as $result) {
                if (!$result['valid']) {
                    $report .= "✗ {$result['message']}\n";
                    if ($result['solution']) {
                        $report .= "   Solução: {$result['solution']}\n";
                    }
                    $report .= "\n";
                }
            }
        }
        
        $report .= "CORREÇÕES SUGERIDAS:\n\n";
        $fixes = $this->fixCommonIssues();
        
        foreach ($fixes as $fix) {
            $report .= "• {$fix['description']}\n";
            if (isset($fix['command'])) {
                $report .= "  Comando: {$fix['command']}\n";
            }
            $report .= "  Status: {$fix['status']}\n\n";
        }
        
        return $report;
    }
    
    private function addValidationResult(string $key, bool $valid, string $message, array $details = [], ?string $solution = null): void
    {
        $this->validationResults[$key] = [
            'valid' => $valid,
            'message' => $message,
            'details' => $details,
            'solution' => $solution,
            'timestamp' => time()
        ];
    }
    
    private function findProjectRoot(): string
    {
        $currentDir = __DIR__;
        
        // Procurar pelo composer.json subindo na hierarquia
        $dir = $currentDir;
        while ($dir !== dirname($dir)) {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }
        
        // Fallback: assumir que estamos em src/ e a raiz é um nível acima
        if (basename($currentDir) === 'src') {
            return dirname($currentDir);
        }
        
        // Último fallback: usar o diretório pai do atual
        return dirname($currentDir);
    }
}