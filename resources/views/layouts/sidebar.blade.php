@php
    $level = (int)auth()->user()->level;
    $module = request()->route('module');
    $groups = [
        ['title'=>'Master','icon'=>'folder','levels'=>[1],'items'=>[
            ['title'=>'Kategori','icon'=>'cube','route'=>'kategori.index','active'=>request()->routeIs('kategori.*')],
            ['title'=>'Produk','icon'=>'cubes','route'=>'produk.index','active'=>request()->routeIs('produk.*')],
            ['title'=>'Member','icon'=>'id-card','route'=>'member.index','active'=>request()->routeIs('member.*')],
            ['title'=>'Supplier','icon'=>'truck','route'=>'supplier.index','active'=>request()->routeIs('supplier.*')],
            ['title'=>'Rekening Bank','icon'=>'bank','module'=>'rekening-bank'],
        ]],
        ['title'=>'Transaksi','icon'=>'shopping-cart','levels'=>[1,2],'items'=>[
            ['title'=>'Transaksi Aktif','icon'=>'cart-arrow-down','route'=>'transaksi.index','active'=>request()->routeIs('transaksi.index','transaksi_detail.*')],
            ['title'=>'Transaksi Baru','icon'=>'plus-square','route'=>'transaksi.baru','active'=>request()->routeIs('transaksi.baru')],
            ['title'=>'Penjualan','icon'=>'upload','route'=>'penjualan.index','levels'=>[1],'active'=>request()->routeIs('penjualan.*')],
            ['title'=>'Pembelian','icon'=>'download','route'=>'pembelian.index','levels'=>[1],'active'=>request()->routeIs('pembelian.*','pembelian_detail.*')],
        ]],
        ['title'=>'Persediaan','icon'=>'cubes','levels'=>[1],'items'=>[
            ['title'=>'Kartu Stok & Opname','icon'=>'list-alt','module'=>'stok'],
            ['title'=>'Retur Barang','icon'=>'exchange','module'=>'retur'],
        ]],
        ['title'=>'Servis','icon'=>'wrench','levels'=>[1,3],'items'=>[
            ['title'=>'Servis Komputer','icon'=>'desktop','route'=>'servis.index','active'=>request()->routeIs('servis.*')],
            ['title'=>'Pembayaran Servis','icon'=>'credit-card','module'=>'piutang-servis','levels'=>[1]],
        ]],
        ['title'=>'Keuangan','icon'=>'money','levels'=>[1,2],'items'=>[
            ['title'=>'Utang & Piutang','icon'=>'credit-card','route'=>'pembayaran.index','active'=>request()->routeIs('pembayaran.*')],
            ['title'=>'Kas, Bank & Modal','icon'=>'bank','module'=>'kas','levels'=>[1]],
            ['title'=>'Pinjaman & Cicilan','icon'=>'handshake-o','module'=>'pinjaman','levels'=>[1]],
            ['title'=>'Pendapatan Lain','icon'=>'line-chart','route'=>'pendapatan-lain.index','levels'=>[1],'active'=>request()->routeIs('pendapatan-lain.*')],
            ['title'=>'Biaya Operasional','icon'=>'money','route'=>'pengeluaran.index','levels'=>[1],'active'=>request()->routeIs('pengeluaran.*')],
        ]],
        ['title'=>'Akuntansi','icon'=>'book','levels'=>[1],'items'=>[
            ['title'=>'Inventaris & Penyusutan','icon'=>'desktop','module'=>'aset'],
            ['title'=>'Pembayaran di Muka','icon'=>'calendar','module'=>'akrual'],
            ['title'=>'Rekonsiliasi Bank','icon'=>'balance-scale','module'=>'rekonsiliasi'],
            ['title'=>'Pajak & PPN','icon'=>'file-text-o','module'=>'pajak'],
            ['title'=>'Buku Besar & Tutup Buku','icon'=>'book','module'=>'akuntansi'],
        ]],
        ['title'=>'Laporan','icon'=>'file-text','levels'=>[1],'items'=>[
            ['title'=>'Ringkasan Kas','icon'=>'money','tab'=>'ringkasan'],
            ['title'=>'Laporan Penjualan','icon'=>'upload','tab'=>'penjualan'],
            ['title'=>'Laporan Pembelian','icon'=>'download','tab'=>'pembelian'],
            ['title'=>'Laporan per Barang','icon'=>'cube','tab'=>'produk'],
            ['title'=>'Laporan per Kategori','icon'=>'folder','tab'=>'kategori'],
            ['title'=>'Laba Rugi','icon'=>'line-chart','report'=>'profit'],
            ['title'=>'Neraca','icon'=>'balance-scale','report'=>'balance'],
            ['title'=>'Arus Kas','icon'=>'exchange','report'=>'cash'],
            ['title'=>'Laporan PPN','icon'=>'file-text-o','report'=>'tax'],
        ]],
        ['title'=>'System','icon'=>'cogs','levels'=>[1],'items'=>[
            ['title'=>'User','icon'=>'users','route'=>'user.index','active'=>request()->routeIs('user.index','user.create','user.edit','user.show')],
            ['title'=>'Pengaturan','icon'=>'wrench','route'=>'setting.index','active'=>request()->routeIs('setting.*')],
        ]],
        ['title'=>'Cabang','icon'=>'building','levels'=>[1],'items'=>[
            ['title'=>'Pindah Cabang','icon'=>'exchange','route'=>'user.pindah_cabang','active'=>request()->routeIs('user.pindah_cabang','user.update_cabang')],
            ['title'=>'Data Cabang','icon'=>'sitemap','route'=>'cabang.index','active'=>request()->routeIs('cabang.*')],
        ]],
    ];
@endphp
<aside class="main-sidebar">
    <section class="sidebar">
        <div class="user-panel">
            <div class="pull-left image"><img src="{{ url(auth()->user()->foto ?? '') }}" class="img-circle img-profil" alt="User Image"></div>
            <div class="pull-left info"><p>{{ auth()->user()->name }}</p><small>{{ auth()->user()->cabang->nama_cabang ?? 'Tanpa Cabang' }}</small><a href="#"><i class="fa fa-circle text-success"></i> Online</a></div>
        </div>
        <ul class="sidebar-menu" data-widget="tree" style="transition: none;">
            <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><a href="{{ route('dashboard') }}"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a></li>
            @foreach($groups as $group)
                @continue(!in_array($level,$group['levels'],true))
                @php
                    $items = collect($group['items'])->filter(fn($item)=>in_array($level,$item['levels']??$group['levels'],true))->map(function($item) use($module) {
                        if(isset($item['module'])) {
                            $item['url'] = route('business.index',$item['module']);
                            $item['active'] = request()->routeIs('business.index') && $module === $item['module'];
                        } elseif(isset($item['tab'])) {
                            $item['url'] = route('laporan.index',['tab'=>$item['tab']]);
                            $item['active'] = request()->routeIs('laporan.index') && request('tab','ringkasan') === $item['tab'];
                        } elseif(isset($item['report'])) {
                            $item['url'] = route('laporan.keuangan',$item['report']);
                            $item['active'] = request()->routeIs('laporan.keuangan') && request()->route('report') === $item['report'];
                        } else {
                            $item['url'] = route($item['route']);
                        }
                        return $item;
                    });
                    $open = $items->contains('active',true);
                @endphp
                <li class="treeview {{ $open ? 'active menu-open' : '' }}">
                    <a href="#"><i class="fa fa-{{ $group['icon'] }}"></i> <span>{{ $group['title'] }}</span><span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a>
                    <ul class="treeview-menu">
                        @foreach($items as $item)
                        <li class="{{ $item['active'] ? 'active' : '' }}"><a href="{{ $item['url'] }}" @isset($item['tab']) data-report-tab="{{ $item['tab'] }}" @endisset><i class="fa fa-{{ $item['icon'] }}"></i> {{ $item['title'] }}</a></li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    </section>
</aside>
