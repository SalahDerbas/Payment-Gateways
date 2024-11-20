<?php

namespace App\Providers;

use App\Interfaces\PaymentGatewayInterface;
use App\Services\PaymobPaymentService;
use App\Services\TapPaymentService;
use App\Services\MyFatoorahPaymentService;
use App\Services\PaypalPaymentService;
use App\Services\MoyasarPaymentService;

use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class , PaymobPaymentService::class);
        $this->app->bind(PaymentGatewayInterface::class , TapPaymentService::class);
        $this->app->bind(PaymentGatewayInterface::class , MyFatoorahPaymentService::class);
        $this->app->bind(PaymentGatewayInterface::class , PaypalPaymentService::class);
        $this->app->bind(PaymentGatewayInterface::class , MoyasarPaymentService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
