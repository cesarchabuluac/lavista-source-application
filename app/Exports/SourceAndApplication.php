<?php

namespace App\Exports;

use App\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SourceAndApplication implements FromCollection, WithProperties, WithDrawings, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return User::all();
    }

    public function headings(): array
    {
        //Put Here Header Name That you want in your excel sheet 
        return [
            'Name',
            'Father Name',
            'Mother Name',
            'Gender',
            'Opted Language',
            'Corresponding Address'
        ];
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('This is my logo');
        $drawing->setPath(public_path('assets/img/logo.png'));
        $drawing->setHeight(90);
        $drawing->setHeight(90);
        $drawing->setOffsetX(120);
        // $drawing->setCoordinates('B3');

        return $drawing;
    }

    public function properties(): array
    {
        return [
            'creator'        => 'Oviedo Sucesores 2020',
            'lastModifiedBy' => 'Oviedo Sucesores',
            'title'          => 'Source And Application',
            'description'    => 'HOA Admin Generated Document',
            'subject'        => 'OSU Management Report',
            'keywords'       => 'Office 2007 openxml php',
            'category'       => 'OSU Reports',
        ];
    }
}
