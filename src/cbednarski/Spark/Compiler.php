<?php

namespace cbednarski\Spark;

use cbednarski\Spark\FileUtils;
use Symfony\Component\Console\Output\OutputInterface;

class Compiler
{
    private $cache;
    private $twig;
    private $loader;
    private $output;
    private $plugins;

    public function __construct(Config $config)
    {
        $this->config = $config;

        $twig_paths = FileUtils::filterExists(
            array(
                $this->config->getFullPath('pages'),
                $this->config->getFullPath('layouts')
            )
        );

        $this->loader = new \Twig_Loader_Filesystem($twig_paths);

        $this->twig = new \Twig_Environment($this->loader, array(
            'auto_reload' => true,
            'autoescape' => false,
            'cache' => false,
            'debug' => false,
            'optimizations' => -1,
            'strict_variables' => false
        ));

        $this->plugins = array();
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function println($message)
    {
        if ($this->output) {
            $this->output->writeln($message);
        }
    }

    public static function mergeDefaultParams($params)
    {
        $temp = array('assets' => '/assets');

        return array_merge($temp, $params);
    }

    public function compile($source, $target, $params = array())
    {
        $render = $this->twig->render($source, static::mergeDefaultParams($params));
        file_put_contents($target, $render);
    }

    public function build()
    {
        $page_path = $this->config->getFullPath('pages');

        foreach (FileUtils::listFilesInDir($page_path) as $file) {
            // Calculate target filename
            $filename = FileUtils::pathDiff($page_path, $file, true);

            $target = FileUtils::removeTwigExtension($this->config->getFullPath('target') . $filename);
            $this->println(' Building ' . $filename);
            // Make sure parent folder for target exists
            $parent_dir = pathinfo($target, PATHINFO_DIRNAME);
            FileUtils::mkdirIfNotExists($parent_dir);

            // Compile or copy if it's not a template
            if (pathinfo($file, PATHINFO_EXTENSION) === 'twig') {
                try {
                    $this->compile($filename, $target);
                } catch (\Exception $e) {
                    echo 'Error while processing ' . $filename;
                    throw $e;
                }

            } else {
                copy($file, $target);
            }
        }

        $this->copyAssets();

        //Run custom plugins after build
        $this->loadPluginFiles();
        $this->runPlugins();

    }

    public function copyAssets()
    {
        $assets_path = $this->config->getFullPath('assets');

        foreach (FileUtils::listFilesInDir($assets_path) as $file) {
            $filename = FileUtils::pathDiff($assets_path, $file, true);

            $target = $this->config->getFullPath('target') . 'assets/' . $filename;
            $parent_dir = pathinfo($target, PATHINFO_DIRNAME);
            FileUtils::mkdirIfNotExists($parent_dir);

            $this->println(' Copying assets/' . $filename);
            copy($file, $target);
        }
    }

    public function watch()
    {

    }

    private function loadPluginFiles()
    {
        $plugin_files = FileUtils::listFilesInDir($this->config->getFullPath('plugins'));
        $spark = $this;
        foreach ($plugin_files as $plugin_file) {
            require_once($plugin_file);
        }
    }

    public function addPlugins($name, $plugin)
    {
        $this->plugins[$name] = $plugin;
    }

    public function runPlugins()
    {
        foreach ($this->plugins as $plugin) {
            $plugin();
        }
    }
}
