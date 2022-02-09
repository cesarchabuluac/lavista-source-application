@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Source and Application</div>

                <div class="card-body">
                    @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        {{ session('status') }}
                    </div>
                    @endif
                    <form method="get" action="{{ route('budgets.index') }}">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="residencias">Residencias</label>
                                    <select class="form-control" id="residencias" name="residencias" required>
                                        <option value="">Selecciona una opción</option>
                                        <option value="hoadmin_la_vista">La vista</option>
                                        <option value="hoadmin_antigua">Antigua</option>
                                        <option value="hoadmin_villa_la_paloma">Villa La Paloma</option>
                                        <option value="hoadmin_cresta">Cresta del Mar</option>
                                        <option value="hoadmin_libertad_1065">Libertad 1065</option>
                                    </select>
                                </div>
                            </div>
                            <!-- <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="end_date">Fecha Fin</label>
                                    <input value="{{ \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d') }}" name="end_date" type="date" id="end_date">
                                </div>
                            </div> -->
                            <div class="col-sm-12">
                                <button type="submit" class="btn btn-primary">Crear</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection