<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class WalletController extends Controller
{
    public function getWallet()
    {
        $user = JWTAuth::user();
        if ($user) {

            $wallet = Wallet::where("user_id", $user->id)->first();
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
                $txns->where("user_name", "like", "%" . $request->keyword . "%");
                $txns->orWhere("status", "like", "%" . $request->keyword . "%");
                $txns->orWhere("order_number", "like", "%" . $request->keyword . "%");
                $txns->orWhere("txn_number", "like", "%" . $request->keyword . "%");
                $txns->orWhere("amount", "like", "%" . $request->keyword . "%");
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
