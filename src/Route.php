<?php

namespace APIORM;

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
            $path = $_ENV['PATH_ROOT'] . 'Controllers/';

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

            if ($route) {
                $routes = explode('/', $route);
            }
        }

        $args = array();
        $verifiedRoutes = array();
        $argsType = array();
        $argsDefaultResult = array();
        $argsResult = array();

        $very = true;

        $countRoutes = is_array($routes) ? count($routes) : 0;

        if ($countRoutes == count($this->route)) {

            try {
                $fx = new \ReflectionFunction($execute);
            } catch (\ReflectionException $e) {
                new ApiCustomException($e->getMessage());
            }

            $setArg = $fx->getParameters();

            if (count($setArg)) {
                foreach ($setArg as $key => $value) {
                    $argName = $value->getName();

                    $argType = $value->getType();

                    if ($argType) {
                        $argType = $argType->getName();
                    }

                    $argsType[$argName] = $argType;

                    $arg = '$' . $argName;
                    $valueDefault = null;

                    if ($value->isDefaultValueAvailable()) {
                        try {
                            $valueDefault = $value->getDefaultValue();

                            $argsDefaultResult[$argName] = $valueDefault;
                        } catch (\ReflectionException $e) {
                            new ApiCustomException($e->getMessage());
                        }
                    }

                    $args[$arg] = $valueDefault;
                }
            }

            if ($countRoutes) {
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
                    $valueType = gettype($value);

                    if ($countPostArg && key_exists($fullKeyName, $postArg)) {
                        $postValue = $this->GetTypeValue($postArg[$fullKeyName], $argsType[$fullKeyName]);
                        $args[$key] = $postValue;
                        $argsResult[] = $postValue;
                    } else if ($countGetArg && key_exists($fullKeyName, $getArgs)) {
                        $getValue = $this->GetTypeValue($getArgs[$fullKeyName], $argsType[$fullKeyName]);
                        $args[$key] = $getValue;
                        $argsResult[] = $getValue;
                    } else if ($value !== null && $valueType !== 'array') {
                        $argsResult[] = $this->GetTypeValue($value, $argsType[$fullKeyName]);
                    } else if ($value !== null && $valueType === 'array') {
                        $argsResult[] = $this->GetTypeValue($postArg, $argsType[$fullKeyName]);
                    } else if (key_exists($fullKeyName, $argsDefaultResult)) {
                        $argsResult[] = $this->GetTypeValue($argsDefaultResult[$fullKeyName], $argsType[$fullKeyName]);
                    }
                }
            }

        } else {
            $very = false;
        }

        return array($very, $verifiedRoutes, $args, $argsResult);
    }

    private function GetTypeValue($value, $argType)
    {
        if ($value === 'null' || $value === 'undefined') {
            return null;
        }

        if (is_array($value)) {
            $checkClassExist = class_exists($argType);

            if ($checkClassExist && method_exists($argType, 'ImportData')) {
                try {
                    $newInstance = new $argType;
                    $newInstance->ImportData($value);
                    $value = $newInstance;
                } catch (\Exception $e) {
                    new ApiCustomException($e->getMessage());
                }
            }

            return $value;
        }

        switch ($argType) {
            case 'string' :
                return $value;
                break;
            case 'DateTime':
                try {
                    return new \DateTime($value);
                } catch (\Exception $e) {
                    new ApiCustomException($e->getMessage());
                }
                break;
            case 'array' :

                if ($value === null || $value === '') {
                    return [];
                } else if (strpos($value, ',')) {
                    return explode(',', $value);
                }

                return [$value];
                break;
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

    private function Route(string $method, string $route, object $execute, ISession $auth = null)
    {
        if ($method == $_SERVER['REQUEST_METHOD']) {
            try {
                list($very, $verifiedRoutes, $args, $argsResult) = $this->GetRouteArgs($route, $execute);
            } catch (ApiCustomException $e) {
                new ApiCustomException('Erro ao recuperar dados da rota');
            }

            if ($very && count($this->route) === count($verifiedRoutes) && count($args) === count($argsResult)) {

                if (gettype($auth) === 'object') {
                    $Security = new Security($auth::GetPathAuthorization());
                    $Security->AuthenticatedRegion($auth);
                }

                call_user_func_array($execute, $argsResult);
            }
        }
    }

    public function GET(string $route, object $execute, ISession $auth = null)
    {
        $this->Route('GET', $route, $execute, $auth);
    }

    public function POST(string $route, object $execute, ISession $auth = null)
    {
        $this->Route('POST', $route, $execute, $auth);
    }

    public function PUT(string $route, object $execute, ISession $auth = null)
    {
        $this->Route('PUT', $route, $execute, $auth);
    }

    public function DELETE(string $route, object $execute, ISession $auth = null)
    {
        $this->Route('DELETE', $route, $execute, $auth);
    }
}
