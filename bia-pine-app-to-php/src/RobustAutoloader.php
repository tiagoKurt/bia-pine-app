<?php

namespace App;

class RobustAutoloader
{
    private static ?self $instance = null;
    private bool $fallbackRegistered = false;
    private array $classMap = [];
    private string $projectRoot;
    
    public function __construct()
    {
        $this->projectRoot = $this->findProjectRoot();
        $this->initializeClassMap();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function ensureAutoloaderLoaded(): bool
    {
        // Verificar se o Composer autoloader está carregado
        $composerAutoloadPath = $this->projectRoot . '/vendor/autoload.php';
        
        if (!file_exists($composerAutoloadPath)) {
            error_log("RobustAutoloader: Composer autoload file not found at: {$composerAutoloadPath}");
            return false;
        }
        
        // Verificar se já está carregado
        $autoloaders = spl_autoload_functions();
        $composerLoaded = false;
        
        foreach ($autoloaders as $autoloader) {
            if (is_array($autoloader) && isset($autoloader[0])) {
                $className = get_class($autoloader[0]);
                if (strpos($className, 'Composer') !== false) {
                    $composerLoaded = true;
                    break;
                }
            }
        }
        
        if (!$composerLoaded) {
            try {
                require_once $composerAutoloadPath;
                error_log("RobustAutoloader: Composer autoloader loaded successfully");
                return true;
            } catch (Exception $e) {
                error_log("RobustAutoloader: Failed to load Composer autoloader: " . $e->getMessage());
                return false;
            }
        }
        
        return true;
    }
    
    public function loadClassManually(string $className): bool
    {
        // Normalizar o nome da classe
        $className = ltrim($className, '\\');
        
        // Verificar se a classe já está carregada
        if (class_exists($className, false)) {
            return true;
        }
        
        // Tentar carregar usando o mapa de classes
        if (isset($this->classMap[$className])) {
            $filePath = $this->classMap[$className];
            
            if (file_exists($filePath) && is_readable($filePath)) {
                try {
                    require_once $filePath;
                    
                    if (class_exists($className, false)) {
                        error_log("RobustAutoloader: Successfully loaded class {$className} from {$filePath}");
                        return true;
                    } else {
                        error_log("RobustAutoloader: File loaded but class {$className} not found");
                        return false;
                    }
                } catch (Exception $e) {
                    error_log("RobustAutoloader: Error loading class {$className}: " . $e->getMessage());
                    return false;
                }
            }
        }
        
        // Tentar carregar usando convenção PSR-4
        return $this->loadClassByPsr4Convention($className);
    }
    
    public function registerFallbackAutoloader(): void
    {
        if ($this->fallbackRegistered) {
            return;
        }
        
        spl_autoload_register([$this, 'fallbackAutoload'], true, false);
        $this->fallbackRegistered = true;
        error_log("RobustAutoloader: Fallback autoloader registered");
    }
    
    public function validateClassLoading(): array
    {
        $results = [];
        
        foreach ($this->classMap as $className => $filePath) {
            $results[$className] = [
                'file_exists' => file_exists($filePath),
                'file_readable' => file_exists($filePath) && is_readable($filePath),
                'class_loaded' => class_exists($className, false),
                'can_load_manually' => false,
                'file_path' => $filePath
            ];
            
            // Tentar carregamento manual se a classe não estiver carregada
            if (!$results[$className]['class_loaded'] && $results[$className]['file_readable']) {
                $results[$className]['can_load_manually'] = $this->loadClassManually($className);
            }
        }
        
        return $results;
    }
    
    public function repairAutoloader(): array
    {
        $repairs = [];
        
        // 1. Verificar e regenerar autoloader do Composer
        $composerAutoloadPath = $this->projectRoot . '/vendor/autoload.php';
        if (!file_exists($composerAutoloadPath)) {
            $repairs[] = [
                'action' => 'regenerate_composer_autoloader',
                'status' => 'needed',
                'message' => 'Composer autoloader não encontrado, execute: composer dump-autoload'
            ];
        } else {
            $repairs[] = [
                'action' => 'check_composer_autoloader',
                'status' => 'ok',
                'message' => 'Composer autoloader encontrado'
            ];
        }
        
        // 2. Verificar permissões de arquivos
        foreach ($this->classMap as $className => $filePath) {
            if (file_exists($filePath) && !is_readable($filePath)) {
                $repairs[] = [
                    'action' => 'fix_file_permissions',
                    'status' => 'needed',
                    'message' => "Arquivo {$filePath} não é legível",
                    'solution' => "chmod 644 {$filePath}"
                ];
            }
        }
        
        // 3. Registrar fallback autoloader se necessário
        if (!$this->fallbackRegistered) {
            $this->registerFallbackAutoloader();
            $repairs[] = [
                'action' => 'register_fallback',
                'status' => 'applied',
                'message' => 'Fallback autoloader registrado'
            ];
        }
        
        return $repairs;
    }
    
    public function fallbackAutoload(string $className): void
    {
        error_log("RobustAutoloader: Fallback autoload called for class: {$className}");
        $this->loadClassManually($className);
    }
    
    private function loadClassByPsr4Convention(string $className): bool
    {
        // Converter namespace para caminho de arquivo seguindo PSR-4
        if (strpos($className, 'App\\') === 0) {
            $relativePath = str_replace('App\\', '', $className);
            $relativePath = str_replace('\\', '/', $relativePath);
            $filePath = $this->projectRoot . '/src/' . $relativePath . '.php';
            
            if (file_exists($filePath) && is_readable($filePath)) {
                try {
                    require_once $filePath;
                    
                    if (class_exists($className, false)) {
                        error_log("RobustAutoloader: Successfully loaded {$className} via PSR-4 convention");
                        return true;
                    }
                } catch (Exception $e) {
                    error_log("RobustAutoloader: Error loading {$className} via PSR-4: " . $e->getMessage());
                }
            }
        }
        
        return false;
    }
    
    private function initializeClassMap(): void
    {
        // Mapa manual das classes críticas
        $this->classMap = [
            'App\\Bia' => $this->projectRoot . '/src/Bia.php',
            'App\\Pine' => $this->projectRoot . '/src/Pine.php',
            'App\\AutoloaderDiagnostic' => $this->projectRoot . '/src/AutoloaderDiagnostic.php',
            'App\\RobustAutoloader' => $this->projectRoot . '/src/RobustAutoloader.php',
            'App\\ConfigurationValidator' => $this->projectRoot . '/src/ConfigurationValidator.php'
        ];
        
        // Adicionar classes encontradas dinamicamente no diretório src
        $this->scanForClasses();
    }
    
    private function scanForClasses(): void
    {
        $srcDir = $this->projectRoot . '/src';
        
        if (!is_dir($srcDir)) {
            return;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $relativePath = str_replace($srcDir . '/', '', $file->getPathname());
                $relativePath = str_replace('/', '\\', $relativePath);
                $className = 'App\\' . str_replace('.php', '', $relativePath);
                
                if (!isset($this->classMap[$className])) {
                    $this->classMap[$className] = $file->getPathname();
                }
            }
        }
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