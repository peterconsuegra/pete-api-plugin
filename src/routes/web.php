<?php


//Route::get('test_demo', 'Pete\PeteApi\Http\PeteApiController@test_demo');
Route::get('test_demo', 'Pete\PeteApi\Http\PeteApiController@test_demo')->middleware(['web']);
