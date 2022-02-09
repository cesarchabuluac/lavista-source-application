<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('auth.login');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
Route::get('invoice/generate-pdf', 'InvoiceController@generateZip')->name('generate.multiple.pdf');
Route::resource('invoice', 'InvoiceController');

//Budgets
Route::resource('budgets', 'BudgetController');
