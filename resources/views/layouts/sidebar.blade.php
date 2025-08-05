    <!-- resources/views/layouts/sidebar.blade.php -->
    <!-- Left side column. contains the logo and sidebar -->
    <aside class="main-sidebar">
        <!-- sidebar: style can be found in sidebar.less -->
        <section class="sidebar">
            <!-- Sidebar user panel -->
            <div class="user-panel">
                <div class="pull-left image">
                    <img src="{{ url(auth()->user()->foto ?? '') }}" class="img-circle img-profil" alt="User Image">
                </div>
                <div class="pull-left info">
                    <p>{{ auth()->user()->name }}</p>
                    <small>{{ auth()->user()->cabang->nama_cabang ?? 'Tanpa Cabang' }}</small>
                    <a href="#"><i class="fa fa-circle text-success"></i> Online</a>
                </div>
            </div>
            
            <!-- /.search form -->
            <!-- sidebar menu: : style can be found in sidebar.less -->
            <ul class="sidebar-menu" data-widget="tree">
            <li>
    <a href="{{ route('dashboard') }}">
        <i class="fa fa-dashboard"></i> <span>Dashboard</span>
    </a>
</li>

{{-- Role ADMIN --}}
@if (auth()->user()->level == 1)
    <li class="header">MASTER</li>
    <li><a href="{{ route('kategori.index') }}"><i class="fa fa-cube"></i> <span>Kategori</span></a></li>
    <li><a href="{{ route('produk.index') }}"><i class="fa fa-cubes"></i> <span>Produk</span></a></li>
    <li><a href="{{ route('member.index') }}"><i class="fa fa-id-card"></i> <span>Member</span></a></li>
    <li><a href="{{ route('supplier.index') }}"><i class="fa fa-truck"></i> <span>Supplier</span></a></li>
    <li><a href="{{ route('user.pindah_cabang') }}"><i class="fa fa-exchange"></i> <span>Pindah Cabang</span></a></li>
    <li><a href="{{ route('cabang.index') }}"><i class="fa fa-building"></i> <span>Cabang</span></a></li>
@endif

{{-- Role ADMIN dan KASIR --}}

@if (auth()->user()->level == 1)
    <li class="header">TRANSAKSI</li>
    <li><a href="{{ route('pengeluaran.index') }}"><i class="fa fa-money"></i> <span>Pengeluaran</span></a></li>
    <li><a href="{{ route('pembelian.index') }}"><i class="fa fa-download"></i> <span>Pembelian</span></a></li>
    <li><a href="{{ route('penjualan.index') }}"><i class="fa fa-upload"></i> <span>Penjualan</span></a></li>
@endif

{{-- Role ADMIN, KASIR, TEKNISI --}}
@if (in_array(auth()->user()->level, [1,3]))
    <li><a href="{{ route('servis.index') }}"><i class="fa fa-wrench"></i> <span>Servis Komputer</span></a></li>
@endif

{{-- Role KASIR saja --}}
@if (in_array(auth()->user()->level, [1,2]))
    <li><a href="{{ route('transaksi.index') }}"><i class="fa fa-cart-arrow-down"></i> <span>Transaksi Aktif</span></a></li>
    <li><a href="{{ route('transaksi.baru') }}"><i class="fa fa-cart-arrow-down"></i> <span>Transaksi Baru</span></a></li>
@endif

{{-- Laporan hanya ADMIN --}}
@if (auth()->user()->level == 1)
    <li class="header">REPORT</li>
    <li><a href="{{ route('laporan.index') }}"><i class="fa fa-file-pdf-o"></i> <span>Laporan</span></a></li>

    <li class="header">SYSTEM</li>
    <li><a href="{{ route('user.index') }}"><i class="fa fa-users"></i> <span>User</span></a></li>
    <li><a href="{{ route('setting.index') }}"><i class="fa fa-cogs"></i> <span>Pengaturan</span></a></li>
@endif


                
            </ul>
        </section>
        <!-- /.sidebar -->
    </aside>