<?php

namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Firebase\JWT\JWT;

class ZainCashPaymentService extends BasePaymentService implements PaymentGatewayInterface
{
    /**
     * Constructor for initializing the class properties.
     * Sets up the necessary variables from environment variables and default values.
     */
    protected string $secret;
    protected string $merchantId;
    protected string $msisdin;
    protected string $transactionId;
    protected array  $header;

    public function __construct()
    {
        $this->base_url       = env("ZAIN_CASH_BASE_URL");
        $this->secret         = env("ZAIN_CASH_SECRET");
        $this->merchantId     = env("ZAIN_CASH_MERCHANT_ID");
        $this->msisdin        = env("ZAIN_CASH_MSISDIN");
        $this->header         = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Send payment request to the ZainCash gateway.
     * This method formats the data, sends a request, and returns the success URL if the transaction is successful.
     */
    public function sendPayment(Request $request):array
    {
        $data        =  $this->formatDataPayment($request);
        $dataToPost  =  $this->formatDataPost($this->generateJwtToken($data));

        $response = $this->buildRequest('POST', '/transaction/init', $dataToPost);

        if ($response->getData(true)['success']){
            $this->transactionId = $response->getData(true)['data']['id'];
            return ['success' => true, 'url' => $this->base_url.'/transaction/pay?id='.$this->transactionId];
        }

        return ['success' => false, 'url' => route('payment.failed')];
    }

    /**
     * Handle the callback from the ZainCash payment gateway.
     * This method validates the callback and checks the transaction status.
     */
    public function callBack(Request $request): bool
    {
        $token      = $request->all()['token'];
        $dataToPost =  $this->formatDataPost($token);

        $response = $this->buildRequest('POST', '/transaction/get', $dataToPost);
        Storage::put('zain_cash.json',
            json_encode( $response )
        );

        $responseData = $response->getData(true)['data'];
        if (isset($responseData['status']) && in_array($responseData['status'] ,  ["success","completed"]))
            return true;

        return false;
    }

    /**
     * Generate a JWT token for secure communication with ZainCash API.
     * This method encodes the data using the secret key and the HS256 algorithm.
     */
    private function generateJwtToken(array $data): string
    {
        return urlencode(JWT::encode($data, $this->secret, 'HS256'));
    }

    /**
     * Format the data to be posted to the ZainCash API.
     * This method prepares the data in the required structure for the request.
     */
    public function formatDataPost($token) : array
    {
        return [
            'token'      => $token ,
            'merchantId' => $this->merchantId,
            'lang'       => 'en',
        ];
    }

    /**
     * Format the payment data for sending to the ZainCash API.
     * This method structures the payment data including amount, order ID, and expiration time.
     */
    public function formatDataPayment($request): array
    {
        return [
            'amount'      =>   $request->amount,
            'serviceType' =>   "Payment",
            'msisdn'      =>   $this->msisdin,
            'orderId'     =>   "OrderID",
            'redirectUrl' =>   $request->getSchemeAndHttpHost().'/api/payment/callback',
            'iat'         =>   time(),
            'exp'         =>   time() + 60 * 60 * 4,
        ];
    }

}
