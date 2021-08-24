<?php

use Illuminate\Http\Request;
use Merciall\Docket\Docket;

Route::prefix('sms')->group(function () {
    Route::get('receive', function (Request $request) {
        return Docket::SMS($request)->receive();
    });
    Route::get('send', function (Request $request) {
        return Docket::SMS($request)->send();
    });
});
