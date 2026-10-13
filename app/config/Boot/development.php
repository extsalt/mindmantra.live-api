<?php

/*
 |--------------------------------------------------------------------------
 | ERROR DISPLAY
 |--------------------------------------------------------------------------
 | In development, we want to show as many errors as possible to help
 | make sure they don't make it to production. And save us hours of
 | painful debugging.
 |
 | If you set 'display_errors' to '1', CI4's detailed error report will show.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

defined('SHOW_DEBUG_BACKTRACE') || define('SHOW_DEBUG_BACKTRACE', false);
defined('CI_DEBUG') || define('CI_DEBUG', false);
