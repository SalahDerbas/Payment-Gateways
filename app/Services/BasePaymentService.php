<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class BasePaymentService
{

    /**
     * Sends an HTTP request to the payment gateway API.
     *
     * @param string $method The HTTP method (e.g., GET, POST, PUT).
     * @param string $url The endpoint URL (relative to the base URL).
     * @param mixed $data The data to send in the request (can be JSON or other formats).
     * @param string $type The type of data to send (default is 'json').
     * @return \Illuminate\Http\JsonResponse The JSON response from the API call.
     */
    protected string $base_url;
    protected array  $header;
    protected function buildRequest($method, $url, $data = null,$type='json'): \Illuminate\Http\JsonResponse
    {
        try {
            $fullURL = $this->base_url . $url;

            $response = Http::withHeaders($this->header)->send($method, $fullURL, [$type => $data] );

            return response()->json([
                'success'   =>  $response->successful(),
                'status'    =>  $response->status(),
                'data'      =>  $response->json(),
            ],  $response->status() );

        } catch (Exception $e) {

            return response()->json([
                'success'   =>  false,
                'status'    =>  500,
                'message'   =>  $e->getMessage(),
            ], 500);
        }
    }

}
