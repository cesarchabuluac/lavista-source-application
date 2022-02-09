<?php

namespace App\Http\Controllers;

use App\BankAccount;
use App\BankAccountStatement;
use App\CreditNote;
use App\Expense;
use App\Income;
use App\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BudgetController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $max_month = 12;
        $year = 2021;
        $date_from = $year . '-01-01';
        $days_to = cal_days_in_month(CAL_GREGORIAN, $max_month, $year);
        $date_to = $year . '-' . sprintf('%02d', $max_month) . '-' . sprintf('%02d', $days_to);
        $month_names = getMonthNames();
        $budget_hoa_dues = 0.00;

        $bank_accounts = ['6387-mxn', '6735-usd', '0903-mxn', '5716-mxn', '6062-usd', 'covid', '24383-mxn'];
        $bank_currencies = [
            '6387-mxn' => 'mxn',
            '6735-usd' => 'usd',
            '0903-mxn' => 'mxn',
            '5716-mxn' => 'mxn',
            '6062-usd' => 'usd',
            'covid' => 'mxn',
            '24383-mxn' => 'mxn',
        ];

        $bank_account_balances = $this->getOpeningBankAccountBalances($bank_accounts, $year, $max_month);
        Log::info($bank_account_balances);
    }

    /***
     * Protected methods
     */

    protected function getOpeningBankAccountBalances($bank_accounts, $year, $max_month)
    {
        $bank_account_balances = [];
        $max_month++;
        $max_month = $max_month > 12 ? 12 : $max_month;
        foreach (range(1, 12) as $month) {
            $balances = [];
            $date = $year . '-' . sprintf('%02d', $month) . '-01';
            foreach ($bank_accounts as $bank_account) {
                $balance = $this->getBankAccountBalance($bank_account, $date);
                $b_a = BankAccount::find($bank_account);
                // Log::info($b_a);
                $balances[$bank_account] = $balance;
            }
            $bank_account_balances[$month] = $balances;
        }
        if ($max_month >= 12)
            $balances = [];
        $date = ($year + 1) . '-01-01';
        foreach ($bank_accounts as $bank_account) {
            $balance = $this->getBankAccountBalance($bank_account, $date);
            $b_a = BankAccount::find($bank_account);
            $balances[$bank_account] = $balance;
        }
        $bank_account_balances[13] = $balances;

        return $bank_account_balances;
    }

    protected function getBankAccountBalance($id_bank_account, $date)
    {

        $bank_account = BankAccountStatement::where('id_bank_account', $id_bank_account)->first();
        $previous_balance = $bank_account->previous_balance;
        $currency = $bank_account->bankAccount->currency; //currencie->id_currency;

        $incomes_total = 0;

        //First Day on year
        $firstDayOfYear = "2022-01-01";


        //First income
        $Incomes = Income::where('id_bank_account', $id_bank_account)
            ->where('deposit_date', '>=', $firstDayOfYear)
            ->where('deposit_date', '<' . $date)->get();

        if (!empty($Incomes)) {
            foreach ($Incomes as $key => $income) {
                $incomes_total += $income->amount;
            }
        }


        $credit_notes_total = 0;
        $credit_notes = CreditNote::where(['id_bank_account' => $id_bank_account])
            ->where('date', '>=', $firstDayOfYear)
            ->where('date', '<', $date)->get();

        if (!empty($credit_notes)) {
            foreach ($credit_notes as $key => $credit_note) {
                $credit_notes_total += $credit_note->totalmxn;
            }
        }

        $payments_sum = 0;
        $Payments = Payment::where('id_bank_account', $id_bank_account)
            ->where('currency', $currency)
            ->where('deposit_date', '>=', $firstDayOfYear)
            ->where('bool_has_conversion', false)
            ->where('deposit_date', '<', $date)->get();

        if (!empty($Payments)) {
            foreach ($Payments as $key => $payment) {
                if ($currency == 'MXN') {
                    $debit = $payment->totalmxn;
                } else if ($currency == 'USD') {
                    $debit = $payment->totalusd;
                }
                $payments_sum += $debit;
            }
        }

        $PaymentConversions =  Payment::where('id_bank_account', $id_bank_account)
            ->where('conversion_currency', $currency)
            ->where('bool_has_conversion', true)
            ->where('deposit_date', '<', $date)->get();

        if (!empty($PaymentConversions)) {
            foreach ($PaymentConversions as $key => $payment) {
                $payments_sum += $payment->conversion_amount;
            }
        }


        /******************* PAYMENTS - CONVERSION  *********************/

        $expenses_sum = 0;
        $Expenses = Expense::where('id_bank_account', $id_bank_account)
            ->where('check_uncashed_active', 0)
            ->where('date', '>=', $firstDayOfYear)
            ->where('date',  '<', $date)->get();

        if (!empty($Expenses)) {
            foreach ($Expenses as $key => $expense) {
                $expenses_sum += $expense->amount;
            }
        }

        $ExpensesCashedmode = Expense::where('id_bank_account', $id_bank_account)
            ->where('check_uncashed_active', 1)
            ->where('check_is_cashed', 1)
            ->where('check_cashed_date', '>=', $firstDayOfYear)
            ->where('check_cashed_date',  '<', $date)->get();
        if (!empty($ExpensesCashedmode)) {
            foreach ($ExpensesCashedmode as $key => $expense) {
                $expenses_sum += $expense->amount;
            }
        }

        return $credit_notes_total + $payments_sum + $incomes_total - $expenses_sum + $previous_balance;
    }
}
