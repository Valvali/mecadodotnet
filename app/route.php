<?php
/*
	Routes
	
*/
$app->get('/', 'ExempleController:exemple')->setName('index');

$app->get('/userRegister', 'UserController:registerUser')->setName('register');

