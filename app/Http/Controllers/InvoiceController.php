<?php

namespace App\Http\Controllers;

use App\Traits\InvoiceRoute;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use InvoiceRoute;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index() {
        return view('invoices.index');
    }
}
