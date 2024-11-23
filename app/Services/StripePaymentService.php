<?php

namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StripePaymentService extends BasePaymentService implements PaymentGatewayInterface
{

    /**
     * Constructor to initialize the Stripe API key, base URL, and headers.
     */
    protected mixed $api_key;
    public function __construct()
    {
        $this->base_url =  env("STRIPE_BASE_URL");
        $this->api_key  =  env("STRIPE_SECRET_KEY");
        $this->header   = [
            'Accept'          => 'application/json',
            'Content-Type'    =>'application/x-www-form-urlencoded',
            'Authorization'   => 'Bearer ' . $this->api_key,
        ];

    }

    /**
     * Sends a payment request to Stripe and returns the result.
     *
     * @param Request $request The incoming HTTP request containing payment details.
     * @return array An array containing success status and redirection URL.
     */
    public function sendPayment(Request $request): array
    {
        $data     = $this->formatDataPayment($request);

        $response = $this->buildRequest('POST', '/v1/checkout/sessions', $data, 'form_params');

        if($response->getData(true)['success'])
            return ['success' => true, 'url' => $response->getData(true)['data']['url']];

        return ['success' => false,'url'=>route('payment.failed')];
    }

    /**
     * Handles the callback from Stripe after payment processing.
     *
     * @param Request $request The incoming HTTP request containing callback data.
     * @return bool True if the payment was successful, otherwise false.
     */
    public function callBack(Request $request): bool
    {
        $session_id = $request->get('session_id');

        $response   = $this->buildRequest('GET','/v1/checkout/sessions/'.$session_id);

        Storage::put('stripe.json',
          json_encode([
            'callback_response' =>  $request->all(),
            'response'          =>  $response,
            ])
        );

        if($response->getData(true)['success'] && $response->getData(true)['data']['payment_status'] === 'paid')
             return true;

        return false;

    }

    /**
     * Formats the payment data into the required structure for Stripe.
     *
     * @param Request $request The incoming HTTP request containing payment details.
     * @return array The formatted payment data to be sent to Stripe.
     */
    public function formatDataPayment($request): array
    {
        return [
            "success_url" => $request->getSchemeAndHttpHost().'/api/payment/callback?session_id={CHECKOUT_SESSION_ID}',
            "line_items"  => [
                [
                    "price_data" => [
                        "unit_amount"   => $request->input('amount')*100,
                        "currency"      => $request->input("currency"),
                        "product_data"  => [
                            "name"         => "product name",
                            "description"  => "description of product"
                        ],
                    ],
                    "quantity" => 1,
                ],
            ],
            "mode" => "payment",
        ];
    }

}