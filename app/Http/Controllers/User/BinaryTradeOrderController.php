<?php

namespace App\Http\Controllers\User;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\BinaryTrade;
use App\Models\CoinPair;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class BinaryTradeOrderController extends Controller {
    public function binaryTradeOrder(Request $request) {

        $coinPair = CoinPair::active()->activeMarket()->activeCoin()->where(function ($query) {
            $query->where('type', Status::BINARY_TRADE)->orWhere('type', Status::BOTH_TRADE);
        })->with(['coin', 'market', 'marketData'])->where('id', $request->coin_pair_id)->first();

        if (!$coinPair) {
            return response()->json(['error' => 'Coin Pair not found.']);
        }

        $minInvest = $coinPair->min_binary_trade_amount;
        $maxInvest = $coinPair->max_binary_trade_amount;
        $duration  = implode(',', $coinPair->binary_trade_duration);

        $validator = Validator::make($request->all(), [
            'amount'       => "required|numeric|gte:$minInvest|lte:$maxInvest",
            'duration'     => "required|in:$duration",
            'direction'    => 'required|string|in:higher,lower',
            'coin_pair_id' => "required|integer|exists:coin_pairs,id",
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->all()]);
        }

        $user       = auth()->user();
        $existTrade = BinaryTrade::where('user_id', $user->id)->inactive()->exists();
        if ($existTrade) {
            return response()->json(['error' => 'You need to wait until the ongoing trade is completed']);
        }

        $userWallet = $user->wallets()->where('wallet_type', Status::WALLET_TYPE_FUNDING)->where('currency_id', $coinPair->coin_id)->first();
        if (!$userWallet) {
            return response()->json(['error' => 'You have no ' . @$coinPair->coin->symbol . ' funding wallet']);
        }

        if ($request->amount > $userWallet->balance) {
            return response()->json(['error' => 'Insufficient balance in your ' . @$coinPair->coin->symbol . ' funding wallet']);
        }

        $userWallet->balance -= $request->amount;
        $userWallet->save();

        $trx = getTrx();

        $transaction               = new Transaction();
        $transaction->user_id      = $user->id;
        $transaction->wallet_id    = $userWallet->id;
        $transaction->amount       = $request->amount;
        $transaction->charge       = 0;
        $transaction->post_balance = $userWallet->balance;
        $transaction->trx          = $trx;
        $transaction->trx_type     = '-';
        $transaction->details      = $request->amount . ' ' . @$coinPair->coin->symbol . ' ' . 'binary trade order';
        $transaction->remark       = 'binary_trade';
        $transaction->save();

        $response = Http::get('https://api.binance.com/api/v3/ticker/price', [
            'symbol' => str_replace('_', '', @$coinPair->symbol),
        ]);

        if (!$response->successful()) {
            return response()->json(['error' => 'Something went wrong']);
        }

        $data         = $response->json();
        $currentPrice = $data['price'] ?? null;
        if ($currentPrice == null) {
            return response()->json(['error' => 'Something went wrong']);
        }

        $currency       = $coinPair->coin;
        $currency->rate = $currentPrice;
        $currency->save();

        $binaryTrade                 = new BinaryTrade();
        $binaryTrade->user_id        = $user->id;
        $binaryTrade->coin_pair_id   = $request->coin_pair_id;
        $binaryTrade->amount         = $request->amount;
        $binaryTrade->last_price     = $currentPrice;
        $binaryTrade->duration       = (int) $request->duration;
        $binaryTrade->direction      = $request->direction;
        $binaryTrade->trx            = $trx;
        $binaryTrade->trade_ended_at = Carbon::now()->addSeconds((int) $request->duration);
        $binaryTrade->save();

        $newTrade = view('Template::partials.single_binary_table', compact('binaryTrade'))->render();

        return response()->json([
            'binary_trade_id' => $binaryTrade->id,
            'amount'          => $binaryTrade->amount,
            'direction'       => $binaryTrade->direction,
            'duration'        => $binaryTrade->duration,
            'newTrade'        => $newTrade,
        ]);
    }

    public function binaryTradeComplete(Request $request) {
        $validator = Validator::make($request->all(), [
            'binary_trade_id' => "required|integer|exists:binary_trades,id",
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->all()]);
        }

        $binaryTrade = BinaryTrade::inactive()->pending()->where('user_id', auth()->id())->withWhereHas('coinPair', function ($query) {
            $query->active()->activeMarket()->activeCoin()->where(function ($q) {
                $q->where('type', Status::BINARY_TRADE)->orWhere('type', Status::BOTH_TRADE);
            });
        })->where('id', $request->binary_trade_id)->first();

        if (!$binaryTrade) {
            return response()->json(['error' => 'Binary trade not found']);
        }

        if (now()->isBefore($binaryTrade->trade_ended_at)) {
            $binaryTrade->status = Status::ENABLE;
            $binaryTrade->save();
            return response()->json(['error' => 'Something went wrong']);
        }

        $coinPair = $binaryTrade->coinPair;
        $response = Http::get('https://api.binance.com/api/v3/ticker/price', [
            'symbol' => str_replace('_', '', @$coinPair->symbol),
        ]);

        if (!$response->successful()) {
            $binaryTrade->status = Status::ENABLE;
            $binaryTrade->save();
            return response()->json(['error' => 'Something went wrong']);
        }

        $data         = $response->json();
        $currentPrice = $data['price'] ?? null;
        if ($currentPrice == null) {
            $binaryTrade->status = Status::ENABLE;
            $binaryTrade->save();
            return response()->json(['error' => 'Something went wrong']);
        }

        $currency       = $coinPair->coin;
        $currency->rate = $currentPrice;
        $currency->save();

        $currencySymbol = $binaryTrade->coinPair->coin->symbol;
        $result         = $currentPrice > $binaryTrade->last_price;

        if (($binaryTrade->direction == "higher" && $result) || ($binaryTrade->direction == "lower" && !$result)) {
            $binaryTrade->win_status = Status::BINARY_TRADE_WIN;
            $binaryTrade->win_amount = $binaryTrade->amount + ($binaryTrade->amount * $binaryTrade->coinPair->binary_trade_profit / 100);
            $notification            = 'Congratulations! You have got ' . $binaryTrade->win_amount . ' ' . $currencySymbol . ' from binary trade';
        } else {
            $binaryTrade->win_status = Status::BINARY_TRADE_LOSE;
            $notification            = 'You lost ' . $binaryTrade->amount . ' ' . $currencySymbol;
        }

        $binaryTrade->result_price = $currentPrice;
        $binaryTrade->profit       = $binaryTrade->coinPair->binary_trade_profit;
        $binaryTrade->status       = Status::ENABLE;
        $binaryTrade->save();

        if ($binaryTrade->win_status == Status::BINARY_TRADE_WIN) {
            $user       = auth()->user();
            $userWallet = $user->wallets()->where('wallet_type', Status::WALLET_TYPE_FUNDING)->where('currency_id', $binaryTrade->coinPair->coin_id)->first();
            if (!$userWallet) {
                return response()->json(['error' => 'You have no ' . @$currencySymbol . ' funding wallet']);
            }

            $userWallet->balance += $binaryTrade->win_amount;
            $userWallet->save();

            $transaction               = new Transaction();
            $transaction->user_id      = $user->id;
            $transaction->wallet_id    = $userWallet->id;
            $transaction->amount       = $binaryTrade->win_amount;
            $transaction->charge       = 0;
            $transaction->post_balance = $userWallet->balance;
            $transaction->trx          = getTrx();
            $transaction->trx_type     = '+';
            $transaction->details      = $binaryTrade->win_amount . ' ' . $currencySymbol . ' binary trade win';
            $transaction->remark       = 'binary_trade';
            $transaction->save();
        }

        $trades           = BinaryTrade::where('user_id', auth()->id())->with('coinPair')->active()->latest()->take(5)->get();
        $closedTradeTable = view('Template::partials.binary_table', compact('trades'))->render();

        return response()->json([
            'win_status'       => $binaryTrade->win_status,
            'notification'     => $notification,
            'closedTradeTable' => $closedTradeTable,
        ]);
    }

    public function allTrade() {
        $pageTitle = 'All Binary Trade';
        $trades    = $this->getBinaryTrade('');
        return view('Template::user.binary.trade_history', compact('pageTitle', 'trades'));
    }
    public function winTrade() {
        $pageTitle = 'Win Binary Trade';
        $trades    = $this->getBinaryTrade('win');
        return view('Template::user.binary.trade_history', compact('pageTitle', 'trades'));
    }
    public function loseTrade() {
        $pageTitle = 'Lose Binary Trade';
        $trades    = $this->getBinaryTrade('lose');
        return view('Template::user.binary.trade_history', compact('pageTitle', 'trades'));
    }
    public function refundTrade() {
        $pageTitle = 'Refund Binary Trade';
        $trades    = $this->getBinaryTrade('refund');
        return view('Template::user.binary.trade_history', compact('pageTitle', 'trades'));
    }

    protected function getBinaryTrade($scope) {
        if ($scope) {
            $trades = BinaryTrade::$scope();
        } else {
            $trades = BinaryTrade::query();
        }
        return $trades->where('user_id', auth()->id())->searchable(['trx', 'coinPair:symbol', 'coinPair.coin:symbol'])->with('coinPair')->orderBy('id', 'desc')->paginate(getPaginate());
    }

    public function tradeHistory(Request $request) {
        $page   = $request->page ?? 1;
        $trades = BinaryTrade::active()->where('user_id', auth()->id())->with('coinPair')->orderBy('id', 'desc')->skip(($page - 1) * 5)->take(5)->get();

        $view = '';
        foreach ($trades as $key => $binaryTrade) {
            $view .= view('Template::partials.single_binary_table', compact('binaryTrade'))->render();
        }

        return response()->json([
            'trades' => $view,
        ]);
    }
}
