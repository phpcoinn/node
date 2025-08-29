<?php

class Pajax
{

    const GATEWAY_DAPP = "PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3";

    static $options;
    static $data;
    static $ajax = false;
    static $class;
    static $scripts = [];
    static $process;

    static function app($class, $options = [])
    {
        self::$class = new $class();
        self::$options = $options;
        self::processAjax();
        try {
        self::render();
        } catch (Exception $t) {
            print_r($t);
        }

    }

    static function processAjax()
    {
        if (isset($_SERVER['HTTP_P_AJAX'])) {
            $pAjax = json_decode(base64_decode($_SERVER['HTTP_P_AJAX']), true);
            $viewData = json_decode(base64_decode($pAjax['viewData']), true);
            $class = $pAjax['class'];
            self::$options = json_decode(base64_decode($pAjax['options']), true);
            $action = $pAjax['action'];
            $actionData = $pAjax['actionData'];
            self::$process = @$pAjax['process'];
            if(!class_exists($class)) {
                $class_file = dirname($_SERVER['SCRIPT_FILENAME']) ."/inc/class/$class.php";
                if(file_exists($class_file)) {
                    require $class_file;
                }
            }
            self::$ajax = true;
            self::$class = new $class();
            if(is_array($viewData)) {
                foreach($viewData as $k => $v) {
                    if(property_exists(self::$class, $k)) {
                        self::$class->$k = $v;
                    }
                }
            }
            foreach($_POST as $k => $v) {
                if(property_exists(self::$class, $k)) {
                    self::$class->$k = $v;
                }
            }
            try {
            if(method_exists(self::$class, $action)) {
                if(empty($actionData)) {
                    call_user_func([self::$class, $action]);
                } else {
                    if(!is_array($actionData)) {
                        $actionData = [$actionData];
                    }
                    call_user_func([self::$class, $action], ...$actionData);
                }
            }
            ob_clean();
            ob_start();
                self::render();
                $content = ob_get_contents();
                if(self::$class) {
                    $data = base64_encode(json_encode(self::$class));
                } else {
                    $data = base64_encode(json_encode(self::$data));
                }
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['content' => $content, 'data' => $data, 'scripts' => self::$scripts]);
            } catch (Throwable $t) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode( ['error' => $t->getMessage(), 'details' => $t->getTraceAsString()]);
            }
            exit;
        }
    }

    static function redirect($redirect, $js=true) {
        if($js) {
            header('Content-Type: application/json');
            echo json_encode(['redirect' => $redirect]);
            exit;
        } else {
            header("location: $redirect");
            exit;
        }
    }

    static function executeScript($method, ...$params) {
        self::$scripts[] = ['method'=>$method, 'params'=>$params];
    }

    static function login($appName, $redirect) {
        $request_code = uniqid();
        $_SESSION['request_code'] = $request_code;
        $url = '/dapps.php?url='.self::GATEWAY_DAPP.'/gateway/auth.php?app='.$appName.'&request_code=' . $request_code . '&redirect=' . $redirect;
        self::redirect($url);
    }


    static function logout($redirect) {
        session_destroy();
        self::redirect($redirect);
    }


    static function handleAuth($redirect, $callback = null) {
        if(isset($_GET['auth_data'])) {
            $auth_data = json_decode(base64_decode($_GET['auth_data']), true);
            if ($auth_data['request_code'] == $_SESSION['request_code']) {
                $_SESSION['account'] = $auth_data['account'];
                if($callback!= null && is_callable($callback)) {
                    call_user_func($callback, $auth_data);
                }
                self::redirect($redirect, false);
            }
        }
    }

    static function render()
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (in_array($errno, [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR])) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            }
            error_log("PHP Warning: [$errno] $errstr in $errfile on line $errline");
            return false;
        });
        $view = self::getViewId();
        ob_flush();
        ob_clean();
        ob_start();
        try {
        call_user_func(self::getTemplate());
        } catch (Throwable $t) {
            echo "Caught warning from reflected getTemplate(): " . $t->getMessage().'<br/>';
            echo $t->getFile() .":" . $t->getLine().'<br/>';
            echo $t->getTraceAsString();
        } finally {
            restore_error_handler();
        }
        $body = ob_get_contents();
        ob_clean();
        ob_start();
        ?>
        <div id="<?= $view ?>" data-p-view="<?= $view ?>" data-p-class="<?= get_class(self::$class) ?>"
             data-p-options="<?= base64_encode(json_encode(self::$options)) ?>"
             data-p-view-data="<?= base64_encode(json_encode(self::getData())) ?>" class="<?= self::$options['class'] ?? '' ?>">
            <?php echo $body ?>
        </div>
        <?php
    }

    static function getViewId() {
        if(self::$ajax) {
            $pAjax = json_decode(base64_decode($_SERVER['HTTP_P_AJAX']), true);
            return $pAjax['view'];
        } else {
            if(isset(self::$options['id'])) {
                return self::$options['id'];
            }
        }
        return uniqid();
    }

    static function getTemplate() {
        $class = self::$class;
        $template = (new ReflectionClass($class))->getMethod('getTemplate')->getClosure($class);
        $template = $template->bindTo($class);
        return $template;
    }

    static function getData() {
        $data = self::$class;
        return $data;
    }

    public static function callMethod($name, $param, array $args)
    {
        $class = self::$class;
        if(method_exists($class, $name)) {
            $fn = [$class, $name];
            if(is_callable($fn)) {
                call_user_func($fn, $param, ...$args);
            }
        }
    }

    public static function component($class, $props = []) {
        $classInstance = new $class();
        foreach($props as $k => $v) {
            $classInstance->$k = $v;
        }
        $template = (new ReflectionClass($classInstance))->getMethod('getTemplate')->getClosure($classInstance);
        $template = $template->bindTo($classInstance);
        call_user_func($template);
    }

    public static function block($name, Closure $closure) {
        if(!self::$ajax || (self::$ajax && empty(self::$process) || (!empty(self::$process) && in_array($name, self::$process)))) {
            $class = self::$class;
            $closure->call($class);
        }
    }

}

function pd($name, ...$val)
{

    if(Pajax::$class) {
        $class = Pajax::$class;
        if (!$val) {
            return $class->$name;
        } else {
            $class->$name = $val[0];
        }
    } else {
        if (!$val) {
            return @Pajax::$data[$name];
        } else {
            Pajax::$data[$name] = $val[0];
        }
    }

}

function renderAttr($attrs = [])
{
    $attrList = [];
    foreach ($attrs as $key => $value) {
        if (is_bool($value)) {
            if ($value) $attrList[] = $key;
        } else {
            $attrList[] = "$key=\"$value\"";
        }
    }
    return implode(' ', $attrList);
}

function pinput($name)
{
    return "<input type='text' name='" . $name . "' value='" . pd($name) . "'/>";
}

function paction($action, $label = "Submit", $attrs = [])
{
    return '<button type="button" onclick="a(event,\'' . $action . '\')" ' . renderAttr($attrs) . '>' . $label . '</button>';
}

function pcall($name, ...$args)
{
    Pajax::callMethod($name, ...$args);
}

ob_start();

