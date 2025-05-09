<?php

class Pajax
{

    static $options;
    static $data;
    static $ajax = false;
    static $class;

    static function app($class, $options = [])
    {
        self::$class = new $class();
        self::$options = $options;
        self::processAjax();
        self::render();

    }

    static function processAjax()
    {
        if (isset($_SERVER['HTTP_P_AJAX'])) {
            $pAjax = json_decode(base64_decode($_SERVER['HTTP_P_AJAX']), true);
            $data = json_decode(base64_decode($_POST['p-form-data']), true);
            $class = $pAjax['class'];
            $action = $pAjax['action'];
            $actionData = $pAjax['actionData'];
            self::$ajax = true;
            self::$class = new $class();
            foreach($data as $k => $v) {
                if(property_exists(self::$class, $k)) {
                    self::$class->$k = $v;
                }
            }
            foreach($_POST as $k => $v) {
                if(property_exists(self::$class, $k)) {
                    self::$class->$k = $v;
                }
            }
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
            echo json_encode(['content' => $content, 'data' => $data]);
            exit;
        }
    }

    static function render($form=true)
    {
        $view = self::getViewId();
        ?>
        <form method="post" action="" id="<?= $view ?>" data-p-view="<?= $view ?>" autocomplete="off">
            <?php call_user_func(self::getTemplate()) ?>
            <input type="hidden" name="p-view" value="<?= $view ?>"/>
            <input type="hidden" name="p-class" value="<?= get_class(self::$class) ?>"/>
            <input type="hidden" name="p-form-data" value="<?= base64_encode(json_encode(self::getData())) ?>"/>
        </form>
        <?php
    }

    static function getViewId() {
        if(self::$ajax) {
            return $_POST['p-view'];
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

