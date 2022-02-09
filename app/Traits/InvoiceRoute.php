<?php


namespace App\Traits;


use App\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ZipArchive;
use PDF;

trait InvoiceRoute {

    public function generateZip(Request  $request) {
        ini_set('max_execution_time', 500);
        $start_date = $request->input('start_date') ?? Carbon::now()->startOfMonth();
        $end_date = $request->input('end_date') ?? Carbon::now()->endOfMonth();
        $time = time();
        $folderName = public_path('process-pdf/'.$time);
        mkdir($folderName, 0755, true);
        $invoices = Invoice::with('owner', 'feeStatements.concept')
            ->whereBetween('date', [$start_date, $end_date])
            ->orderBy('date', 'desc')
            ->get();
        $files = [];
        foreach ($invoices as $invoice) {
            $pdf = $this->makePdf($invoice);
            $fileName = Str::slug($invoice->owner->unit).".pdf";
            array_push($files, $fileName);
            file_put_contents("$folderName/$fileName", $pdf);
            chmod("$folderName/$fileName", 0644);
        }
        $zip = new \ZipArchive();
        $zipFileName = $folderName.'/la-vista.zip';
        $zip->open($zipFileName, \ZipArchive::CREATE);
        foreach ($files as $file) {
            $zip->addFile("$folderName/$file", $file);
        }
        $zip->close();
        $headers = array(
            'Content-Type: application/pdf',
        );
        return response()->download($zipFileName, 'la-vista.zip', $headers);
    }

    private function makePdf(Invoice $invoice) {
        $data = [
            'invoice' => $invoice
        ];
        return PDF::loadView('invoices.pdf', $data)->output();
    }
}
