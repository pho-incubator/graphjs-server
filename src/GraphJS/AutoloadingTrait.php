<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphJS;

use Composer\Autoload\ClassLoader;
use Pho\Kernel\Kernel;
use PhoNetworksAutogenerated\User;

/**
 * Deals with autoloading
 * 
 * for both Grou.ps and GraphJS setups
 * 
 * Note: APP_ROOT must be defined.
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
trait AutoloadingTrait
{

    protected static $INDEX_PREFIX = 'Pho\\Kernel\\Services\\Index\\Adapters\\';
    protected static $RECIPE_PREFIX = 'PhoNetworksAutogenerated\\';

    protected function loadEnvVars(string $configs_file): void
    {
        if($this->heroku) {
            include dirname(dirname(__DIR__)) . '/inc/heroku.php';
        }
        else {
            //$configs_file = $this->configs_file;
            if(empty($configs_file)) {
                $configs_file = dirname(dirname(__DIR__)).'/';
            }
            $dotenv = new \Dotenv\Dotenv($configs_file);
            $dotenv->load();
        }
        $this->configureAutoloading();
    }

    protected function configureAutoloading(): void
    {
        $installationType = getenv('INSTALLATION_TYPE');
        $indexType = getenv('INDEX_TYPE');

        spl_autoload_register(function ($class) use ($installationType, $indexType) {
            return $this->autoload($installationType, $indexType, $class);
        }, true, true);

        error_log(var_export((new \ReflectionClass(\Pho\Kernel\Services\Index\Adapters\QueryResult::class))->getFileName()));
        error_log(var_export((new \ReflectionClass(User::class))->getFileName()));
    }

    public function autoload(string $installationType, string $indexType, string $class): bool
    {
        $indexRes = $installationRes = false;
        $installationType = strtolower($installationType);
        $indexType = strtolower($indexType);

        if($this->isRecipeClass($class)) {
            if ($installationType === 'groupsv2') {
                $installationRes = $this->useRecipeNetwork($class);
            }
            else {
                $installationRes = $this->useRecipeWeb($class);
            }
        }
        elseif($this->isIndexClass($class)) {
            if ($indexType === 'redis') {
                $indexRes = $this->useIndexRedis($class);
            } 
            else {
                $indexRes = $this->useIndexNeo4j($class);
            }
        }

        return $indexRes || $installationRes;
    }

    private function isIndexClass(string $class): bool
    {
        return (strpos($class, static::$INDEX_PREFIX) === 0);
    }

    private function isRecipeClass(string $class): bool
    {
        return (strpos($class, static::$RECIPE_PREFIX) === 0);
    }

    private function loadClass(string $class, string $classPrefix, string $filePath): bool
    {
        //if (strpos($class, $classPrefix) === 0) {
            $nonPrefixed = str_replace($classPrefix, '', $class);
            $nonPrefixed = str_replace('\\', '/', $nonPrefixed);
            $includeFile = $filePath . "/{$nonPrefixed}.php";
            if (file_exists($includeFile)) {
                include $includeFile;
                return true;
            }
        //}
        return false;
    }

    private function useIndexNeo4j(string $class): bool
    {
        return $this->loadClass($class, static::$INDEX_PREFIX, APP_ROOT . '/vendor/pho-adapters/index-neo4j');
    }

    private function useIndexRedis(string $class): bool
    {
        return $this->loadClass($class, static::$INDEX_PREFIX, APP_ROOT . '/vendor/pho-adapters/index-redis');
    }

    private function useRecipeWeb(string $class): bool
    {
        return $this->loadClass($class, static::$RECIPE_PREFIX, APP_ROOT . '/vendor/pho-recipes/web/.compiled');
    }

    private function useRecipeNetwork(string $class): bool
    {
        return $this->loadClass($class, static::$RECIPE_PREFIX, APP_ROOT . '/vendor/pho-recipes/network/.compiled');
    }

}
