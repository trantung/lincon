<?php
Route::any('/api/search', 'ApiController@search');
Route::get('/api/detail/{id}', 'ApiController@detail');
/*
Route::when('/api/*', function() {
	if (Request::server('REMOTE_IP') != Config::get('oya.host')) {
		// Forbid access
		Log::warning('Illegal access', $_SERVER);
		App::abort(401,'You are not authorized.');
	}
});
*/