<?php

namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MyFatoorahPaymentService extends BasePaymentService implements PaymentGatewayInterface
{
    /**
     * Constructor to initialize class properties.
     * Sets up the necessary variables from environment variables and sets the headers for requests.
     */
    protected $api_key;
    public function __construct()
    {
        $this->base_url  = env("MYFATOORAH_BASE_URL");
        $this->api_key   = env("MYFATOORAH_API_KEY");
        $this->header    = [
            'accept'         => 'application/json',
            "Content-Type"   => "application/json",
            "Authorization"  => "Bearer " . $this->api_key,
        ];
    }

    /**
     * Send a payment request to the MyFatoorah gateway.
     * This method formats the payment data, sends the payment request, and returns the success URL if the transaction is successful.
     */
    public function sendPayment(Request $request): array
    {
        $data     = $this->formatDataPayment($request);

        $response = $this->buildRequest('POST', '/v2/SendPayment', $data);

        if($response->getData(true)['success'])
            return ['success' => true,'url' => $response->getData(true)['data']['Data']['InvoiceURL']];

        return ['success' => false,'url' => route('payment.failed')];
    }

    /**
     * Handle the callback from MyFatoorah after the payment attempt.
     * This method validates the callback and checks the payment status.
     */
    public function callBack(Request $request): bool
    {
        $data         = $this->formatDataCallBack($request->input('paymentId'));

        $response     = $this->buildRequest('POST', '/v2/getPaymentStatus', $data);
        $dataResponse = $response->getData(true);

        Storage::put('myfatoorah_response.json',
            json_encode([
            'myfatoorah_callback_response' =>   $request->all(),
            'myfatoorah_response_status'   =>   $dataResponse
            ])
        );

        if($dataResponse['data']['Data']['InvoiceStatus'] === 'Paid')
            return true;

        return false ;
    }

    /**
     * Format the payment data for sending to the MyFatoorah API.
     * This method structures the payment data including amount, order ID, and expiration time.
     */
    public function formatDataPayment($request): array
    {
        $data                       = $request->all();
        $data['NotificationOption'] = "LNK";
        $data['Language']           = "en";
        $data['CallBackUrl']        = $request->getSchemeAndHttpHost().'/api/payment/callback';

        return $data;
    }

    /**
     * Format the data for the callback to MyFatoorah.
     * This method prepares the necessary data to verify the callback.
     */
    public function formatDataCallBack($paymentId) : array
    {
        return [
            'KeyType' => 'paymentId',
            'Key'     => $paymentId ,
        ];
    }


}
