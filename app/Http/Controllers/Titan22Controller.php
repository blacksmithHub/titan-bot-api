<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class Titan22Controller extends Controller
{
    /**
     * Create the controller instance and resolve its service.
     *
     */
    public function __construct()
    {
        $this->titan22 = config('services.titan22.url');
        $this->checkout = config('services.titan22.checkout');
    }

    /**
     * Place order.
     *
     * @param PlaceOrderRequest $request
     * @return \Illuminate\Http\Response
     */
    public function placeOrder(Request $request)
    {
        $cookieJar = new CookieJar();

        $http = new Client(['cookies' => $cookieJar]);

        $response = $http->request(
            'POST',
            sprintf('%s/rest/V1/carts/mine/payment-information', $this->titan22),
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => sprintf('Bearer %s', Arr::get($request, 'token'))
                ],
                'json' => Arr::get($request, 'payload')
            ]
        );

        if ($response->getStatusCode() !== 200) abort($response->getStatusCode(), $response->getBody());

        $transactionResponse = $http->request(
            'GET',
            sprintf('%s/ccpp/htmlredirect/gettransactiondata', $this->titan22),
            [
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]
        );

        if ($transactionResponse->getStatusCode() !== 200) abort($transactionResponse->getStatusCode(), $transactionResponse->getBody());

        $transactionResponse = json_decode($transactionResponse->getBody());

        $transactionData = collect($transactionResponse->fields);

        $transactionData = $transactionData->combine($transactionResponse->values)->all();

        $paymentResponse = $http->request(
            'POST',
            sprintf('%s/RedirectV3/Payment', $this->checkout),
            [
                'headers' => [
                    'Accept' => 'application/x-www-form-urlencoded'
                ],
                'form_params' => $transactionData
            ]
        );

        if ($paymentResponse->getStatusCode() !== 200) abort($paymentResponse->getStatusCode(), $paymentResponse->getBody());

        $cookie = $cookieJar->getCookieByName(('ASP.NET_SessionId'));

        return [
            'cookies' => [
                'name' => 'ASP.NET_SessionId',
                'value' => $cookie->getValue(),
                'domain' => $cookie->getDomain(),
                'expiry' => $cookie->getExpires(),
            ],
            'data' => $transactionData
        ];
    }
}
