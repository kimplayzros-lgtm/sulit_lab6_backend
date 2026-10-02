<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 *
 * Copyright (c) 2020 Ronald M. Marasigan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @since Version 1
 * @link https://github.com/ronmarasigan/LavaLust
 * @license https://opensource.org/licenses/MIT MIT License
 */

/*
| -------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------
| Here is where you can register web routes for your application.
|
|
*/
/** @var object $router **/

$router->get('/', 'Welcome::index');

$router->get('/api/health', 'Auth::health');
$router->post('/api/auth/login', 'Auth::login');
$router->post('/api/auth/register', 'Auth::register');
$router->post('/api/auth/logout', 'Auth::logout');
$router->options('/api/auth/login', 'Auth::preflight');
$router->options('/api/auth/register', 'Auth::preflight');
$router->options('/api/auth/logout', 'Auth::preflight');

$router->post('/create', 'Auth::register');
$router->post('/login', 'Auth::login');
$router->post('/logout', 'Auth::logout');
$router->post('/refresh', 'Auth::refresh');
$router->get('/me', 'Auth::me');
$router->get('/users', 'Auth::users');
$router->put('/users/{id}', 'Auth::update_user');
$router->delete('/users/{id}', 'Auth::delete_user');
$router->options('/create', 'Auth::preflight');
$router->options('/login', 'Auth::preflight');
$router->options('/logout', 'Auth::preflight');
$router->options('/refresh', 'Auth::preflight');
$router->options('/me', 'Auth::preflight');
$router->options('/users', 'Auth::preflight');
$router->options('/users/{id}', 'Auth::preflight');

$router->get('/api/products', 'Products::index');
$router->post('/api/products', 'Products::store');
$router->get('/api/products/{id}', 'Products::show');
$router->put('/api/products/{id}', 'Products::update');
$router->patch('/api/products/{id}', 'Products::update');
$router->delete('/api/products/{id}', 'Products::destroy');
$router->options('/api/products', 'Products::preflight');
$router->options('/api/products/{id}', 'Products::preflight');