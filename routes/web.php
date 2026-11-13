<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $adminHost = config('platform.admin_subdomain').'.'.config('platform.base_domain');

    return redirect($request->getHost() === $adminHost ? '/platform' : '/portal');
});
