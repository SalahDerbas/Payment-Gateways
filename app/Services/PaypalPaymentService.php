<?php

namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaypalPaymentService extends BasePaymentService implements PaymentGatewayInterface
{

    /**
     * Constructor to initialize class properties.
     * Sets up the necessary environment variables and prepares the headers for PayPal API requests.
     */
    protected $client_id;
    protected $client_secret;
    public function __construct()
    {
        $this->base_url      = env("PAYPAL_BASE_URL");
        $this->client_id     = env("PAYBAL_CLIENT_ID");
        $this->client_secret = env("PAYPAL_CLIENT_SECRET");
        $this->header        = [
                "Accept"         =>  "application/json",
                'Content-Type'   =>  "application/json",
                'Authorization'  =>  "Basic " . base64_encode("$this->client_id:$this->client_secret"),
        ];
    }

    /**
     * Send a payment request to the PayPal gateway.
     * This method formats the payment data, sends the payment request, and returns the approval URL for PayPal if successful.
     */
    public function sendPayment(Request $request): array
    {
        $data = $this->formatDataPayment($request);

        $response = $this->buildRequest("POST", "/v2/checkout/orders", $data);

        if ($response->getData(true)['success'])
            return ['success' => true,'url' => $response->getData(true)['data']['links'][1]['href']];

        return ['success' => false,'url'=>route('payment.failed')];

    }

    /**
     * Handle the callback from PayPal after the payment attempt.
     * This method captures the payment after approval and checks the status of the payment.
     */
    public function callBack(Request $request) : bool
    {
        $token    = $request->get('token');
        $response = $this->buildRequest('POST',"/v2/checkout/orders/$token/capture");

        Storage::put('paypal.json',
            json_encode([
                'callback_response' => $request->all(),
                'capture_response'  => $response
            ])
        );

        if($response->getData(true)['success'] && $response->getData(true)['data']['status']==='COMPLETED' )
            return true;

        return false;
    }

    /**
     * Format the payment data to be sent to PayPal API.
     * This method structures the data for the PayPal payment request.
     */
    public function formatDataPayment($request): array
    {
        return [
            "intent"         => "CAPTURE",
            "purchase_units" => [
                [
                    "amount" => $request->input("amount"),
                ]
            ],
            "payment_source"  => [
                "paypal"  => [
                    "experience_context"  => [
                        "return_url"  =>  $request->getSchemeAndHttpHost().'/api/payment/callback',
                        "cancel_url"  =>  route("payment.failed"),
                    ]
                ]
            ]
        ];
    }

}
