<?php

namespace App;

class AutoloaderDiagnostic
{
    private array $diagnosticResults = [];
    
    public function runDiagnostic(): array
    {
        $this->diagnosticResults = [];
        
        // Verificar registro do autoloader
        $this->checkAutoloaderRegistration();
        
        // Verificar arquivos de classe
        $this->checkClassFiles();
        
        // Verificar diferenças de ambiente
        $this->checkEnvironmentDifferences();
        
        // Verificar configuração do Composer
        $this->checkComposerConfiguration();
        
        // Verificar permissões de arquivo
        $this->checkFilePermissions();
        
        return $this->diagnosticResults;
    }
    
    public function checkAutoloaderRegistration(): bool
    {
        $autoloaders = spl_autoload_functions();
        $composerAutoloaderFound = false;
        
        foreach ($autoloaders as $autoloader) {
            if (is_array($autoloader) && isset($autoloader[0])) {
                $className = get_class($autoloader[0]);
                if (strpos($className, 'Composer') !== false) {
                    $composerAutoloaderFound = true;
                    break;
                }
            }
        }
        
        $this->addResult(
            'autoloader_registration',
            $composerAutoloaderFound ? 'success' : 'error',
            $composerAutoloaderFound 
                ? 'Composer autoloader está registrado corretamente'
                : 'Composer autoloader não foi encontrado nos autoloaders registrados',
            [
                'total_autoloaders' => count($autoloaders),
                'autoloaders' => $this->describeAutoloaders($autoloaders),
                'composer_found' => $composerAutoloaderFound
            ],
            $composerAutoloaderFound ? null : 'Execute "composer dump-autoload" para regenerar o autoloader'
        );
        
        return $composerAutoloaderFound;
    }
    
    public function checkClassFiles(): array
    {
        $classFiles = [
            'App\\Bia' => 'src/Bia.php',
            'App\\Pine' => 'src/Pine.php'
        ];
        
        $results = [];
        
        foreach ($classFiles as $className => $filePath) {
            $fullPath = $this->getProjectRoot() . '/' . $filePath;
            $exists = file_exists($fullPath);
            $readable = $exists && is_readable($fullPath);
            $classExists = class_exists($className, false); // Não tentar autoload
            
            $status = ($exists && $readable) ? 'success' : 'error';
            if ($exists && $readable && !$classExists) {
                $status = 'warning';
            }
            
            $message = $exists 
                ? ($readable 
                    ? ($classExists 
                        ? "Classe {$className} carregada e arquivo acessível"
                        : "Arquivo existe mas classe não está carregada")
                    : "Arquivo existe mas não é legível")
                : "Arquivo {$filePath} não encontrado";
            
            $this->addResult(
                "class_file_{$className}",
                $status,
                $message,
                [
                    'class_name' => $className,
                    'file_path' => $filePath,
                    'full_path' => $fullPath,
                    'file_exists' => $exists,
                    'file_readable' => $readable,
                    'class_loaded' => $classExists,
                    'file_size' => $exists ? filesize($fullPath) : null
                ],
                !$exists ? "Verifique se o arquivo {$filePath} existe no projeto" : 
                (!$readable ? "Verifique as permissões do arquivo {$filePath}" : null)
            );
            
            $results[$className] = [
                'exists' => $exists,
                'readable' => $readable,
                'loaded' => $classExists,
                'path' => $fullPath
            ];
        }
        
        return $results;
    }
    
    public function checkEnvironmentDifferences(): array
    {
        $envInfo = [
            'sapi_name' => php_sapi_name(),
            'working_directory' => getcwd(),
            'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'N/A',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
            'include_path' => get_include_path(),
            'project_root' => $this->getProjectRoot(),
            'autoload_path' => $this->getProjectRoot() . '/vendor/autoload.php',
            'config_path' => $this->getProjectRoot() . '/config.php',
            'is_cli' => php_sapi_name() === 'cli',
            'is_web' => isset($_SERVER['HTTP_HOST']),
            'loaded_classes_count' => count(get_declared_classes()),
            'app_classes' => $this->getAppClasses()
        ];
        
        // Verificar se estamos em ambiente web ou CLI
        $context = $envInfo['is_cli'] ? 'CLI' : 'Web';
        
        $this->addResult(
            'environment_context',
            'success',
            "Executando em contexto: {$context}",
            $envInfo,
            null
        );
        
        // Verificar se arquivos críticos existem
        $criticalFiles = [
            'vendor/autoload.php' => $envInfo['autoload_path'],
            'config.php' => $envInfo['config_path']
        ];
        
        foreach ($criticalFiles as $name => $path) {
            $exists = file_exists($path);
            $this->addResult(
                "critical_file_{$name}",
                $exists ? 'success' : 'error',
                $exists ? "Arquivo {$name} encontrado" : "Arquivo {$name} não encontrado",
                ['path' => $path, 'exists' => $exists],
                !$exists ? "Verifique se o arquivo {$name} existe no local correto" : null
            );
        }
        
        return $envInfo;
    }
    
    public function checkComposerConfiguration(): bool
    {
        $composerPath = $this->getProjectRoot() . '/composer.json';
        
        if (!file_exists($composerPath)) {
            $this->addResult(
                'composer_config',
                'error',
                'Arquivo composer.json não encontrado',
                ['path' => $composerPath],
                'Execute "composer init" para criar um arquivo composer.json'
            );
            return false;
        }
        
        $composerContent = file_get_contents($composerPath);
        $composerData = json_decode($composerContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addResult(
                'composer_config',
                'error',
                'Arquivo composer.json contém JSON inválido: ' . json_last_error_msg(),
                ['path' => $composerPath],
                'Corrija a sintaxe JSON no arquivo composer.json'
            );
            return false;
        }
        
        $hasAutoload = isset($composerData['autoload']['psr-4']['App\\']);
        $autoloadPath = $hasAutoload ? $composerData['autoload']['psr-4']['App\\'] : null;
        
        $this->addResult(
            'composer_config',
            $hasAutoload ? 'success' : 'error',
            $hasAutoload 
                ? "Configuração PSR-4 encontrada: App\\ -> {$autoloadPath}"
                : 'Configuração PSR-4 para namespace App\\ não encontrada',
            [
                'path' => $composerPath,
                'has_autoload' => $hasAutoload,
                'autoload_path' => $autoloadPath,
                'full_config' => $composerData['autoload'] ?? null
            ],
            !$hasAutoload ? 'Adicione a configuração "autoload": {"psr-4": {"App\\\\": "src/"}} no composer.json' : null
        );
        
        return $hasAutoload;
    }
    
    public function checkFilePermissions(): array
    {
        $filesToCheck = [
            'vendor/autoload.php',
            'src/Bia.php',
            'src/Pine.php',
            'config.php'
        ];
        
        $permissions = [];
        
        foreach ($filesToCheck as $file) {
            $fullPath = $this->getProjectRoot() . '/' . $file;
            
            if (file_exists($fullPath)) {
                $perms = fileperms($fullPath);
                $readable = is_readable($fullPath);
                $writable = is_writable($fullPath);
                
                $permissions[$file] = [
                    'exists' => true,
                    'permissions' => substr(sprintf('%o', $perms), -4),
                    'readable' => $readable,
                    'writable' => $writable,
                    'owner' => function_exists('posix_getpwuid') && function_exists('fileowner') 
                        ? posix_getpwuid(fileowner($fullPath))['name'] ?? 'unknown'
                        : 'unknown'
                ];
                
                $status = $readable ? 'success' : 'error';
                $message = $readable 
                    ? "Arquivo {$file} tem permissões adequadas"
                    : "Arquivo {$file} não é legível";
                
                $this->addResult(
                    "permissions_{$file}",
                    $status,
                    $message,
                    $permissions[$file],
                    !$readable ? "Execute 'chmod 644 {$fullPath}' para corrigir permissões" : null
                );
            } else {
                $permissions[$file] = [
                    'exists' => false,
                    'permissions' => null,
                    'readable' => false,
                    'writable' => false,
                    'owner' => null
                ];
                
                $this->addResult(
                    "permissions_{$file}",
                    'error',
                    "Arquivo {$file} não existe",
                    $permissions[$file],
                    "Verifique se o arquivo {$file} foi criado corretamente"
                );
            }
        }
        
        return $permissions;
    }
    
    public function generateReport(): string
    {
        $report = "=== RELATÓRIO DE DIAGNÓSTICO DO AUTOLOADER ===\n\n";
        $report .= "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
        $report .= "Contexto: " . (php_sapi_name() === 'cli' ? 'CLI' : 'Web') . "\n\n";
        
        $successCount = 0;
        $warningCount = 0;
        $errorCount = 0;
        
        foreach ($this->diagnosticResults as $result) {
            switch ($result['status']) {
                case 'success':
                    $successCount++;
                    break;
                case 'warning':
                    $warningCount++;
                    break;
                case 'error':
                    $errorCount++;
                    break;
            }
        }
        
        $report .= "RESUMO:\n";
        $report .= "✓ Sucessos: {$successCount}\n";
        $report .= "⚠ Avisos: {$warningCount}\n";
        $report .= "✗ Erros: {$errorCount}\n\n";
        
        $report .= "DETALHES:\n\n";
        
        foreach ($this->diagnosticResults as $result) {
            $icon = match($result['status']) {
                'success' => '✓',
                'warning' => '⚠',
                'error' => '✗',
                default => '?'
            };
            
            $report .= "{$icon} {$result['message']}\n";
            
            if ($result['solution']) {
                $report .= "   Solução: {$result['solution']}\n";
            }
            
            $report .= "\n";
        }
        
        return $report;
    }
    
    private function addResult(string $key, string $status, string $message, array $details = [], ?string $solution = null): void
    {
        $this->diagnosticResults[$key] = [
            'status' => $status,
            'message' => $message,
            'details' => $details,
            'solution' => $solution,
            'timestamp' => time()
        ];
    }
    
    private function describeAutoloaders(array $autoloaders): array
    {
        $descriptions = [];
        
        foreach ($autoloaders as $i => $autoloader) {
            if (is_array($autoloader) && isset($autoloader[0])) {
                $descriptions[] = get_class($autoloader[0]) . '::' . ($autoloader[1] ?? 'unknown');
            } elseif (is_string($autoloader)) {
                $descriptions[] = $autoloader;
            } else {
                $descriptions[] = 'Closure or unknown';
            }
        }
        
        return $descriptions;
    }
    
    private function getAppClasses(): array
    {
        $loadedClasses = get_declared_classes();
        return array_filter($loadedClasses, function($class) {
            return strpos($class, 'App\\') === 0;
        });
    }
    
    private function getProjectRoot(): string
    {
        // Tentar diferentes métodos para encontrar a raiz do projeto
        $currentDir = __DIR__;
        
        // Método 1: Procurar pelo composer.json subindo na hierarquia
        $dir = $currentDir;
        while ($dir !== dirname($dir)) {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }
        
        // Método 2: Assumir que estamos em src/ e a raiz é um nível acima
        if (basename($currentDir) === 'src') {
            return dirname($currentDir);
        }
        
        // Método 3: Usar o diretório pai do atual
        return dirname($currentDir);
    }
}