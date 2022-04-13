<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

use function React\Promise\Stream\first;

class WalletController extends Controller
{
    public function getWallet()
    {
        $user = JWTAuth::user();
        if ($user) {

            $wallet = Wallet::where("user_id", $user->id)->first();

            $wallet->payout = DB::table('wallet_payouts')
                ->where("user_id", $user->id)
                ->where("transaction_status", "paid")
                ->sum('payout_amount');

            if ($wallet) {
                return response([
                    'status'    => true,
                    'message'   => 'Wallet fetched successfully.',
                    'data'      => $wallet
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Wallet not found.'
            ], 404);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found.'
        ], 404);
    }

    public function getAllTransactions(Request $request)
    {
        $user = JWTAuth::user();
        if ($user) {
            $txns = Transaction::where("type", "wallet")->where("user_id", $user->id);
            if ($request->filled('keyword')) {
                $txns->where(function ($q) use ($request) {
                    $q->where("user_name", "like", "%" . $request->keyword . "%");
                    $q->orWhere("status", "like", "%" . $request->keyword . "%");
                    $q->orWhere("order_number", "like", "%" . $request->keyword . "%");
                    $q->orWhere("txn_number", "like", "%" . $request->keyword . "%");
                    $q->orWhere("amount", "like", "%" . $request->keyword . "%");
                });
            }
            $txns = $txns->get();
            return response([
                'status'    => true,
                'message'   => 'Transactions fetched successfully.',
                'data'      => $txns
            ]);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found!'
        ], 404);
    }
}
