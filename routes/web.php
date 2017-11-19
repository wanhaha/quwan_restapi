<?php

$prefix     = 'quwan';
$namespace  = 'App\Http\Controllers';

//$app->get($prefix, function () {
//    return 'Welcome to quwan';
//});

/**
 * 允许的版本,即当前主版本
 */
$allowVersion = array(1, 2);

$versionAccept = $app->make('request')->header('accept');
$version = getVersion($versionAccept, $allowVersion) ?: '\V1';


//无需用户认证
$unAuthGroup = [
    'prefix'     => $prefix,
    'namespace'  => $namespace.$version,
    'middleware' => ['lang']
];
$app->group($unAuthGroup, function () use ($app) {


    $app->get('/', 'TestController@wx'); //添加文档到索引

    $app->post('add_index', 'TestController@addIndex'); //添加文档到索引
    $app->post('xs', 'TestController@xs'); //迅搜
    $app->get('login', 'TestController@login'); //登录
    $app->post('send_sms', 'TestController@sendSms'); //发送短信
    $app->post('qiniu', 'TestController@qiniu'); //上传到7牛


    $app->get('add_data', 'HomeController@addData'); //增加默认

    $app->get('home', 'HomeController@index'); //首页数据

});


//需要用户认证
$authGroup = [
    'prefix'     => $prefix,
    'namespace'  => $namespace.$version,
    'middleware' => ['lang', 'jwt']
];
$app->group($authGroup, function () use ($app) {
    $app->get('user', 'TestController@user'); //获取用户user_id
    $app->get('logout', 'TestController@logout'); //登出

});
