<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CryptoChartController extends Controller
{
    public function index()
    {
        return view('crypto.chart');
    }

    public function getCryptoData($coinId = 'bitcoin', $days = 30, $currency = 'usd')
    {
        $response = Http::get("https://api.coingecko.com/api/v3/coins/{$coinId}/market_chart", [
            'vs_currency' => $currency,
            'days' => $days,
        ]);

        return $response->json();
    }
}