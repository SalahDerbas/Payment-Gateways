<?php

namespace App\Services;
use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SquarePaymentService extends BasePaymentService implements PaymentGatewayInterface
{

    /**
     * Constructor to initialize the Square API key, base URL, access token, and headers.
     */
    protected $api_key;
    public function __construct()
    {
        $this->base_url      = env("SQUARE_BASE_UR");
        $this->access_token  = env("SQUARE_ACCESS_TOKEN");
        $this->location_id   = env("SQUARE_LOCATION_ID");
        $this->header   = [
            'accept'         => "application/json",
            "Content-Type"   => "application/json",
            "Square-Version" => "2024-10-17",
            'Authorization'  => "Bearer " . $this->access_token. "",

        ];
    }

    /**
     * Sends a payment request to Square and returns the result.
     *
     * @param Request $request The incoming HTTP request containing payment details.
     * @return array An array containing success status and redirection URL.
     */
    public function sendPayment(Request $request)
    {
        $data     = $this->formatDataPayment($request);

        $response = $this->buildRequest('POST', '/v2/online-checkout/payment-links', $data);

        if($response->getData(true)['success'])
            return['success' => true, 'url' => $response->getData(true)['data']['payment_link']['url']];

        return['success' => false,'url' => route('payment.failed') ];

    }

    /**
     * Handles the callback from Square after payment processing.
     *
     * @param Request $request The incoming HTTP request containing callback data.
     * @return bool True if the payment was successful, otherwise false.
     */
    public function callBack(Request $request): bool
    {
        $responseData = $request->all();

        if($responseData['success'] && $responseData['data']['status'] == 'CAPTURED')
            return true;

        return false;
    }

    /**
     * Formats the payment data into the required structure for Square.
     *
     * @param Request $request The incoming HTTP request containing payment details.
     * @return array The formatted payment data to be sent to Square.
     */
    public function formatDataPayment($request): array
    {
        return [
            'idempotency_key'   => uniqid(),
            'quick_pay'         => [
                'name'        => 'Order',
                'price_money' => [
                    'amount'    => $request->input('amount'),
                    'currency'  => 'USD',
                ],
                'location_id' => $this->location_id,
            ],
            "pre_populated_data" =>  [
            "buyer_email"        => "buyer@email.com",
            "buyer_phone_number" => "1-415-555-1212",
            "buyer_address"      => [
                "address_line_1"                  => "1455 MARKET ST",
                "country"                         => "US",
                "administrative_district_level_1" => "CA",
                "locality"                        => "San Jose",
                "postal_code"                     => "94103"
            ],
        ],
            'checkout_options' => [
                'redirect_url' => $request->getSchemeAndHttpHost() . '/api/payment/callback',
            ],
        ];

    }
}
