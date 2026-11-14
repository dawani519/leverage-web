<?php

namespace App\Http\Controllers\Payments;

use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\Payments\DepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DepositCallbackController extends Controller
{
    public function __invoke(Request $request, DepositService $deposits): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');

        // A user may only settle their own deposit through the browser callback.
        $deposit = Deposit::where('reference', $reference)->where('user_id', $request->user()->id)->first();
        if (! $deposit) {
            return redirect()->route('dashboard')->with('status', 'deposit-unknown');
        }

        try {
            $status = $deposits->settle($deposit->reference)->status;
        } catch (PaymentException) {
            $status = 'flagged';
        }

        return redirect()->route('dashboard')->with('status', 'deposit-'.$status);
    }
}
