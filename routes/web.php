<?php

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

Route::get('/', 'IndexController@index');

Route::group(['prefix' => 'chromerivals'], function () {
    Route::view('/charts/fame-activity', 'cr_charts.fameactivity'); // TODO: Cache-Control? -> middleware
    Route::view('/charts/internal/brig-activity', 'cr_charts.brigactivity');
    Route::get('/goto/killsLast24', 'Ranking\CRController@gotoLast24h');
});
