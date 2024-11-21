<?php

namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymobPaymentService extends BasePaymentService implements PaymentGatewayInterface
{

    /**
     * Constructor to initialize class properties.
     * Sets up the necessary environment variables and prepares the headers for Paymob API requests.
     */
    protected $api_key;
    protected $integrations_id;
    public function __construct()
    {
        $this->base_url        = env("BAYMOB_BASE_URL");
        $this->api_key         = env("BAYMOB_API_KEY");
        $this->integrations_id = [4877813, 4877773];
        $this->header          = [
            'Accept'       =>  'application/json',
            'Content-Type' =>  'application/json',
        ];
    }

    /**
     * Generate an authentication token to be used in API requests.
     * This method makes a request to Paymob's authentication endpoint to get a token.
     */
    protected function generateToken()
    {
        $data     = ['api_key' => $this->api_key];

        $response = $this->buildRequest('POST', '/api/auth/tokens', $data);

        return $response->getData(true)['data']['token'];
    }

    /**
     * Send a payment request to Paymob.
     * This method formats the payment data, sends the request, and returns the URL for the payment if successful.
     */
    public function sendPayment(Request $request):array
    {
        $this->header['Authorization'] = 'Bearer ' . $this->generateToken();
        $data                          = $this->formatDataPayment($request);

        $response = $this->buildRequest('POST', '/api/ecommerce/orders', $data);

        if ($response->getData(true)['success'])
            return ['success' => true, 'url' => $response->getData(true)['data']['url']];

        return ['success' => false, 'url' => route('payment.failed')];
    }

    /**
     * Handle the callback from Paymob after the payment attempt.
     * This method checks if the response indicates a successful payment.
     */
    public function callBack(Request $request): bool
    {
        $response = $request->all();

        Storage::put('paymob_response.json',
             json_encode( $response )
        );

        if (isset($response['success']) && $response['success'] === 'true')
            return true;

        return false;

    }

    /**
     * Format the payment data to be sent to Paymob API.
     * This method structures the data, including integrations and the source of the payment.
     */
    public function formatDataPayment($request): array
    {
        $data                 = $request->all();
        $data['integrations'] = $this->integrations_id;
        $data['api_source']   = "INVOICE";

        return $data;
    }

}
