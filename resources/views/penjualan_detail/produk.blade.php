<div class="modal fade" id="modal-produk" tabindex="-1" role="dialog" aria-labelledby="modal-produk">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Pilih Produk</h4>
            </div>
            <div class="modal-body">
                <table class="table table-striped table-bordered table-produk">
                    <thead>
                    <th width="5%">No</th>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Harga Jual</th>
                            <th>Stok</th>
                            <th width="10%"><i class="fa fa-cog"></i></th>
                    </thead>
                    <tbody>
                            @foreach ($produk as $key => $item)
                                <tr class="produk-row" data-nama="{{ strtolower($item->nama_produk) }}" 
                                    data-kode="{{ strtolower($item->kode_produk) }}">
                                    <td width="5%">{{ $key+1 }}</td>
                                    <td>
                                        <span class="label label-success">{{ $item->kode_produk }}</span>
                                    </td>
                                    <td>{{ $item->nama_produk }}</td>
                                    <td>Rp. {{ number_format($item->harga_jual, 0, ',', '.') }}</td>
                                    <td>
                                        <span>
                                            {{ $item->stok }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($item->stok > 0)
                                            <a href="#" class="btn btn-primary btn-xs btn-flat"
                                               onclick="pilihProduk('{{ $item->id_produk }}', '{{ $item->kode_produk }}')"
                                               title="Pilih produk ini">
                                                <i class="fa fa-check-circle"></i>
                                                Pilih
                                            </a>
                                        @else
                                            <span class="btn btn-default btn-xs btn-flat disabled">
                                                <i class="fa fa-times"></i>
                                                Habis
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>