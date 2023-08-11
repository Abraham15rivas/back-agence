<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| Api Responser Trait
|--------------------------------------------------------------------------
|
| This trait will be used for any response we sent to clients.
|
*/

trait Response
{
	/**
     * Return a success JSON response.
     *
     * @param  array|string  $data
     * @param  string  $message
     * @param  int|null  $code
     * @return \Illuminate\Http\JsonResponse
     */
	protected function success (string $message = null, $data = null, int $code = 200) {
		return response()->json([
			'success' 	=> true,
			'message' 	=> $message,
			'data' 		=> $data
		], $code);
	}

	/**
     * Return an error JSON response.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  array|string|null  $data
     * @return \Illuminate\Http\JsonResponse
     */
	protected function error (string $message = null, $data = null, int $code) {
		return response()->json([
			'success' 	=> false,
			'message' 	=> $message,
			'data' 		=> $data
		], $code);
	}

    public function validator($data, $validationData, $nameController) {
        $customMessages = [
            'required'  => $nameController . 'Validator: :attribute is required.',
            'integer'   => $nameController . 'Validator: :attribute must be an integer.',
            'string'    => $nameController . 'Validator: :attribute must be a string.',
            'boolean'   => $nameController . 'Validator: :attribute must be a boolean.'
        ];
        return Validator::make($data, $validationData, $customMessages);
    }

    public function reportError ($error) {
        Log::info('====================== ERROR ======================');
        Log::info('Date America/Caracas: ' . Carbon::now()->setTimezone('America/Caracas')->format('Y-m-d H:i:s'));
        Log::info('File: ' . $error->getFile());
        Log::info('Message: ' . $error->getMessage());
        Log::info('Line: ' . $error->getLine());
        Log::info('===================================================');
    }
}