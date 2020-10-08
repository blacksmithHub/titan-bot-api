<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class Titan22Controller extends Controller
{
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
            'https://www.titan22.com/rest/V1/carts/mine/payment-information',
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
            'https://www.titan22.com/ccpp/htmlredirect/gettransactiondata',
            [
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]
        );

        if ($transactionResponse->getStatusCode() !== 200) abort($response->getStatusCode(), $response->getBody());

        $transactionResponse = json_decode($transactionResponse->getBody());

        $transactionData = collect($transactionResponse->fields);

        $transactionData = $transactionData->combine($transactionResponse->values)->all();

        $paymentResponse = $http->request(
            'POST',
            'https://t.2c2p.com/RedirectV3/Payment',
            [
                'headers' => [
                    'Accept' => 'application/x-www-form-urlencoded'
                ],
                'form_params' => $transactionData
            ]
        );

        if ($paymentResponse->getStatusCode() !== 200) abort($response->getStatusCode(), $response->getBody());

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
