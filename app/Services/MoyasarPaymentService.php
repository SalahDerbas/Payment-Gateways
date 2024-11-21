<?php

namespace App\Services;
use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MoyasarPaymentService extends BasePaymentService implements PaymentGatewayInterface
{

    /**
     * Constructor to initialize class properties.
     * Sets up the necessary environment variables and prepares the headers for Moyasar API requests.
     */
    protected  $api_secret;
    public function __construct()
    {
        $this->base_url    = env("MOYASAR_BASE_URL");
        $this->api_secret  = env("MOYASAR_SECRET_KEY");
        $this->header      = [
                'accept' => 'application/json',
                "Content-Type" => "application/json",
                "Authorization" => "Basic ".base64_encode("$this->api_secret:''"),
        ];
    }

    /**
     * Send a payment request to Moyasar.
     * This method formats the payment data, sends the request, and returns the URL for payment if successful.
     */
    public function sendPayment(Request $request)
    {
        $data     = $this->formatDataPayment($request);

        $response = $this->buildRequest('POST', '/v1/invoices', $data);

        if($response->getData(true)['success'])
            return['success' => true,'url' => $response->getData(true)['data']['url']];

        return['success' => false, 'url' => $response];
    }

    /**
     * Handle the callback from Moyasar after the payment attempt.
     * This method checks if the response status indicates a successful payment.
     */
    public function callBack(Request $request): bool
    {
        $response_status = $request->get('status');

        Storage::put( 'moyasar_response.json' ,
            json_encode($request->all())
        );

        if(isset($response_status) && $response_status === 'paid' )
            return true;

       return false;
    }

    /**
     * Format the payment data to be sent to Moyasar API.
     * This method structures the data, including the success URL where the user will be redirected after payment.
     */
    public function formatDataPayment($request): array
    {
        $data                = $request->all();
        $data['success_url'] = $request->getSchemeAndHttpHost() . '/api/payment/callback';

        return $data;
    }
}
