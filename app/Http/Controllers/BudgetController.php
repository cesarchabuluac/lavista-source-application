<?php

namespace App\Http\Controllers;

use App\BankAccount;
use App\BankAccountStatement;
use App\Budget;
use App\BudgetCategory;
use App\CreditNote;
use App\ExchangeRate;
use App\Expense;
use App\Exports\SourceAndApplication;
use App\ExRateSourceAndApp;
use App\FeeConcept;
use App\Income;
use App\Payment;
use App\PaymentDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
// use Maatwebsite\Excel\Facades\Excel;

class BudgetController extends Controller
{
    protected $excel;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * [conectDatabase description]
     *
     * @param   [type]  $database  [$database description]
     *
     * @return  [type]             [return description]
     */
    private function conectDatabase($database) {
        config()->set('database.connections.mysql.database', $database);
        DB::reconnect('mysql');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        $this->conectDatabase($request->residencias);

        $max_month = 12;
        $year = 2020;
        $date_from = $year . '-01-01';
        $days_to = cal_days_in_month(CAL_GREGORIAN, $max_month, $year);
        $date_to = $year . '-' . sprintf('%02d', $max_month) . '-' . sprintf('%02d', $days_to);
        $month_names = getMonthNames();
        $budget_hoa_dues = 0.00;

        $bank_accounts = BankAccount::all();
        $bank_currencies = $bank_accounts->pluck('currency', 'id_bank_account');
        
        $bank_account_balances = $this->getOpeningBankAccountBalances($bank_accounts, $year, $max_month);
        $bank_account_balances = $this->getBankAccountBalancesConversion($bank_account_balances, $bank_currencies, $year);

        $bank_account_names = [];

        //Bank names
        foreach ($bank_accounts as $key => $item) {
            $currency = ($item->currency === 'MXN') ? "PESOS" : " DOLARES (EQ. IN MXN)";
            $computed = "{$item->name} CTA. {$item->last_four} {$currency}";
            $bank_account_names[$item->id_bank_account] = strtoupper($computed);
        }

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

        $concepts = FeeConcept::orderBy('_order', 'ASC')->get();
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


        $today_m = $data['today_m'];
        $year = $data['year'];
        $date_from = $data['date_from'];
        $date_to = $data['date_to'];
        $budgets_result = $data['budgets_result'];
        $expenses_result = $data['expenses_result'];
        $budget_concepts_result = $data['budget_concepts_result'];
        $budget_concepts_description = $data['budget_concepts_description'];
        $budget_categories_result = $data['budget_categories_result'];
        $data_payment_totals = $data['data_payment_totals'];
        $data_credit_notes_totals = $data['data_credit_notes_totals'];
        $statements_data = $data['statements_data'];
        $data_concepts_total = $data['data_concepts_total'];
        $exchange_rate_conversions = $data['exchange_rate_conversions'];
        $exchange_rate_adjustments = $data['exchange_rate_adjustments'];
        $month_names = $data['month_names'];
        $budget_hoa_dues = $data['budget_hoa_dues'];
        $bank_accounts = $data['bank_accounts'];
        $bank_account_balances = $data['bank_account_balances'];
        $bank_account_names = $data['bank_account_names'];
        $bank_account_balances_totals = $data['bank_account_balances_totals'];
        $concept_ids = $data['concept_ids'];

        $budget_data = array(
            'today_m' => $today_m,
            'budgets_result' => $budgets_result,
            'expenses_result' => $expenses_result,
            'budget_concepts_result' => $budget_concepts_result,
            'budget_concepts_description' => $budget_concepts_description,
            'budget_categories_result' => $budget_categories_result,
        );

        // return Excel::download(new SourceAndApplication, 'users.xlsx');
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getProperties()->setCreator('Oviedo Sucesores 2020')
            ->setLastModifiedBy('Oviedo Sucesores')
            ->setTitle('Source And Application')
            ->setSubject('OSU Management Report')
            ->setDescription('HOA Admin Generated Document')
            ->setKeywords('Office 2007 openxml php')
            ->setCategory('OSU Reports');
        $spreadsheet->setActiveSheetIndex(0);

        //Insert logo
        $drawing = new \PhpOffice\PhpSpreadsheet\WorkSheet\Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');
        $path = public_path('assets/img/logo.png');

        $drawing->setPath($path);
        $drawing->setHeight(90);
        $drawing->setOffsetX(120);
        $drawing->setWorksheet($spreadsheet->getActiveSheet());

        $spreadsheet->getActiveSheet()->getStyle('A1:DD1')->applyFromArray(
            [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
            ]
        );

        //Set (logo) row height
        $spreadsheet->getActiveSheet()->getRowDimension(1)->setRowHeight(74);

        $arr['concept'] = array('column' => 'A', 'name' => '');
        $arr[1] = array('column' => 'B', 'name' => 'JANUARY');
        $arr[2] = array('column' => 'C', 'name' => 'FEBRUARY');
        $arr[3] = array('column' => 'D', 'name' => 'MARCH');
        $arr[4] = array('column' => 'E', 'name' => 'APRIL');
        $arr[5] = array('column' => 'F', 'name' => 'MAY');
        $arr[6] = array('column' => 'G', 'name' => 'JUNE');
        $arr[7] = array('column' => 'H', 'name' => 'JULY');
        $arr[8] = array('column' => 'I', 'name' => 'AUGUST');
        $arr[9] = array('column' => 'J', 'name' => 'SEPTEMBER');
        $arr[10] = array('column' => 'K', 'name' => 'OCTUBRE');
        $arr[11] = array('column' => 'L', 'name' => 'NOVEMBER');
        $arr[12] = array('column' => 'M', 'name' => 'DECEMBER');
        $arr['ytd_total'] = array('column' => 'N', 'name' => 'YTD TOTAL');
        $arr['annual_budget'] = array('column' => 'O', 'name' => 'BUDGET ASSIGNED');
        $arr['available'] = array('column' => 'P', 'name' => 'BUDGET AVAILABLE');

        $concept_col = $arr['concept']['column'];
        $ytd_total_col = $arr['ytd_total']['column'];
        $available_col = $arr['available']['column'];
        $annual_budget_col = $arr['annual_budget']['column'];

        $current_row = 3;

        //OPENING BALANCE SECTION
        foreach ($bank_accounts as $bank_account) {
            foreach (range(1, 12) as $month) {
                $month_col = $arr[$month]['column'];
                $amount = $bank_account_balances[$month][$bank_account->id_bank_account];

                //write amount for the current month
                $spreadsheet->getActiveSheet()
                    ->setCellValue($month_col . $current_row, $amount);
            }

            $spreadsheet->getActiveSheet()
                ->setCellValue($ytd_total_col . $current_row, $bank_account_balances[1][$bank_account->id_bank_account]);

            $spreadsheet->getActiveSheet()
                ->setCellValue($concept_col . $current_row, $bank_account_names[$bank_account->id_bank_account]);

            foreach ($arr as $value) {
                //paint yellow row
                $this->setYellowFormat($value['column'] . $current_row, $spreadsheet);
                $this->setCurrencyFormat($value['column'] . $current_row, $spreadsheet);
            }

            //collapse and group
            $spreadsheet->getActiveSheet()->getRowDimension($current_row)->setOutlineLevel(1);
            $spreadsheet->getActiveSheet()->getRowDimension($current_row)->setVisible(false);
            $spreadsheet->getActiveSheet()->getRowDimension($current_row)->setCollapsed(true);

            $current_row++;
        }

        $opening_balance_row = $current_row;

        $first_children_row = $current_row - count($bank_accounts);
        $last_children_row = $current_row - 1;

        foreach (range(1, 12) as $month) {
            $month_col = $arr[$month]['column'];

            //write amount for the current month
            $spreadsheet->getActiveSheet()
                ->setCellValue($month_col . $current_row, '=SUM(' . $month_col . $first_children_row . ':' . $month_col . $last_children_row . ')');
        }
        $spreadsheet->getActiveSheet()
            ->setCellValue($concept_col . $current_row, 'OPENING BALANCE 2020');

        $spreadsheet->getActiveSheet()
            ->setCellValue($ytd_total_col . $current_row, '=SUM(' . $ytd_total_col . $first_children_row . ':' . $ytd_total_col . $last_children_row . ')');

        foreach ($arr as $value) {
            //paint gray row
            $this->setDarkGrayFormat($value['column'] . $current_row, $spreadsheet);
            $this->setCurrencyFormat($value['column'] . $current_row, $spreadsheet);

            //make bold
            $spreadsheet->getActiveSheet()->getStyle($value['column'] . $current_row)->getFont()->setBold(true);
        }
        $current_row++;

        //CREDIT NOTES SECTION
        $spreadsheet->getActiveSheet()
            ->setCellValue($concept_col . $current_row, 'Credit Notes (cash)');
        foreach (range(1, $today_m) as $month) {
            $month_amount = is_array($data_credit_notes_totals[2020][$month]) ? array_sum($data_credit_notes_totals[2020][$month]) : 0;
            $month_col = $arr[$month]['column'];
            $spreadsheet->getActiveSheet()
                ->setCellValue($month_col . $current_row, $month_amount);
        }

        $first_month_cell = $arr[1]['column'] . $current_row;
        $last_month_cell = $arr[12]['column'] . $current_row;
        $ytd_total_formula = '=SUM(' . $first_month_cell . ':' . $last_month_cell . ')';

        $spreadsheet->getActiveSheet()
            ->setCellValue($ytd_total_col . $current_row, $ytd_total_formula);

        $income_rows[] = $current_row;
        $current_row++;

        //CONCEPTS TOTALS SECTION investment fund / other incomes
        $concepts_totals_arr = [
            55 => 'Fondo de Inversion/ Invesment Found',
            56 => 'Otros Ingresos/ Other Incomes',
        ];
        foreach ($concepts_totals_arr as $id_concept => $concept_description) {
            foreach (range(2019, $year) as $concepts_year) {
                $spreadsheet->getActiveSheet()
                    ->setCellValue($concept_col . $current_row, $concepts_year . ' ' . $concept_description);

                $total_year = 0;
                foreach (range(1, $today_m) as $month) {
                    $month_amount = is_array($data_concepts_total[$id_concept][$concepts_year][$month]) ? array_sum($data_concepts_total[$id_concept][$concepts_year][$month]) : 0;
                    $month_col = $arr[$month]['column'];
                    $total_year += $month_amount;
                    $spreadsheet->getActiveSheet()
                        ->setCellValue($month_col . $current_row, $month_amount);
                }

                $first_month_cell = $arr[1]['column'] . $current_row;
                $last_month_cell = $arr[12]['column'] . $current_row;
                $ytd_total_formula = '=SUM(' . $first_month_cell . ':' . $last_month_cell . ')';

                $spreadsheet->getActiveSheet()
                    ->setCellValue($ytd_total_col . $current_row, $ytd_total_formula);

                if ($total_year) {
                    $income_rows[] = $current_row;
                    $current_row++;
                }
            }
        }

        //INCOMES SECTION
        $concept_list = [];

        foreach (range(2019, $year) as $concepts_year) {
            $id_concepts = [];
            collect($data_payment_totals[$concepts_year])->each(function ($month_data, $month) use (&$id_concepts) {
                if (!empty($month_data)) {
                    foreach (array_keys($month_data) as $key => $id_concept) {
                        $id_concepts[] = $id_concept;
                    }
                }
            });

            $id_concepts = array_unique($id_concepts);
            foreach ($id_concepts as $key => $id_concept) {
                $concept_list[$id_concept] = $concept_list[$id_concept] ?? [];
                $concept_list[$id_concept][] = $concepts_year;
            }
        }

        foreach ($concept_ids as $key => $id_concept) {
            if (isset($concept_list[$id_concept])) {
                foreach ($concept_list[$id_concept] as $concepts_year) {
                    $income_rows[] = $current_row;
                    $concept = FeeConcept::find($id_concept);
                    $spreadsheet->getActiveSheet()
                        ->setCellValue($concept_col . $current_row, $concepts_year . ' ' . $concept->description);
                    $spreadsheet->getActiveSheet()
                        ->setCellValue($annual_budget_col . $current_row, $concept->s_and_a_column1);
                    $spreadsheet->getActiveSheet()
                        ->setCellValue($available_col . $current_row, $concept->s_and_a_column2);

                    foreach (range(1, $today_m) as $month) {

                        if (is_array($data_payment_totals[$concepts_year][$month])) {
                            if (isset($data_payment_totals[$concepts_year][$month][$id_concept]) && is_array($data_payment_totals[$concepts_year][$month][$id_concept])) {
                                $month_amount = is_array($data_payment_totals[$concepts_year][$month][$id_concept]) ? array_sum($data_payment_totals[$concepts_year][$month][$id_concept]) : 0;
                                $month_col = $arr[$month]['column'];
                                $spreadsheet->getActiveSheet()
                                    ->setCellValue($month_col . $current_row, $month_amount);
                            } else {
                                $month_amount = 0;
                                $month_col = $arr[$month]['column'];
                                $spreadsheet->getActiveSheet()
                                    ->setCellValue($month_col . $current_row, $month_amount);
                            }
                        }
                    }

                    $first_month_cell = $arr[1]['column'] . $current_row;
                    $last_month_cell = $arr[12]['column'] . $current_row;
                    $ytd_total_formula = '=SUM(' . $first_month_cell . ':' . $last_month_cell . ')';

                    $spreadsheet->getActiveSheet()
                        ->setCellValue($ytd_total_col . $current_row, $ytd_total_formula);

                    $current_row++;
                }
            }
        }

        //EXCHANGE RATE CONVERSION
        $income_rows[] = $current_row;
        $spreadsheet->getActiveSheet()
            ->setCellValue($concept_col . $current_row, 'EXCHANGE RATE');

        foreach (range(1, 12) as $month) {
            $amount = $exchange_rate_adjustments[$month];

            $month_col = $arr[$month]['column'];
            $spreadsheet->getActiveSheet()
                ->setCellValue($month_col . $current_row, $amount);
        }

        $first_month_cell = $arr[1]['column'] . $current_row;
        $last_month_cell = $arr[12]['column'] . $current_row;
        $ytd_total_formula = '=SUM(' . $first_month_cell . ':' . $last_month_cell . ')';

        $spreadsheet->getActiveSheet()
            ->setCellValue($ytd_total_col . $current_row, $ytd_total_formula);

        $current_row++;

        //FORMATING AND COLLAPSE ROWS
        foreach ($income_rows as $income_row) {
            foreach ($arr as $value) {
                //paint yellow row
                $this->setYellowFormat($value['column'] . $income_row, $spreadsheet);
                $this->setCurrencyFormat($value['column'] . $income_row, $spreadsheet);
            }

            //collapse and group
            $spreadsheet->getActiveSheet()->getRowDimension($income_row)->setOutlineLevel(1);
            $spreadsheet->getActiveSheet()->getRowDimension($income_row)->setVisible(false);
            $spreadsheet->getActiveSheet()->getRowDimension($income_row)->setCollapsed(true);
        }

        //TOTAL INCOMES
        $first_incomes_row = $income_rows[0];
        $last_incomes_row = $income_rows[count($income_rows) - 1];

        $total_incomes_row = $current_row;

        $spreadsheet->getActiveSheet()
            ->setCellValue($concept_col . $current_row, 'TOTAL INCOMES');

        $last_incomes_row = $current_row - 1;

        foreach (range(1, 12) as $month) {
            $month_col = $arr[$month]['column'];

            //write amount for the current month
            $spreadsheet->getActiveSheet()
                ->setCellValue($month_col . $current_row, '=SUM(' . $month_col . $first_incomes_row . ':' . $month_col . $last_incomes_row . ')');
        }

        $first_month_cell = $ytd_total_col . $first_incomes_row;
        $last_month_cell = $ytd_total_col . $last_incomes_row;
        $ytd_total_formula = '=SUM(' . $first_month_cell . ':' . $last_month_cell . ')';

        $spreadsheet->getActiveSheet()
            ->setCellValue($ytd_total_col . $current_row, $ytd_total_formula);

        foreach ($arr as $value) {
            //paint gray row
            $this->setDarkGrayFormat($value['column'] . $current_row, $spreadsheet);
            $this->setCurrencyFormat($value['column'] . $current_row, $spreadsheet);

            //make bold
            $spreadsheet->getActiveSheet()->getStyle($value['column'] . $current_row)->getFont()->setBold(true);
        }

        $current_row++;

        //TOTAL OF FUNDS
        $total_of_funds_row = $current_row;

        $spreadsheet->getActiveSheet()
            ->setCellValue($concept_col . $current_row, 'TOTAL OF FUNDS');

        foreach (range($arr[1]['column'], $arr['ytd_total']['column']) as $column) {
            $opening_balance_cell = $column . $opening_balance_row;
            $total_incomes_cell = $column . $total_incomes_row;
            $total_formula = '=' . $opening_balance_cell . '+' . $total_incomes_cell;

            $spreadsheet->getActiveSheet()
                ->setCellValue($column . $current_row, $total_formula);
        }

        foreach ($arr as $value) {
            //paint gray row
            $this->setDarkGrayFormat($value['column'] . $current_row, $spreadsheet);
            $this->setCurrencyFormat($value['column'] . $current_row, $spreadsheet);

            //make bold
            $spreadsheet->getActiveSheet()->getStyle($value['column'] . $current_row)->getFont()->setBold(true);
        }

        $current_row++;

        //BUDGET SECTION
        $headers_row = 2;
        $this->insertBudgetDataExcel($spreadsheet, $budget_data, $today_m, $headers_row, $current_row, $arr, $data_credit_notes_totals, $year);

        $disbursements_row = $current_row;

        $current_row++;

        //DIFFERENCE BETWEEN SOURCE AND APPLICATION
        $difference_row = $current_row;

        $spreadsheet->getActiveSheet()
            ->setCellValue($concept_col . $current_row, 'DIFFERENCE BETWEEN SOURCE AND APPLICATION');

        foreach (range($arr[1]['column'], $arr['ytd_total']['column']) as $column) {
            $total_of_funds_cell = $column . $total_of_funds_row;
            $disbursements_cell = $column . $disbursements_row;
            $total_formula = '=' . $total_of_funds_cell . '-' . $disbursements_cell;

            $spreadsheet->getActiveSheet()
                ->setCellValue($column . $current_row, $total_formula);
        }

        foreach ($arr as $value) {
            //paint gray row
            $this->setDarkGrayFormat($value['column'] . $current_row, $spreadsheet);
            $this->setCurrencyFormat($value['column'] . $current_row, $spreadsheet);

            //make bold
            $spreadsheet->getActiveSheet()->getStyle($value['column'] . $current_row)->getFont()->setBold(true);
        }

        $current_row++;

        //CLOSING BANK BALANCE 2020
        foreach ($bank_accounts as $bank_account) {
            foreach (range(1, 12) as $month) {
                $month_col = $arr[$month]['column'];
                $amount = $bank_account_balances[$month + 1][$bank_account->id_bank_account];

                //write amount for the current month
                $spreadsheet->getActiveSheet()
                    ->setCellValue($month_col . $current_row, $amount);
            }

            $spreadsheet->getActiveSheet()
                ->setCellValue($ytd_total_col . $current_row, $bank_account_balances[$today_m + 1][$bank_account->id_bank_account]);

            $spreadsheet->getActiveSheet()
                ->setCellValue($concept_col . $current_row, $bank_account_names[$bank_account->id_bank_account]);

            foreach ($arr as $value) {
                //paint yellow row
                $this->setYellowFormat($value['column'] . $current_row, $spreadsheet);
                $this->setCurrencyFormat($value['column'] . $current_row, $spreadsheet);
            }

            //collapse and group
            $spreadsheet->getActiveSheet()->getRowDimension($current_row)->setOutlineLevel(1);
            $spreadsheet->getActiveSheet()->getRowDimension($current_row)->setVisible(false);
            $spreadsheet->getActiveSheet()->getRowDimension($current_row)->setCollapsed(true);

            $current_row++;
        }

        $closing_balance_row = $current_row;

        $first_children_row = $current_row - count($bank_accounts);
        $last_children_row = $current_row - 1;

        foreach (range(1, 12) as $month) {
            $month_col = $arr[$month]['column'];

            //write amount for the current month
            $spreadsheet->getActiveSheet()
                ->setCellValue($month_col . $current_row, '=SUM(' . $month_col . $first_children_row . ':' . $month_col . $last_children_row . ')');
        }
        $spreadsheet->getActiveSheet()
            ->setCellValue($concept_col . $current_row, 'CLOSING BANK BALANCE 2020');

        $spreadsheet->getActiveSheet()
            ->setCellValue($ytd_total_col . $current_row, '=SUM(' . $ytd_total_col . $first_children_row . ':' . $ytd_total_col . $last_children_row . ')');

        foreach ($arr as $value) {
            //paint gray row
            $this->setDarkGrayFormat($value['column'] . $current_row, $spreadsheet);
            $this->setCurrencyFormat($value['column'] . $current_row, $spreadsheet);

            //make bold
            $spreadsheet->getActiveSheet()->getStyle($value['column'] . $current_row)->getFont()->setBold(true);
        }
        $current_row++;

        //GRAND TOTAL
        $grand_total_row = $current_row;

        $spreadsheet->getActiveSheet()
            ->setCellValue($concept_col . $current_row, 'GRAND TOTAL');

        foreach (range($arr[1]['column'], $ytd_total_col) as $column) {
            $closing_balance_cell = $column . '' . $closing_balance_row;
            $total_formula = '=' . $closing_balance_cell;

            $spreadsheet->getActiveSheet()
                ->setCellValue($column . $current_row, $total_formula);
        }

        foreach ($arr as $value) {
            //paint gray row
            $this->setDarkGrayFormat($value['column'] . $current_row, $spreadsheet);
            $this->setCurrencyFormat($value['column'] . $current_row, $spreadsheet);

            //make bold
            $spreadsheet->getActiveSheet()->getStyle($value['column'] . $current_row)->getFont()->setBold(true);
        }

        //DIFERENCIA / DIFFERENCE
        $current_row++;

        $spreadsheet->getActiveSheet()
            ->setCellValue($concept_col . $current_row, 'DIFERENCIA / DIFFERENCE');

        foreach (range($arr[1]['column'], $arr['ytd_total']['column']) as $column) {
            $difference_cell = $column . $difference_row;
            $closing_balance_cell = $column . $closing_balance_row;
            $total_formula = '=' . $difference_cell . '-' . $closing_balance_cell;

            $spreadsheet->getActiveSheet()
                ->setCellValue($column . $current_row, $total_formula);
        }

        foreach ($arr as $value) {
            //paint gray row
            $this->setDarkGrayFormat($value['column'] . $current_row, $spreadsheet);
            $this->setCurrencyFormat($value['column'] . $current_row, $spreadsheet);

            //make bold
            $spreadsheet->getActiveSheet()->getStyle($value['column'] . $current_row)->getFont()->setBold(true);
        }

        //autosize columns
        foreach (range($concept_col, $annual_budget_col) as $column) {
            $spreadsheet->getActiveSheet()->getColumnDimension($column)
                ->setAutoSize(true);
        }

        //hide unused months columns
        if ((12 - $today_m) > 0) {
            foreach (range($today_m + 1, 12) as $month) {
                $spreadsheet->getActiveSheet()->getColumnDimension($arr[$month]['column'])->setCollapsed(true);
                $spreadsheet->getActiveSheet()->getColumnDimension($arr[$month]['column'])->setVisible(false);
            }
        }
        // return;

        $spreadsheet->getActiveSheet()->getColumnDimension($available_col)->setWidth(18);


        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="budget-export.xlsx"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');

        // $this->conectDatabase(env('DB_DATABASE'));

        exit;
    }

    /**
     * [description]
     */
    protected function setGrayFormat($cell, $spreadsheet)
    {
        $spreadsheet->getActiveSheet()->getStyle($cell)->applyFromArray(
            [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['argb' => 'FFE5E5E5'],
                ],
            ]
        );
    }
    protected function setDarkGrayFormat($cell, $spreadsheet)
    {
        $spreadsheet->getActiveSheet()->getStyle($cell)->applyFromArray(
            [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['argb' => 'FFD9D9D9'],
                ],
            ]
        );
    }
    protected function setYellowFormat($cell, $spreadsheet)
    {
        $spreadsheet->getActiveSheet()->getStyle($cell)->applyFromArray(
            [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['argb' => 'FFEEECE1'],
                ],
            ]
        );
    }
    protected function setCurrencyFormat($cell, $spreadsheet)
    {
        $spreadsheet->getActiveSheet()
            ->getStyle($cell)
            ->getNumberFormat()
            ->setFormatCode('_($#,##0.00_)');
    }

    protected function setBalanceCurrencyFormat($cell, $spreadsheet)
    {
        $spreadsheet->getActiveSheet()
            ->getStyle($cell)
            ->getNumberFormat()
            ->setFormatCode('[Red]_($#,##0.00_);-_($#,##0.00_);_($#,##0.00_)');
    }
    protected function setBalanceCurrencyInverseFormat($cell, $spreadsheet)
    {
        $spreadsheet->getActiveSheet()
            ->getStyle($cell)
            ->getNumberFormat()
            ->setFormatCode('_($#,##0.00_);[Red]-_($#,##0.00_);_($#,##0.00_)');
    }

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
            //
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
                if ($bank_currencies[$bank_account] == 'USD') {
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

    /**
     * [getOpeningBankAccountBalances description]
     *
     * @param   [type]  $bank_accounts  [$bank_accounts description]
     * @param   [type]  $year           [$year description]
     * @param   [type]  $max_month      [$max_month description]
     *
     * @return  [type]                  [return description]
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
                $balance = $this->getBankAccountBalance($bank_account->id_bank_account, $date);
                // $b_a = BankAccount::find($bank_account);
                $balances[$bank_account->id_bank_account] = $balance;
            }
            $bank_account_balances[$month] = $balances;
        }
        if ($max_month >= 12)
            $balances = [];
        $date = ($year + 1) . '-01-01';
        foreach ($bank_accounts as $bank_account) {
            $balance = $this->getBankAccountBalance($bank_account->id_bank_account, $date);
            // $b_a = BankAccount::find($bank_account);
            $balances[$bank_account->id_bank_account] = $balance;
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
            $payments = Payment::where(DB::raw("YEAR(value_date)"), $year)
                ->where(DB::raw("MONTH(value_date)"), $i)
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

            $payments = Payment::where(DB::raw("YEAR(value_date)"), $year)
                ->where(DB::raw("MONTH(value_date)"), $i)
                ->where('bool_has_conversion', true)
                ->where('conversion_currency', 'USD')
                ->get();

            foreach ($payments as $payment) {

                $payment_details = PaymentDetail::where('id_payment', $payment->id_payment)
                    ->where(DB::raw("YEAR(date_from)"), $target_year)->get();

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

            $credit_notes = CreditNote::where(DB::raw("YEAR(date)"), $year)
                ->where(DB::raw("MONTH(date)"), $i)
                ->get();

            foreach ($credit_notes as $credit_note) {

                $payment_details = PaymentDetail::where('id_credit_note', $credit_note->id_credit_note)
                    ->where(DB::raw("YEAR(date_from)"), $target_year)->get();

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

            $payments = Payment::where(DB::raw("YEAR(deposit_date)"), $year)
                ->where(DB::raw("MONTH(deposit_date)"), $i)
                ->where('id_owner', $id_owner) //Dues Statements: 01 "Others"
                ->get();

            foreach ($payments as $payment) {
                $payment_details = PaymentDetail::where('id_concept', $id_concept)
                    ->where('id_payment', $payment->id_payment)
                    ->where(DB::raw("YEAR(date_from)"), $target_year)->get();
                foreach ($payment_details as $detail) {
                    $amount = $detail->amount_usd;
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
            $result[$month] = $e_r_adj->amount ?? 0
            ;
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
            $result[$month] = $e_r_adj->amount ?? 0;
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

    /**
     * [insertBudgetDataExcel description]
     *
     * @param   [type]  $spreadsheet               [$spreadsheet description]
     * @param   [type]  $budget_data               [$budget_data description]
     * @param   [type]  $today_m                   [$today_m description]
     * @param   [type]  $headers_row               [$headers_row description]
     * @param   [type]  $current_row               [$current_row description]
     * @param   [type]  $arr                       [$arr description]
     * @param   [type]  $data_credit_notes_totals  [$data_credit_notes_totals description]
     * @param   [type]  $year                      [$year description]
     *
     * @return  [type]                             [return description]
     */
    public function insertBudgetDataExcel($spreadsheet, $budget_data, $today_m, $headers_row, &$current_row, $arr, $data_credit_notes_totals, $year)
    {
        $today_m = $budget_data['today_m'];
        $budgets_result = $budget_data['budgets_result'];
        $expenses_result = $budget_data['expenses_result'];
        $budget_concepts_result = $budget_data['budget_concepts_result'];
        $budget_concepts_description = $budget_data['budget_concepts_description'];
        $budget_categories_result = $budget_data['budget_categories_result'];

        //Write headers
        foreach ($arr as $key => $value) {
            $spreadsheet->getActiveSheet()->setCellValue($value['column'] . $headers_row, $value['name']);
        }

        //columns
        $cols[] = $concept_col = $arr['concept']['column'];
        $cols[] = $annual_budget_col = $arr['annual_budget']['column'];
        $cols[] = $ytd_total_col = $arr['ytd_total']['column'];
        $cols[] = $available_col = $arr['available']['column'];

        //columns with same (sum) formula 
        $cols_header_totals[] = $annual_budget_col;
        $cols_header_totals[] = $ytd_total_col;

        $header_rows = [];
        // budget categories
        // Log::warning($budget_concepts_description);
        // Log::info($budgets_result);
        foreach ($budget_categories_result as $id_budget_category => $category_description) {

            if (isset($budget_concepts_result[$id_budget_category])) {

                //budget concepts
                foreach ($budget_concepts_result[$id_budget_category] as $id_budget_concept) {

                    //get budget values
                    if (isset($budget_concepts_description[$id_budget_concept])) {
                        $concept_description = $budget_concepts_description[$id_budget_concept];
                        $annual_budget = $budgets_result[$id_budget_concept]['amount'] ?? 0;

                        //create excel addresses
                        $first_month_cell = $arr[1]['column'] . $current_row;
                        $last_month_cell = $arr[12]['column'] . $current_row;
                        $ytd_total_cell = $ytd_total_col . $current_row;
                        $annual_budget_cell = $annual_budget_col . $current_row;
                        $available_cell = $available_col . $current_row;

                        //write cells
                        $spreadsheet->getActiveSheet()
                            ->setCellValue($concept_col . $current_row, $concept_description)
                            ->setCellValue($annual_budget_col . $current_row, $annual_budget);
                        $ytd_total_formula = '=SUM(' . $first_month_cell . ':' . $last_month_cell . ')';
                        $available_formula = '=' . $annual_budget_cell . '-' . $ytd_total_cell;
                        $spreadsheet->getActiveSheet()
                            ->setCellValue($ytd_total_col . $current_row, $ytd_total_formula)
                            ->setCellValue($available_col . $current_row, $available_formula);

                        //paint yellow row
                        foreach ($cols as $column) {
                            $this->setYellowFormat($column . $current_row, $spreadsheet);
                        }

                        //normal currency format
                        $currency_range = $first_month_cell . ':' . $annual_budget_cell;
                        $this->setCurrencyFormat($currency_range, $spreadsheet);

                        //currency format with red formating in negative numbers
                        $this->setBalanceCurrencyFormat($available_cell, $spreadsheet);
                        foreach (range(1, 12) as $month) {
                            $month_col = $arr[$month]['column'];

                            //paint yellow row
                            $this->setYellowFormat($month_col . $current_row, $spreadsheet);
                            $amount = $expenses_result[$id_budget_concept][$month];

                            $other_amount = 0;
                            // if($id_budget_concept == 54){//cantidad de credit notes se agregan a "10.10 Covid Contingency (cash)"
                            //     $other_amount = is_array($data_credit_notes_totals[$year][$month]) ? array_sum($data_credit_notes_totals[$year][$month]) : 0;
                            //     $amount += $other_amount;
                            // }

                            //write expense amount for the current month
                            $spreadsheet->getActiveSheet()
                                ->setCellValue($month_col . $current_row, $amount);
                        }

                        //collapse and group
                        $spreadsheet->getActiveSheet()->getRowDimension($current_row)->setOutlineLevel(1);
                        $spreadsheet->getActiveSheet()->getRowDimension($current_row)->setVisible(false);
                        $spreadsheet->getActiveSheet()->getRowDimension($current_row)->setCollapsed(true);
                        $current_row++;
                    }
                }

                //budget category header
                $spreadsheet->getActiveSheet()
                    ->setCellValue($concept_col . $current_row, $category_description);
                $spreadsheet->getActiveSheet()->getRowDimension($current_row)->setCollapsed(true);

                foreach ($cols as $column) {
                    //paint gray row
                    $this->setGrayFormat($column . $current_row, $spreadsheet);

                    //make bold
                    $spreadsheet->getActiveSheet()->getStyle($column . $current_row)->getFont()->setBold(true);
                }

                //save header rows to be used later
                $header_rows[] = $current_row;

                //get first and last row number of children
                $first_children_row = $current_row - count($budget_concepts_result[$id_budget_category]);
                $last_children_row = $current_row - 1;

                //get cells of other columns
                $ytd_total_cell = $ytd_total_col . $current_row;
                $annual_budget_cell = $annual_budget_col . $current_row;

                //create sum formulas
                foreach ($cols_header_totals as $column) {
                    $first_cell = $column . $first_children_row;
                    $last_cell = $column . $last_children_row;
                    $sum_formula = '=SUM(' . $first_cell . ':' . $last_cell . ')';
                    $spreadsheet->getActiveSheet()
                        ->setCellValue($column . $current_row, $sum_formula);
                }

                //create sum formulas (months)
                foreach (range(1, 12) as $month) {
                    $month_col = $arr[$month]['column'];

                    //paint gray row
                    $this->setGrayFormat($month_col . $current_row, $spreadsheet);
                    $first_cell = $month_col . $first_children_row;
                    $last_cell = $month_col . $last_children_row;

                    //make bold
                    $spreadsheet->getActiveSheet()->getStyle($month_col . $current_row)->getFont()->setBold(true);

                    //sum formula for months
                    $sum_formula = '=SUM(' . $first_cell . ':' . $last_cell . ')';
                    $spreadsheet->getActiveSheet()
                        ->setCellValue($month_col . $current_row, $sum_formula);
                }

                //(available) formula
                $available_formula = '=' . $annual_budget_cell . '-' . $ytd_total_cell;

                //write (available) formula
                $spreadsheet->getActiveSheet()
                    ->setCellValue($available_col . $current_row, $available_formula);

                //set currency format
                foreach (range($arr[1]['column'], $annual_budget_col) as $column) {
                    $this->setCurrencyFormat($column . $current_row, $spreadsheet);
                }
                $available_cell = $available_col . $current_row;
                $this->setBalanceCurrencyInverseFormat($available_cell, $spreadsheet);
                $current_row++;
            }
        }

        //set dark gray background for grand totals row
        foreach ($cols as $column) {
            $this->setDarkGrayFormat($column . $current_row, $spreadsheet);
            $spreadsheet->getActiveSheet()->getStyle($column . $current_row)->getFont()->setBold(true);
        }
        foreach (range(1, 12) as $month) {
            $month_col = $arr[$month]['column'];
            $this->setDarkGrayFormat($month_col . $current_row, $spreadsheet);

            //make bold
            $spreadsheet->getActiveSheet()->getStyle($month_col . $current_row)->getFont()->setBold(true);
        }

        //disbursements row
        $spreadsheet->getActiveSheet()
            ->setCellValue($concept_col . $current_row, 'DISBURSEMENTS');

        //declare cells
        $ytd_total_cell = $ytd_total_col . $current_row;
        $annual_budget_cell = $annual_budget_col . $current_row;

        //set formula for (available) column
        $available_formula = '=' . $annual_budget_cell . '-' . $ytd_total_cell;
        $spreadsheet->getActiveSheet()
            ->setCellValue($available_col . $current_row, $available_formula);
        //set sum formulas for disbursements row
        foreach (range($arr[1]['column'], $annual_budget_col) as $column) {
            $sum_formula = '=';
            foreach ($header_rows as $row) {
                $sum_formula .= '+' . $column . $row;
            }
            $spreadsheet->getActiveSheet()
                ->setCellValue($column . $current_row, $sum_formula);
            $this->setCurrencyFormat($column . $current_row, $spreadsheet);
        }

        $spreadsheet->getActiveSheet()->setAutoFilter('A' . $headers_row . ':' . $available_col . $headers_row);
        $spreadsheet->getActiveSheet()->freezePane('A3');

        //format as balance currency (available) column
        $available_cell = $available_col . $current_row;
        $this->setBalanceCurrencyInverseFormat($available_cell, $spreadsheet);
    }
}
