<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/cms/{page}', 'CmsController@pageAction')->where('page', '\w+');

Route::group(['prefix' => 'ranking'], function () {
    Route::get('/highscore', 'Ranking\HighscoreController@get');
});
