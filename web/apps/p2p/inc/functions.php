<?php

function is_mobile() {
    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        return false;
    }

    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    if (strpos($user_agent, 'Mobile') !== false ||
        strpos($user_agent, 'Android') !== false ||
        strpos($user_agent, 'iPhone') !== false ||
        strpos($user_agent, 'iPad') !== false ||
        strpos($user_agent, 'BlackBerry') !== false ||
        strpos($user_agent, 'Opera Mini') !== false ||
        strpos($user_agent, 'Opera Mobi') !== false) {
        return true;
    } else {
        return false;
    }
}

