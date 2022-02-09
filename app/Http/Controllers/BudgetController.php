<?php

namespace App\Http\Controllers;

use App\BankAccount;
use App\BankAccountStatement;
use App\Income;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $max_month = 6;
        $year = 2020;
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

        // $bank_account_balances = $this->getOpeningBankAccountBalances($bank_accounts, $year, $max_month);
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

    protected function getBankAccountBalance($id_bank_account, $date){
        
        $bank_account = BankAccountStatement::where(['id_bank_account' => $id_bank_account])->first();
        $previous_balance = $bank_account->previous_balance;
        $currency = $bank_account->bankAccount->currency;

        $incomes_total = 0;

        //First income
        $Incomes = Income::where('id_bank_account', $id_bank_account)
        ->where('deposit_date', '>=', '')
        if (($Incomes = Incomes::find()
            ->where(['id_bank_account' => $id_bank_account])
            ->andWhere(['>=', 'deposit_date', BankAccountStatements::START_DATE])
            ->andWhere("deposit_date <'".$date."'")
            ->all()) !== null){
            foreach ($Incomes as $key => $income) {
                $incomes_total += $income->amount;
            }
        }

        $credit_notes_total = 0;
        if (($credit_notes = CreditNotes::find()
            ->where(['id_bank_account' => $id_bank_account])
            ->andWhere(['>=', 'date', BankAccountStatements::START_DATE])
            ->andWhere("date <'".$date."'")
            ->all()) !== null){

            foreach ($credit_notes as $key => $credit_note) {
                $credit_notes_total += $credit_note->totalmxn;
            }
        }


        $payments_sum = 0;
        if (($Payments = Payments::find()
            ->where(['id_bank_account' => $id_bank_account])
            ->andWhere(['currency' => $currency])
            ->andWhere(['>=', 'deposit_date', BankAccountStatements::START_DATE])
            ->andWhere(['bool_has_conversion' => false])
            ->andWhere("deposit_date <'".$date."'")
            ->all()) !== null){
            foreach ($Payments as $key => $payment) {
                if($currency == 'MXN'){
                    $debit = $payment->totalmxn;
                } else if($currency == 'USD'){
                    $debit = $payment->totalusd;
                }
                $payments_sum += $debit;
            }
        }

        if (($Payments = Payments::find()
            ->where(['id_bank_account' => $id_bank_account])
            ->andWhere(['bool_has_conversion' => true])
            ->andWhere(['conversion_currency' => $currency]) /**/
            ->andWhere("deposit_date <'".$date."'")
            ->all()) !== null){
            foreach ($Payments as $key => $payment) {
                $payments_sum += $payment->conversion_amount;
            }
        }


        /*

            ****************** PAYMENTS - CONVERSION  ********************

        */
        // if($date == '2020-08-01' && $id_bank_account == '8774-usd'){
        //     die($payments_sum);
        // }


        $expenses_sum = 0;
        if (($Expenses = Expenses::find()
            ->where(['id_bank_account' => $id_bank_account])
            ->andWhere('check_uncashed_active = 0')
            ->andWhere(['>=', 'date', BankAccountStatements::START_DATE])
            ->andWhere("date <'".$date."'")
            ->all()) !== null){
            foreach ($Expenses as $key => $expense) {
                $expenses_sum += $expense->amount;
            }
        }
        if (($Expenses_cashedmode = Expenses::find()
            ->where(['id_bank_account' => $id_bank_account])
            ->andWhere('check_uncashed_active = 1')
            ->andWhere('check_is_cashed = 1')
            ->andWhere(['>=', 'check_cashed_date', BankAccountStatements::START_DATE])
            ->andWhere("check_cashed_date <'".$date."'")
            ->all()) !== null){
            foreach ($Expenses_cashedmode as $key => $expense) {
                $expenses_sum += $expense->amount;
            }
        }

        return $credit_notes_total + $payments_sum + $incomes_total - $expenses_sum + $previous_balance;
    }
}
