<?php
/**
 * @package 搜外内容管家
 * @version 1.0.0
 */

require_once dirname(__FILE__) . '/../../../wp-load.php';
require_once plugin_dir_path(__FILE__) . DIRECTORY_SEPARATOR . 'guanjia.class.php';

$guanjia = new Guanjia();

$_GET['a'] = parseAction(isset($_GET['a']) ? $_GET['a'] : '');

if (!in_array($_GET['a'], array(
    'client',
    'categories',
    'publish',
    'upgrade',
))) {
    return $guanjia->res(-1, "访问受限");
}

// 执行
$guanjia->run();

function parseAction($a)
{
    $a = lcfirst(str_replace(" ", "", ucwords(str_replace(array("/", "_"), " ", $a))));
    return $a;
}