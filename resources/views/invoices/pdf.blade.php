<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Invoice</title>
    <style>
        *, :after, :before {
            -webkit-box-sizing: border-box;
            -moz-box-sizing: border-box;
            box-sizing: border-box
        }
        html {
            font-size: 10px;
            -webkit-tap-highlight-color: rgba(0, 0, 0, 0)
        }

        body {
            font-family: "Helvetica", sans-serif;
            font-size: 14px;
            line-height: 1.42857143;
            color: #333;
            background-color: #fff
        }
        .data-person p {
            margin: 0;
        }
        table.custom-table, table.custom-table td, table.custom-table th {
            border: 1px solid black;
        }
        table.table-prices {
            width: 100%; border-collapse: collapse;
        }
        table.table-prices td.row {
            border-bottom: 1px solid #e9e9e9;
        }
    </style>
</head>
<body>
    <table>
        <tbody>
            <tr>
                <td>
                    <img width="175" src="{{ asset('/assets/img/logo.png') }}" alt="">
                </td>
                <td style="width: 325px">
                    <div style="text-align: center">
                        <p style="margin:0; font-size:11px"><strong>Asociación de Condóminos de La Vista, AC.</strong></p>
                        <p style="margin:0; font-size:11px">RFC: ACV0902203N8</p>
                        <p style="margin:0; font-size:11px">Boulevard La Vista S/N, Colonia El Tezal, entre</p>
                        <p style="margin:0; font-size:11px">Calle Transpeninsular y Calle Camino a Rancho Paraiso</p>

                        <p style="margin:0; font-size:11px">Cabo San Lucas, Los Cabos, Baja California Sur, CP 23454</p>
                        <p style="margin:0; font-size:11px">E-mail: admonlavista@gmail.com</p>
                    </div>
                </td>
                <td style="vertical-align: top; padding-top: 5px;">
                    <div style="width: 176px">
                        <div style="width:100%; text-align:center">
                            <h4 style="border:1px solid black; border-radius:10px 10px 0px 0px; padding-bottom:5px; font-size:14px; margin-bottom: 2px; margin-top: 0">RECIBO / INVOICE</h4>
                        </div>
                        <div style="width:100%">
                            <div style="border-radius:0px 0px 10px 10px; border: 1px solid black; padding:5px;">
                                <p style="text-align: center; margin: 0">{{ $invoice->id_invoice }}</p>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <div style="width: 100%">
                        <p style="text-align: right;margin: 0">Cabo San Lucas, B.C.S.,  {{ $invoice->print_date }}</p>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <div class="data-person">
                        <p style="margin:0; font-size:11px"><strong>To:</strong></p>
                        <p style="margin:0; font-size:11px">{{ $invoice->owner->name }}</p>
                        <p style="margin:0; font-size:11px">{{ $invoice->owner->street_name }} {{ $invoice->owner->unit }}</p>
                        <p style="margin:0; font-size:11px">La Vista</p>
                        <p style="margin:0; font-size:11px">Cabo San Lucas, BCS 23454</p>
                        <p style="margin:0; font-size:11px">Mexico</p>
                        <p style="margin:0; font-size:11px"><u>{{ $invoice->owner->email }}</u></p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
    <table class="table-prices">
        <tr style="background-color: #f9f9f9">
            <th class ="th2" style="text-align: left">&nbsp;Fecha / Date</th>
            <th class ="th2" style="text-align: left">&nbsp;&nbsp;Descripción / Description</th>
            <th class ="th2" style="text-align:right;">Cuota / Rate&nbsp;</th>
        </tr>
        @foreach($invoice->feeStatements as $feeStatement)
            <tr>
                <td class="row" style="padding-left: 4px; font-size: 12px">{{ $feeStatement->print_date }}</td>
                <td class="row" style="font-size: 12px">{{ $feeStatement->concept->description }}</td>
                <td class="row" style="text-align: right;font-size: 12px">${{ number_format($feeStatement->absolute_amount, 2) }}</td>
            </tr>
        @endforeach
        <tr style="background-color: #fcf8e3;">
            <td></td>
            <td>Total</td>
            <td style="text-align: right"><strong>${{ number_format($invoice->feeStatements->sum('absolute_amount'), 2) }}</strong></td>
        </tr>
    </table>
    <div style="height: 300px"></div>
    <table style="width: 100%; font-family:sans-serif;border-collapse: collapse;">
        <tr>
            <td style="width: 40%;border:1px solid red; border-collapse: collapse;">
                <div style="font-size: 14px; text-align: center; border-bottom: 1px solid red; border-collapse: collapse; padding: 5px; font-weight:bold;">
                    Depositos a: / Deposit to:
                </div>
                <div style="font-size: 18px; padding: 5px 5px 5px 5px;">
                    <p style="margin:0; font-size:11px">Bancomer (pesos)</p>
                    <p style="margin:0; font-size:11px">No. Cuenta / Bank Account: 016536.5716</p>
                    <p style="margin:0; font-size:11px">Clabe / Wire transfer: 01.2040.0016.5365.7161</p>
                </div>
            </td>
            <td style="width: 60%;">
                <table class="custom-table" style="width: 100%;border-collapse: collapse;">
                    <tr>
                        <td style="font-size: 12px; font-weight:bold;text-align: left;">
                            Subtotal
                        </td>
                        <td style="font-size: 12px; font-weight:bold;text-align: right">
                            ${{ number_format($invoice->feeStatements->sum('absolute_amount'), 2) }} MX
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 12px; font-weight:bold;text-align: left;">
                            Saldo previo / Balance Forwarded
                        </td>
                        <td style="font-size: 12px; font-weight:bold;text-align: right">
                            ${{ number_format($invoice->balance, 2) }}MX
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 12px; font-weight:bold;text-align: left;">
                            Pago del mes / This month payment
                        </td>
                        <td style="font-size: 12px; font-weight:bold;text-align: right">
                            ${{ number_format($invoice->credit, 2) }} MX
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 12px; font-weight:bold;text-align: left;">
                            {{ $invoice->label }}
                        </td>
                        <td style="font-size: 12px; font-weight:bold;text-align: right">
                            ${{ number_format($invoice->balance + $invoice->amount, 2) }} MX
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <table>
        <tr>
            <td colspan="2">
                <p style="font-size:11px;margin:0">No olvide agregar referencia / Do not forget to add reference number</p>
            </td>
        </tr>
    </table>
</body>
</html>
