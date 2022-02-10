<?php

namespace App\Http\Controllers;

use App\BankAccount;
use App\BankAccountStatement;
use App\Budget;
use App\BudgetCategory;
use App\CreditNote;
use App\ExchangeRate;
use App\Expense;
use App\ExRateSourceAndApp;
use App\FeeConcept;
use App\Income;
use App\Payment;
use App\PaymentDetail;
use Carbon\Carbon;
use DateTime;
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

        $bank_account_balances = $this->getOpeningBankAccountBalances($bank_accounts, $year, $max_month);
        $bank_account_balances = $this->getBankAccountBalancesConversion($bank_account_balances, $bank_currencies, $year);

        $bank_account_names = array(
            '6387-mxn' => 'BBVA BANCOMER RESERVA CTA. 6387 PESOS',
            '6735-usd' => 'BBVA BANCOMER RESERVA CTA. 6735 DOLARES (EQ. IN MXN)',
            '0903-mxn' => 'BBVA BANCOMER CTA. 0903 PESOS',
            '5716-mxn' => 'BBVA BANCOMER CTA. 5716 PESOS',
            '6062-usd' => 'BBVA BANCOMER CTA. 6062 DOLARES (EQ. IN MXN)',
            'covid' => 'CONTINGENCY COVID-19 PESOS',
            '24383-mxn' => 'INTERCAM CTA. 24383 PESOS',
        );
        $bank_account_balances_totals = [];

        foreach ($bank_account_balances as $month => $balance) {
            $total = 0;
            foreach ($balance as $bank_account => $amount) {
                $total += $amount;
            }
            $bank_account_balances_totals[$month] = $total;
        }

        $budgets_result = [];
        $expenses_result = [];
        $budget_concepts_result = [];
        $budget_concepts_description = [];
        $budget_categories_result = [];

        $this->generateBudget2data(
            $date_to,
            $today,
            $today_m,
            $budgets_result,
            $expenses_result,
            $budget_concepts_result,
            $budget_concepts_description,
            $budget_categories_result
        );

        $concept_ids = [21, 22, 23, 29, 36, 50, 2, 8, 11, 12, 26, 27, 28, 33, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 9, 53, 54, 51];
        $concepts = FeeConcept::whereIn('id_concept', $concept_ids)->orderBy('_order', 'ASC')->get();
        $concept_ids = [];
        foreach ($concepts as $concept) {
            $concept_ids[] = $concept->id_concept;
        }

        $data_payment_totals = [];
        foreach (range(2019, $year) as $target_year) {
            $data_payment_totals[$target_year] = $this->getPaymentTotalsByYear($year, $target_year, $concept_ids);
        }

        $data_credit_notes_totals = [];
        foreach (range(2019, $year) as $target_year) {
            $data_credit_notes_totals[$target_year] = $this->getCreditNotesTotalsByYear($year, $target_year);
        }

        $data_concepts_total = [];
        foreach (array(55, 56) as $id_concept) {
            $data_concepts = [];
            foreach (range(2019, $year) as $target_year) {
                $data_concepts[$target_year] = $this->getOwnerTotalsByYear($year, $target_year, 74, $id_concept);
            }
            $data_concepts_total[$id_concept] = $data_concepts;
        }

        $exchange_rate_conversions = $this->getExchangeConversions($year);
        $exchange_rate_adjustments = $this->getExchangeRateAdjustments($year);
        $statements_data = $this->getStatementsData($year, $max_month);

        $data = array(
            'year' => $year,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'today_m' => $today_m,
            'budgets_result' => $budgets_result,
            'expenses_result' => $expenses_result,
            'budget_concepts_result' => $budget_concepts_result,
            'budget_concepts_description' => $budget_concepts_description,
            'budget_categories_result' => $budget_categories_result,
            'data_payment_totals' => $data_payment_totals,
            'data_credit_notes_totals' => $data_credit_notes_totals,
            'exchange_rate_conversions' => $exchange_rate_conversions,
            'exchange_rate_adjustments' => $exchange_rate_adjustments,
            'statements_data' => $statements_data,
            'month_names' => $month_names,
            'data_concepts_total' => $data_concepts_total,
            'budget_hoa_dues' => $budget_hoa_dues,
            'bank_accounts' => $bank_accounts,
            'bank_account_balances' => $bank_account_balances,
            'bank_account_names' => $bank_account_names,
            'bank_account_balances_totals' => $bank_account_balances_totals,
            'concept_ids' => $concept_ids,
        );
        Log::info(json_encode($data));

    }

    /***
     * Protected methods
     */

    /**
     * [getStatementsData description]
     *
     * @param   [type]  $year   [$year description]
     * @param   [type]  $month  [$month description]
     *
     * @return  [type]          [return description]
     */
    protected function getStatementsData($year, $month)
    {
        $result = [];
        // foreach (range(1,$month) as $month) {
        //     $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        //     $date1 = "{$year}-{$month}-01";
        //     $date2 = "{$year}-{$month}-{$days_in_month}";
        //     $data = Statements::allReport($date1, $date2);
        //     $result[$month] = $data;
        // }
        return $result;
    }


    /**
     * [generateBudget2data description]
     *
     * @param   [type]  $date                         [$date description]
     * @param   [type]  $today                        [$today description]
     * @param   [type]  $today_m                      [$today_m description]
     * @param   [type]  $budgets_result               [$budgets_result description]
     * @param   [type]  $expenses_result              [$expenses_result description]
     * @param   [type]  $budget_concepts_result       [$budget_concepts_result description]
     * @param   [type]  $budget_concepts_description  [$budget_concepts_description description]
     * @param   [type]  $budget_categories_result     [$budget_categories_result description]
     *
     * @return  [type]                                [return description]
     */
    protected function generateBudget2data($date, &$today, &$today_m, &$budgets_result, &$expenses_result, &$budget_concepts_result, &$budget_concepts_description, &$budget_categories_result)
    {
        $date = Carbon::parse($date)->format('Y-m-d'); //DateTime::createFromFormat('Y-m-d', $date);
        $budgets_result = [];
        $expenses_result = [];
        $budget_concepts_result = [];
        $budget_concepts_description = [];
        $budget_categories_result = [];

        $budget_concepts  = DB::table('cat_budget_concepts')
            ->join('cat_budget_categories', 'cat_budget_concepts.id_budget_category', '=', 'cat_budget_categories.id_budget_category')
            ->select('cat_budget_concepts.id_budget_concept', 'cat_budget_concepts.description', 'cat_budget_concepts.id_budget_category', 'cat_budget_categories.description as category')
            ->orderBy('cat_budget_categories._order', 'ASC')
            ->orderBy('cat_budget_concepts._order', 'ASC')
            ->get();

        $budget_categories = BudgetCategory::orderBy('_order', 'ASC')->get();

        foreach ($budget_categories as $category)
            $budget_categories_result[$category->id_budget_category] = $category->description;
        foreach ($budget_concepts as $key => $concept) {
            // $concept = json_decode(json_encode($concept), true);
            $expenses_result[$concept->id_budget_concept] =
                [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0];
            $budget_concepts_description[$concept->id_budget_concept] = $concept->description;
            $budget_concepts_result[$concept->id_budget_category] = [];
            $budget_concepts_result[$concept->id_budget_category][] = $concept->id_budget_concept;
        }


        $today = $date;
        $today_y = intval(date('Y', strtotime($date)));
        $today_m = intval(date('m', strtotime($date)));
        $today_d = intval(date('d', strtotime($date)));

        for ($month = 1; $month < $today_m; $month++) {
            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $today_y);
            $start_date = $today_y . '-' . $month . '-01';
            $end_date = $today_y . '-' . $month . '-' . $days_in_month;

            $expenses_normal = Expense::where(function ($q) {
                $q->whereNull('expense_type')
                    ->orWhere('expense_type', 'warranty-deposit');
            })
                ->where('check_is_cashed', 1)
                ->whereNotNull('id_budget_concept')
                ->where('date', '>=', $start_date)
                ->where('date', '<=', $end_date)
                ->where('check_uncashed_active', 0)
                ->get();

            $expenses_cashedmode = Expense::where(function ($q) {
                $q->whereNull('expense_type')
                    ->orWhere('expense_type', 'warranty-deposit');
            })
                ->where('check_is_cashed', 1)
                ->whereNotNull('id_budget_concept')
                ->where('check_cashed_date', '>=', $start_date)
                ->where('check_cashed_date', '<=', $end_date)
                ->where('check_uncashed_active', 1)
                ->get();

            $expenses_details_ids_normal = DB::table('expenses_details')
                ->leftjoin('expenses', 'expenses_details.id_expense', '=', 'expenses.id_expense')
                ->selectRaw('expenses_details.id_expense_detail, expenses_details.date as date_details, expenses_details.amount, 
                expenses_details.currency, expenses.date as date_expenses, expenses_details.id_budget_concept')
                ->whereNotNull('expenses_details.id_budget_concept')
                ->where('expenses.check_is_cashed', 1)
                ->where('expenses.check_uncashed_active', 0)
                ->where('expenses.date', '>=', $start_date)
                ->where('expenses.date', '<=', $end_date)
                ->get();

            $expenses_details_ids_uncashedmode = DB::table('expenses_details')
                ->leftjoin('expenses', 'expenses_details.id_expense', '=', 'expenses.id_expense')
                ->selectRaw('expenses_details.id_expense_detail, expenses_details.date as date_details, expenses_details.amount, 
                expenses_details.currency, expenses.date as date_expenses, expenses_details.id_budget_concept')
                ->whereNotNull('expenses_details.id_budget_concept')
                ->where('expenses.check_is_cashed', 1)
                ->where('expenses.check_uncashed_active', 1)
                ->where('expenses.check_cashed_date', '>=', $start_date)
                ->where('expenses.check_cashed_date', '<=', $end_date)
                ->get();


            foreach ($expenses_normal as $value) {
                if (array_key_exists($value->id_budget_concept, $expenses_result)) {
                    if ($value->currency == 'MXN')
                        $expenses_result[$value->id_budget_concept][$month] += $value->amount;
                    else if ($value->currency == 'USD') {
                        $e_r = $this->getExchangeRate($value->date);
                        $expenses_result[$value->id_budget_concept][$month] += ($value->amount * $e_r);
                    }
                }
            }

            foreach ($expenses_cashedmode as $value) {
                if (array_key_exists($value->id_budget_concept, $expenses_result)) {
                    if ($value->currency == 'MXN')
                        $expenses_result[$value->id_budget_concept][$month] += $value->amount;
                    else if ($value->currency == 'USD') {
                        $e_r = $this->getExchangeRate($value->date);
                        $expenses_result[$value->id_budget_concept][$month] += ($value->amount * $e_r);
                    }
                }
            }

            foreach ($expenses_details_ids_normal as $value) {
                if (array_key_exists($value->id_budget_concept, $expenses_result)) {
                    if ($value->currency == 'MXN')
                        $expenses_result[$value->id_budget_concept][$month] += $value->amount;
                    else if ($value->currency == 'USD') {
                        $e_r = $this->getExchangeRate($value->date_expenses);
                        $expenses_result[$value->id_budget_concept][$month] += ($value->amount * $e_r);
                    }
                }
            }

            foreach ($expenses_details_ids_uncashedmode as $value) {
                if (array_key_exists($value->id_budget_concept, $expenses_result)) {
                    if ($value->currency == 'MXN')
                        $expenses_result[$value->id_budget_concept][$month] += $value->amount;
                    else if ($value->currency == 'USD') {
                        $e_r = $this->getExchangeRate($value->date_expenses);
                        $expenses_result[$value->id_budget_concept][$month] += ($value->amount * $e_r);
                    }
                }
            }
        }

        $start_date = $today_y . '-' . $today_m . '-01';
        $end_date = $today_y . '-' . $today_m . '-' . $today_d;

        $expenses_normal = Expense::where(function ($q) {
            $q->whereNull('expense_type')
                ->orWhere('expense_type', 'warranty-deposit');
        })
            ->where('check_is_cashed', 1)
            ->whereNotNull('id_budget_concept')
            ->where('date', '>=', $start_date)
            ->where('date', '<=', $end_date)
            ->where('check_uncashed_active', 0)
            ->get();

        $expenses_cashedmode = Expense::where(function ($q) {
            $q->whereNull('expense_type')
                ->orWhere('expense_type', 'warranty-deposit');
        })
            ->where('check_is_cashed', 1)
            ->whereNotNull('id_budget_concept')
            ->where('check_cashed_date', '>=', $start_date)
            ->where('check_cashed_date', '<=', $end_date)
            ->where('check_uncashed_active', 1)
            ->get();

        $expenses_details_ids_normal = DB::table('expenses_details')
            ->leftjoin('expenses', 'expenses_details.id_expense', '=', 'expenses.id_expense')
            ->selectRaw('expenses_details.id_expense_detail, expenses_details.date as date_details, expenses_details.amount, 
                expenses_details.currency, expenses.date as date_expenses, expenses_details.id_budget_concept')
            ->whereNotNull('expenses_details.id_budget_concept')
            ->where('expenses.check_is_cashed', 1)
            ->where('expenses.check_uncashed_active', 0)
            ->where('expenses.date', '>=', $start_date)
            ->where('expenses.date', '<=', $end_date)
            ->get();

        $expenses_details_ids_uncashedmode = DB::table('expenses_details')
            ->leftjoin('expenses', 'expenses_details.id_expense', '=', 'expenses.id_expense')
            ->selectRaw('expenses_details.id_expense_detail, expenses_details.date as date_details, expenses_details.amount, 
                expenses_details.currency, expenses.date as date_expenses, expenses_details.id_budget_concept')
            ->whereNotNull('expenses_details.id_budget_concept')
            ->where('expenses.check_is_cashed', 1)
            ->where('expenses.check_uncashed_active', 1)
            ->where('expenses.check_cashed_date', '>=', $start_date)
            ->where('expenses.check_cashed_date', '<=', $end_date)
            ->get();

        foreach ($expenses_normal as $value) {
            if (array_key_exists($value->id_budget_concept, $expenses_result)) {
                if ($value->currency == 'MXN')
                    $expenses_result[$value->id_budget_concept][$today_m] += $value->amountmxn;
                else if ($value->currency == 'USD') {
                    $e_r = $this->getExchangeRate($value->date);
                    $expenses_result[$value->id_budget_concept][$today_m] += ($value->amountmxn * $e_r);
                }
            }
        }

        foreach ($expenses_cashedmode as $value) {
            if (array_key_exists($value->id_budget_concept, $expenses_result)) {
                if ($value->currency == 'MXN')
                    $expenses_result[$value->id_budget_concept][$today_m] += $value->amountmxn;
                else if ($value->currency == 'USD') {
                    $e_r = $this->getExchangeRate($value->date);
                    $expenses_result[$value->id_budget_concept][$today_m] += ($value->amountmxn * $e_r);
                }
            }
        }

        foreach ($expenses_details_ids_normal as $value) {
            if (array_key_exists($value->id_budget_concept, $expenses_result)) {
                if ($value->currency == 'MXN')
                    $expenses_result[$value->id_budget_concept][$today_m] += $value->amount;
                else if ($value->currency == 'USD') {
                    $e_r = $this->getExchangeRate($value->date_expenses);
                    $expenses_result[$value->id_budget_concept][$today_m] += ($value->amount * $e_r);
                }
            }
        }

        foreach ($expenses_details_ids_uncashedmode as $value) {
            if (array_key_exists($value->id_budget_concept, $expenses_result)) {
                if ($value->currency == 'MXN')
                    $expenses_result[$value->id_budget_concept][$today_m] += $value->amount;
                else if ($value->currency == 'USD') {
                    $e_r = $this->getExchangeRate($value->date_expenses);
                    $expenses_result[$value->id_budget_concept][$today_m] += ($value->amount * $e_r);
                }
            }
        }

        $budgets = Budget::where('year', $today_y)->get();
        foreach ($budgets as $budget) {
            $budgets_result[$budget->id_budget_concept]['amount'] = $budget->amount;
        }
    }

    protected function getBankAccountBalancesConversion($bank_account_balances, $bank_currencies, $year)
    {
        foreach ($bank_account_balances as $month => &$balances) {
            foreach ($balances as $bank_account => $amount) {
                if ($bank_currencies[$bank_account] == 'usd') {
                    if ($month > 12) {
                        $year += 1;
                    }
                    $m = $month;
                    if ($month > 12) {
                        $m = $month - 12;
                    }
                    $date = $year . '-' . sprintf('%02d', $m) . '-01';
                    $e_r = $this->getExchangeRateConversion($date);
                    $e_r = $e_r ? $e_r : 1;
                    $balances[$bank_account] *= $e_r;
                }
            }
        }
        return $bank_account_balances;
    }

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
                // $b_a = BankAccount::find($bank_account);
                $balances[$bank_account] = $balance;
            }
            $bank_account_balances[$month] = $balances;
        }
        if ($max_month >= 12)
            $balances = [];
        $date = ($year + 1) . '-01-01';
        foreach ($bank_accounts as $bank_account) {
            $balance = $this->getBankAccountBalance($bank_account, $date);
            // $b_a = BankAccount::find($bank_account);
            $balances[$bank_account] = $balance;
        }
        $bank_account_balances[13] = $balances;

        return $bank_account_balances;
    }

    /**
     * [getBankAccountBalance description]
     *
     * @param   [type]  $id_bank_account  [$id_bank_account description]
     * @param   [type]  $date             [$date description]
     *
     * @return  [type]                    [return description]
     */
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

    /**
     * [getPaymentTotalsByYear description]
     *
     * @param   [type]  $year         [$year description]
     * @param   [type]  $target_year  [$target_year description]
     * @param   [type]  $concept_ids  [$concept_ids description]
     *
     * @return  [type]                [return description]
     */
    protected function getPaymentTotalsByYear($year, $target_year, $concept_ids)
    {
        $result = [];
        for ($i = 1; $i <= 12; $i++) {
            $newRow = [];

            /* 
                    2   Late Fees
                    9   Not Identified
                    11  Penalty Fees
                *   21  Mantenimiento por Indiviso / Undivided Interest
                *   22  Subsidio Fase II / Phase II Bank Shortfall
                *   23  Mantenimiento JardÃ­n Privativo / Private Garden Fee
                    24  Fine for speeding/Multa exceso de velocidad
                    26  Jan/Feb Dues Catch-up Reflects Dues Increase
                    27  Special Assessment Maintenance & Improvement
                    28  SA 1st Quarter 2019 Ongoing Maintenance Fund
                    29  SA Project Admin Fee
                    30  SA Removal of Propane Tank & Roof Sealing
                    33  Fine / Multa
                    34  SA 2nd Quarter 2019 Ongoing Maintenance Fund
                    35  SA 3rd Quarter 2019 Ongoing Maintenance Fund
                    36  SA 4th Quarter 2019 Ongoing Maintenance Fund
                    37  Contribucion de limpieza de palapa/ Palapa cleaning contribution
                    38  Balance Forwarded Payment
            */

            // $total_hoa =0;
            // $total_late_fees =0;
            // $total_incorrect_deposits =0;

            $payments = Payment::where(DB::raw("YEAR(value_date) = {$year}"))
                ->where(DB::raw("MONTH(value_date) = {$i}"))
                ->where('bool_has_conversion', false)
                ->get();


            foreach ($payments as $payment) {
                $payment_details = PaymentDetail::where('id_payment', $payment->id_payment)
                    ->where(DB::raw("YEAR(date_from) = {$target_year}"))->get();

                foreach ($payment_details as $detail) {
                    $amount = $detail->amountusd;
                    if ($detail->payment->currency == 'USD') {
                        $e_r = $this->getExchangeRate($detail->payment->deposit_date);
                        $e_r = $e_r ? $e_r : 1;
                        $amount *= $e_r;
                    }
                    if (in_array($detail->id_concept, $concept_ids)) {
                        $newRow[$detail->id_concept][] = $amount;
                    }
                }
            }

            $payments = Payment::where(DB::raw("YEAR(value_date) = {$year}"))
                ->where(DB::raw("MONTH(value_date) = {$i}"))
                ->where('bool_has_conversion', true)
                ->where('conversion_currency', 'USD')
                ->get();

            foreach ($payments as $payment) {

                $payment_details = PaymentDetail::where('id_payment', $payment->id_payment)
                    ->where(DB::raw("YEAR(date_from) = {$target_year}"))->get();

                foreach ($payment_details as $detail) {
                    if (in_array($detail->id_concept, $concept_ids)) {
                        $newRow[$detail->id_concept][] = $detail->amountmxn;
                    }
                }
            }
            $result[$i] = $newRow;
        }

        return $result;
    }

    /**
     * [getCreditNotesTotalsByYear description]
     *
     * @param   [type]  $year         [$year description]
     * @param   [type]  $target_year  [$target_year description]
     *
     * @return  [type]                [return description]
     */
    protected function getCreditNotesTotalsByYear($year, $target_year)
    {
        $result = [];
        for ($i = 1; $i <= 12; $i++) {
            $newRow = [];

            $credit_notes = CreditNote::where(DB::raw("YEAR(date) = {$year}"))
                ->where(DB::raw("MONTH(date) = {$i}"))
                ->get();

            foreach ($credit_notes as $credit_note) {

                $payment_details = PaymentDetail::where('id_credit_note', $credit_note->id_credit_note)
                    ->where(DB::raw("YEAR(date_from) = {$target_year}"))->get();

                foreach ($payment_details as $detail) {
                    $amount = $detail->amount;
                    $newRow[] = $amount;
                }
            }
            $result[$i] = $newRow;
        }
        return $result;
    }

    /**
     * [getOwnerTotalsByYear description]
     *
     * @param   [type]  $year         [$year description]
     * @param   [type]  $target_year  [$target_year description]
     * @param   [type]  $id_owner     [$id_owner description]
     * @param   [type]  $id_concept   [$id_concept description]
     *
     * @return  [type]                [return description]
     */
    protected function getOwnerTotalsByYear($year, $target_year, $id_owner, $id_concept)
    {
        $result = [];
        for ($i = 1; $i <= 12; $i++) {
            $newRow = [];

            $payments = Payment::where(DB::raw("YEAR(deposit_date) = {$year}"))
                ->where(DB::raw("MONTH(deposit_date) = {$i}"))
                ->where('id_owner', $id_owner) //Dues Statements: 01 "Others"
                ->get();

            foreach ($payments as $payment) {

                $payment_details = PaymentDetail::where('id_concept', $id_concept)
                    ->where('id_payment', $payment->id_payment)
                    ->where(DB::raw("YEAR(date_from) = {$target_year}"))->get();

                foreach ($payment_details as $detail) {
                    $amount = $detail->amountusd;
                    if ($detail->payment->currency == 'USD') {
                        $e_r = $this->getExchangeRate($detail->payment->deposit_date);
                        $e_r = $e_r ? $e_r : 1;
                        $amount *= $e_r;
                    }
                    $newRow[] = $amount;
                }
            }
            $result[$i] = $newRow;
        }

        return $result;
    }

    /**
     * [getExchangeConversions description]
     *
     * @param   [type]  $year  [$year description]
     *
     * @return  [type]         [return description]
     */
    protected function getExchangeConversions($year)
    {
        $result = [];
        foreach (range(1, 12) as $month) {
            $date = $year . '-' . sprintf('%02d', $month) . '-01';
            $e_r_adj = ExchangeRate::where('date', '<=', $date)->orderBy('date', 'DESC')->first();
            $result[$month] = $e_r_adj->amount;
        }
        return $result;
    }

    /**
     * [getExchangeRateAdjustments description]
     *
     * @param   [type]  $year  [$year description]
     *
     * @return  [type]         [return description]
     */
    protected function getExchangeRateAdjustments($year)
    {
        $result = [];
        foreach (range(1, 12) as $month) {
            $e_r_adj = ExRateSourceAndApp::where('year', $year)
                ->where('month', $month)
                ->first();
            $result[$month] = $e_r_adj->amount;
        }
        return $result;
    }

    /**
     * [getExchangeRateConversion description]
     *
     * @param   [type]  $date  [$date description]
     *
     * @return  [type]         [return description]
     */
    public function getExchangeRateConversion($date)
    {
        $value = ExchangeRate::where('date', '<=', $date)->orderBy('date', 'DESC')->first();
        if (!$value) {
            var_dump('No exchange rate for ' . $date);
            die();
        }
        return $value->amount;
    }

    /**
     * [getExchangeRate description]
     *
     * @param   [type]  $date  [$date description]
     *
     * @return  [type]         [return description]
     */
    public function getExchangeRate($date)
    {
        $value = ExchangeRate::where('date', '<=', $date)->orderBy('date', 'DESC')->first();
        if (!$value) {
            var_dump('No exchange rate for ' . $date);
            die();
        }
        return $value->amount;
    }
}
