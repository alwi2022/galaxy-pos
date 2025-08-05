@extends('layouts.master')

@section('title')
    Pindah Cabang
@endsection

@section('content')
<div class="box">
    <div class="box-body">
        <form action="{{ route('user.update_cabang') }}" method="POST" class="form-horizontal">
            @csrf
            <div class="form-group">
                <label class="col-lg-2 control-label">Cabang Baru</label>
                <div class="col-lg-6">
                    <select name="id_cabang" class="form-control" required>
                        @foreach ($cabang as $c)
                            <option value="{{ $c->id_cabang }}" {{ auth()->user()->id_cabang == $c->id_cabang ? 'selected' : '' }}>
                                {{ $c->nama_cabang }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group text-right">
                <div class="col-lg-offset-2 col-lg-6">
                    <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
