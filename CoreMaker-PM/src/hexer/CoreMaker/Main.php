<?php

namespace hexer\CoreMaker;

use hexer\CoreMaker\DirectoryResourceProvider;
use pocketmine\thread\ThreadSafeClassLoader;
use pocketmine\permission\PermissionManager;
use pocketmine\plugin\PluginDescription;
use pocketmine\utils\TextFormat as TF;
use pocketmine\permission\Permission;
use pocketmine\plugin\PluginBase;
use Symfony\Component\Yaml\Yaml;
use DevTools\FolderPluginLoader;
use pocketmine\utils\Config;
use pocketmine\Server;
use ReflectionClass;

class Main extends PluginBase {

    private Config $config;

    public function onEnable(): void {
        $this->getLogger()->info(TF::GREEN . "CoreMaker Modular started. Scanning modules recursively...");
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $this->config = $this->getConfig();

        $basePath = $this->getDataFolder() . "plugins/";
        if (!is_dir($basePath)) {
            @mkdir($basePath, 0777, true);
        }

        $this->scanAndLoadPlugins($basePath);
    }

    private function scanAndLoadPlugins(string $dir): void {
        $queue = [];
        $dependencies = [];

        foreach (scandir($dir) as $entry) {
            if ($entry === "." || $entry === "..") continue;

            $path = $dir . $entry;

            if (is_dir($path)) {
                if (file_exists($path . "/plugin.yml") && is_dir($path . "/src")) {
                    try {
                        $yamlData = yaml_parse_file($path . "/plugin.yml");
                        $pluginName = $yamlData["name"] ?? basename($path);
                        $deps = $yamlData["depend"] ?? [];
                        $queue[$pluginName] = $path;
                        $dependencies[$pluginName] = $deps;
                    } catch (\Throwable $e) {
                        if($this->config->getNested("logs.errors", true)){
                            $this->getLogger()->error("Error parsing plugin.yml in $entry: " . $e->getMessage());
                        }
                    }
                } else {
                    $this->scanAndLoadPlugins($path . "/");
                }
            }
        }

        $loaded = [];

        while (count($queue) > 0) {
            $progress = false;

            foreach ($queue as $name => $path) {
                $deps = $dependencies[$name];
                $depsOk = true;

                foreach ($deps as $dep) {
                    if (!isset($loaded[$dep]) && Server::getInstance()->getPluginManager()->getPlugin($dep) === null) {
                        $depsOk = false;
                        break;
                    }
                }

                if ($depsOk) {
                    $this->loadPluginManually($path);
                    $loaded[$name] = true;
                    unset($queue[$name]);
                    $progress = true;
                }
            }

            if (!$progress) {
                foreach ($queue as $name => $path) {
                    if($this->config->getNested("logs.errors", true)){
                        $this->getLogger()->error("Failed to load $name: missing dependencies: " . implode(", ", $dependencies[$name]));
                    }
                }
                break;
            }
        }
    }

    private function loadPluginManually(string $pluginPath): void {
        $pluginYml = $pluginPath . "/plugin.yml";
        try {
            $yamlData = yaml_parse_file($pluginYml);    
            if (isset($yamlData["permissions"])) {
                foreach ($yamlData["permissions"] as $name => $info) {
                    $desc = is_array($info) && isset($info["description"]) ? $info["description"] : "Permission registered by CoreMaker";
                    PermissionManager::getInstance()->addPermission(new Permission($name, $desc));

                    if($this->config->getNested("logs.permissions_registered", true)){
                        $this->getLogger()->info("Registered permission: $name");
                    }
                }
            }
        } catch (\Throwable $e) {
            if($this->config->getNested("logs.errors", true)){
                $this->getLogger()->error("Error parsing plugin.yml in " . basename($pluginPath) . ": " . $e->getMessage());
            }
        }

        $srcPath = $pluginPath . "/src/";

        if (!file_exists($pluginYml) || !is_dir($srcPath)) {
            $this->getLogger()->warning("Invalid structure for the plugin: " . basename($pluginPath));
            return;
        }

        $description = new PluginDescription(file_get_contents($pluginYml));
        $namespace = $description->getSrcNamespacePrefix();
        $mainClass = $description->getMain();

        $classLoader = Server::getInstance()->getLoader();
        if ($classLoader instanceof ThreadSafeClassLoader) {
            $classLoader->addPath($namespace, $srcPath);
            if($this->config->getNested("logs.namespace_registered", true)){
                $this->getLogger()->info("Namespace registered: $namespace -> $srcPath");
            }

            try {
                if (!class_exists($mainClass)) {
                    $this->getLogger()->error("Main class not found: $mainClass");
                    return;
                }

                $reflection = new \ReflectionClass($mainClass);
                $pluginInstance = $reflection->newInstanceWithoutConstructor();

                $loader = new FolderPluginLoader(Server::getInstance()->getLoader());
                $resourceProvider = new DirectoryResourceProvider($pluginPath);

                $dataFolder = Server::getInstance()->getDataPath() . "plugin_data/" . $description->getName() . "/";
                @mkdir($dataFolder, 0777, true);

                $pluginInstance = new $mainClass(
                    $loader,
                    Server::getInstance(),
                    $description,
                    $dataFolder,
                    $pluginPath,
                    $resourceProvider
                );

                $pluginInstance->onLoad();

                $pluginManager = Server::getInstance()->getPluginManager();
                $pmReflection = new \ReflectionClass($pluginManager);
                $pluginsProperty = $pmReflection->getProperty("plugins");
                $pluginsProperty->setAccessible(true);

                $plugins = $pluginsProperty->getValue($pluginManager);
                $plugins[$pluginInstance->getDescription()->getName()] = $pluginInstance;
                $pluginsProperty->setValue($pluginManager, $plugins);

                $reflection = new \ReflectionClass($pluginInstance);
                $method = $reflection->getMethod("onEnableStateChange");
                $method->setAccessible(true);
                $method->invoke($pluginInstance, true);

                if($this->config->getNested("logs.modules_loaded", true)){
                    $this->getLogger()->info(TF::AQUA . "Module loaded: " . $pluginInstance->getName());
                }

            } catch (\Throwable $e) {
                if($this->config->getNested("logs.errors", true)){
                    $this->getLogger()->error("Manual loading error of " . basename($pluginPath) . ": " . $e->getMessage());
                }
            }

        } else {
            if($this->config->getNested("logs.errors", true)){
                $this->getLogger()->error("Incompatible ClassLoader.");
            }
        }
    }
}
