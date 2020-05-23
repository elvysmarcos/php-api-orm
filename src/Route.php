<?php
/**
 * Created by PhpStorm.
 * User: elvys.marcos
 * Date: 19/08/2018
 * Time: 22:37
 */

namespace Core;

use ReflectionFunction;

class Route
{
    private $path = null;
    private $route = array();

    function __construct()
    {
        $route = isset($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : null;
        $route = ltrim($route, '/');
        $route = rtrim($route, '/');

        $routers = explode('/', $route);

        $path = null;
        $lastPath = null;
        $empty = true;

        if (count($routers)) {
            $path = PATH_ROOT . 'Controller/';

            foreach ($routers as $key => $value) {

                $currentConfig = explode('-', $value);

                $current = null;

                if (count($currentConfig)) {
                    foreach ($currentConfig as $keyTemp => $valueTemp) {
                        $current .= ucfirst($valueTemp);
                    }
                }

                if ($empty && $current && is_dir($path . $current)) {

                    $path .= $current . '/';
                    $lastPath = $current;

                } else if ($empty && $current && file_exists($path . $current . 'Controller.php')) {

                    $path .= $current . 'Controller.php';
                    $empty = false;

                } else {

                    $this->route[] = $value;

                }
            }

            if (is_dir($path) && $lastPath && is_file($path . $lastPath . 'Controller.php')) {
                $path .= $lastPath . 'Controller.php';
            }
        }

        $this->path = $path;
    }

    private function GetRouteArgs($route, $execute): array
    {
        $routes = [];

        if ($route) {
            $route = ltrim($route, '/');
            $route = rtrim($route, '/');
            $routes = explode('/', $route);
        }

        $args = array();
        $verifiedRoutes = array();
        $argsDefaultResult = array();
        $argsResult = array();

        $very = true;

        if (count($routes) == count($this->route)) {

            try {
                $fx = new ReflectionFunction($execute);
            } catch (\ReflectionException $e) {
                new ApiCustomException($e->getMessage());
            }

            $setArg = $fx->getParameters();

            if (count($setArg)) {
                foreach ($setArg as $key => $value) {
                    $nameArg = $value->getName();
                    $arg = '$' . $nameArg;
                    $defaultValue = null;

                    if ($value->isDefaultValueAvailable()) {
                        try {
                            $defaultValue = $value->getDefaultValue();
                            $argsDefaultResult[$nameArg] = $defaultValue;
                        } catch (\ReflectionException $e) {
                            new ApiCustomException($e->getMessage());
                        }
                    }

                    $args[$arg] = $defaultValue;
                }
            }

            if (count($routes)) {
                foreach ($routes as $key => $value) {

                    $start = substr($value, 0, 1);

                    if ($start === '$') {

                        if (key_exists($value, $args)) {
                            $args[$value] = $this->route[$key];
                        };

                        $verifiedRoutes[] = $this->route[$key];

                    } else if ($value === $this->route[$key]) {

                        $verifiedRoutes[] = $this->route[$key];
                    }
                }
            }

            if (count($args)) {

                $postArg = $_POST;
                $countPostArg = is_array($postArg) ? count($postArg) : 0;
                $getArgs = $_GET;
                $countGetArg = is_array($getArgs) ? count($getArgs) : 0;

                foreach ($args as $key => $value) {
                    $fullKeyName = str_replace('$', '', $key);

                    if ($countPostArg && key_exists($fullKeyName, $postArg)) {
                        $postValue = $this->GetTypeValue($postArg[$fullKeyName]);
                        $args[$key] = $postValue;
                        $argsResult[] = $postValue;
                    } else if ($countGetArg && key_exists($fullKeyName, $getArgs)) {
                        $getValue = $this->GetTypeValue($getArgs[$fullKeyName]);
                        $args[$key] = $getValue;
                        $argsResult[] = $getValue;
                    } else if ($value !== null && !is_array($value)) {
                        $argsResult[] = $this->GetTypeValue($value);
                    } else if ($value !== null && is_array($value)) {
                        $argsResult[] = $this->GetTypeValue($postArg);
                    } else if (key_exists($fullKeyName, $argsDefaultResult)) {
                        $argsResult[] = $this->GetTypeValue($argsDefaultResult[$fullKeyName]);
                    }
                }
            }

        } else {
            $very = false;
        }

        return array($very, $verifiedRoutes, $args, $argsResult);
    }

    private function GetTypeValue($value)
    {
        if ($value === 'null' || $value === 'undefined') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT);

        if ($int !== false) {
            return $int;
        }

        $double = floatval($value);

        if ($double && $int !== $double) {
            return $double;
        }

        $lower = strtolower($value);

        if ($lower === 'true' || $lower === 'false') {
            return ($lower === 'true');
        }

        return $value;
    }

    function Controller()
    {
        if (is_file($this->path)) {
            include($this->path);
        }
    }

    function Route($method, $route, $execute, ISession $auth = null)
    {
        if ($method == METHOD) {
            try {
                list($very, $verifiedRoutes, $args, $argsResult) = $this->GetRouteArgs($route, $execute);
            } catch (ApiCustomException $e) {
                new ApiCustomException('Erro ao recuperar dados da rota');
            }

            if ($very && count($this->route) === count($verifiedRoutes) && count($args) === count($argsResult)) {

                if (gettype($auth) === 'object') {
                    $Security = new Security();
                    $Security->AuthenticatedRegion($auth);
                }

                call_user_func_array($execute, $argsResult);
            }
        }
    }
}
