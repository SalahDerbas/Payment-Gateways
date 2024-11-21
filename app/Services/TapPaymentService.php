<?php

namespace App\Services;
use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TapPaymentService extends BasePaymentService implements PaymentGatewayInterface
{
    /**
     * Constructor to initialize class properties.
     * Sets up the necessary variables from environment variables and prepares the headers for API requests.
     */
    protected $api_key;
    public function __construct()
    {
        $this->base_url = env("TAP_BASE_URL");
        $this->api_key  = env("TAP_API_KEY");
        $this->header   = [
            'accept'        => "application/json",
            "Content-Type"  => "application/json",
            "Authorization" => "Bearer " . $this->api_key,
        ];
    }

    /**
     * Send a payment request to the Tap payment gateway.
     * This method formats the payment data, sends the payment request, and returns the success URL if the transaction is successful.
     */
    public function sendPayment(Request $request)
    {
        $data     = $this->formatDataPayment($request);

        $response = $this->buildRequest('POST', '/v2/charges/', $data);

        if($response->getData(true)['success'])
            return['success' => true, 'url' => $response->getData(true)['data']['transaction']['url']];

        return['success'=>false,'url'=>route('payment.failed')];

    }

    /**
     * Handle the callback from Tap after the payment attempt.
     * This method validates the callback, checks the payment status, and logs the response for debugging.
     */
    public function callBack(Request $request): bool
    {
        $chargeId     = $request->input('tap_id');

        $response     = $this->buildRequest('GET', "/v2/charges/$chargeId");
        $responseData = $response->getData(true);

        Storage::put('tap_response.json',
            json_encode([
                'callback_response' => $request->all(),
                'response'          => $responseData
            ])
        );

        if($responseData['success'] && $responseData['data']['status'] == 'CAPTURED')
            return true;

        return false;
    }

    /**
     * Format the payment data to be sent to Tap API.
     * This method structures the data, including the source and redirect URL for the payment.
     */
    public function formatDataPayment($request): array
    {
        $data             = $request->all();
        $data['source']   = ['id' => 'src_all'];
        $data['redirect'] = ['url' => $request->getSchemeAndHttpHost() . '/api/payment/callback'];

        return $data;
    }
}
