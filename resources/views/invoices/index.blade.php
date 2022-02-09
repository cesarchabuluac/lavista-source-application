@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">{{ __('Invoices') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                        <form method="get" action="{{ route('generate.multiple.pdf') }}">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="start_date">Fecha Inicio</label>
                                        <input value="{{ \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d') }}" name="start_date" type="date" id="start_date">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="end_date">Fecha Fin</label>
                                        <input value="{{ \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d') }}" name="end_date" type="date" id="end_date">
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <button type="submit" class="btn btn-info">Descargar ZIP Invoices</button>
                                </div>
                            </div>
                        </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
