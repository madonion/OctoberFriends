<?php

/**
 * Provide custom routes outside of what october provides
 *
 * @package DMA\Friends
 * @author Kristen Arnold, Carlos Arroyo
 */
Route::get('logout', function()
{
    Auth::logout();
    return Redirect::to('/');
});

Route::get('location/barcode-login', function() {
	return DMA\Friends\Controllers\Locations::barcodeLogin();
});

Route::group(['prefix'=>'webhooks'], function(){
	// Implement here each individual webhook
	Route::post('twilio/sms', function()
	{
		$request = Input::all();
		$ch = Postman::getChannelInstance('sms');
		return $ch->webhook($request);
	});
});



Route::group(['prefix' => 'friends/api', 'namespace' => 'DMA\Friends\Api'], function() {

	Route::resource('activity', 				'ActivityResource');
	Route::resource('activity-log', 			'ActivityLogResource');
    Route::resource('category',                 'Category');
	Route::resource('badge',		 			'BadgeResource');
	Route::resource('location',		 			'LocationResource');
	Route::resource('reward',		 			'RewardResource');
	Route::resource('step',			 			'StepResource');

});