<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Wallet;
use Exception;
use Illuminate\Support\Facades\Validator;
use Razorpay\Api\Api;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class RazorpayController extends Controller
{
    protected $api;
    public function __construct()
    {
        $this->api = new Api(env('RAZOR_KEY'), env('RAZOR_SECRET'));
    }


    public function createOrder(Request $request)
    {
        $user = JWTAuth::user();
        $validator = Validator::make($request->all(), [
            'amount'    => 'required',
            'type'      => 'required',
            'type_id'   => 'required',
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $option = [
            'receipt'   => base64_encode($request->type . '_' . $request->type_id),
            'amount'    => floatval($request->amount) * 100,
            'currency'  => 'INR',
            'notes'     => [
                'type'  => $request->type,
                'type_id'   => $request->type_id,
                'message'   => $request->filled('message') ? $request->message : ''
            ]
        ];
        try {
            $order = $this->api->order->create($option);

            if ($order) {
                $payment = new Transaction;
                $payment->amount = $order->amount / 100;
                $payment->paid = $order->amount_paid / 100;
                $payment->pending = $order->amount_due / 100;
                $payment->currency = 'INR';
                $payment->type = $request->type;
                $payment->type_id = $request->type_id;
                $payment->order_number = $order->id;
                $payment->user_id = $user->id;
                $payment->user_name = $user->first . ' ' . $user->last;
                $payment->txn_number = '';
                $payment->message = $request->filled('message') ? $request->message : '';

                $payment->save();

                return response([
                    'status'    => true,
                    'message'   => 'Razorpay order created successfully.',
                    'data'      => $payment
                ], 200);
            }
        } catch (Exception $error) {
            return response([
                'status'    => false,
                'message'   => 'Razorpay errors occured.',
                'error'     => $error
            ]);
        }
    }

    public function successPayment(Request $request)
    {
        if ($request->has('razorpay_order_id')) {
            $payment = Transaction::where("order_number", $request->razorpay_order_id)->first();
            if ($payment) {
                $payment->txn_number = $request->razorpay_payment_id;
                $payment->bank_ref   = $request->razorpay_signature;

                try {
                    $order = $this->api->order->fetch($request->razorpay_order_id);
                    if ($order) {
                        $payment->amount = $order->amount / 100;
                        $payment->paid = $order->amount / 100;
                        $payment->pending = 0.00;
                    }

                    $payment->method = 'online';
                    $payment->status = 'paid';
                    $payment->gateway_used = 'razorpay';

                    $payment->save();

                    //update agreement if type is agreement
                    if ($payment->type === 'rent') {
                        $agreement = Agreement::where("id", $payment->type_id)->first();
                        $agreement->number_of_invoices = Transaction::where("type_id", $agreement->id)->count();

                        if ($agreement->payment_frequency === 'monthly') {
                            $agreement->next_due = Carbon::parse($agreement->next_due)->addMonth();
                        }
                        if ($agreement->payment_frequency === 'quarterly') {
                            $agreement->next_due = Carbon::parse($agreement->next_due)->addMonths(3);
                        }
                        if ($agreement->payment_frequency === 'half-yearly') {
                            $agreement->next_due = Carbon::parse($agreement->next_due)->addMonths(6);
                        }
                        if ($agreement->payment_frequency === 'yearly') {
                            $agreement->next_due = Carbon::parse($agreement->next_due)->addMonths(12);
                        }


                        $agreement->save();
                    }

                    if ($payment->type === 'wallet') {
                        $wallet = Wallet::where("id", $payment->type_id)->first();
                        $wallet->amount += floatval($payment->paid);
                        $wallet->credit = floatval($payment->paid);
                        $wallet->last_transaction_type = 'credit';
                        $wallet->last_credit_transaction = date("Y-m-d H:i:s");

                        $wallet->save();
                    }

                    return response([
                        'status'    => true,
                        'message'   => 'Razorpay payment done successfully.',
                        'data'      => $payment,
                        'type'      => $payment->type === 'rent' ? $agreement : ($payment->type === 'wallet' ? $wallet : '')
                    ], 200);
                } catch (Exception $error) {
                    return response([
                        'status'    => false,
                        'message'   => 'Razorpay errors occured.',
                        'error'     => $error
                    ]);
                }
            }

            return response([
                'status'    =>  false,
                'message'   => 'Payment not found!'
            ], 404);
        }
    }

    public function getAllTransactions(Request $request)
    {
        $user = JWTAuth::user();

        if ($user) {
            $txns = Transaction::where("type", "!=", "wallet")->where(function ($q) use ($request, $user) {
                $q->where("user_id", $user->id);
                if ($request->filled('user') && $request->user === 'landlord') {
                    $tenants = Agreement::where("landlord_id", $user->id)->pluck("tenant_id")->toArray();
                    if (count($tenants) > 0) {
                        $q->orWhereIn("user_id", $tenants);
                    }
                }
                if ($request->filled('user') && $request->user === 'ibo') {
                    $tenants = Agreement::where("ibo_id", $user->id)->pluck("tenant_id")->toArray();
                    if (count($tenants) > 0) {
                        $q->orWhereIn("user_id", $tenants);
                    }
                }
            });

            $txns = $txns->get();

            return response([
                'status'    => true,
                'message'   => 'Transactions fetched successfully.',
                'data'      => $txns,
            ]);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found!'
        ], 404);
    }

    public function getRecentTransactions(Request $request)
    {
        $user = JWTAuth::user();

        if ($user) {
            $txns = Transaction::orderBy("created_at", "desc")->where(function ($q) use ($request, $user) {
                $q->where("user_id", $user->id);
                if ($request->filled('user') && $request->user === 'landlord') {
                    $tenants = Agreement::where("landlord_id", $user->id)->pluck("tenant_id")->toArray();
                    if (count($tenants) > 0) {
                        $q->orWhereIn("user_id", $tenants);
                    }
                }
                if ($request->filled('user') && $request->user === 'ibo') {
                    $tenants = Agreement::where("ibo_id", $user->id)->pluck("tenant_id")->toArray();
                    if (count($tenants) > 0) {
                        $q->orWhereIn("user_id", $tenants);
                    }
                }
            });

            $txns = $txns->limit(5)->get();

            return response([
                'status'    => true,
                'message'   => 'Transactions fetched successfully.',
                'data'      => $txns,
            ]);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found!'
        ], 404);
    }

    //getPropertyRentTxn
    public function getPropertyRentTxn($code)
    {
        $property = Property::where("property_code", $code)->first();

        if ($property) {
            $txns = Transaction::where("type", "rent")->where(function ($q) use ($property) {
                $agreement = Agreement::where("property_id", $property->id)->pluck("id")->toArray();
                $q->whereIn("type_id", $agreement);
            });

            $txns = $txns->get();

            return response([
                'status'    => true,
                'message'   => 'Transactions fetched successfully.',
                'data'      => $txns,
            ]);
        }

        return response([
            'status'    => false,
            'message'   => 'Property not found!'
        ], 404);
    }
}
