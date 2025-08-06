<aside class="main-sidebar">
    <section class="sidebar">
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

        <ul class="sidebar-menu" data-widget="tree" style="transition: none;">
            <li>
                <a href="{{ route('dashboard') }}">
                    <i class="fa fa-dashboard"></i> <span>Dashboard</span>
                </a>
            </li>

            {{-- MASTER --}}
            @if (auth()->user()->level == 1)
            <li class="treeview">
                <a href="#"><i class="fa fa-folder"></i> <span>Master</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu">
                    <li><a href="{{ route('kategori.index') }}"><i class="fa fa-cube"></i> Kategori</a></li>
                    <li><a href="{{ route('produk.index') }}"><i class="fa fa-cubes"></i> Produk</a></li>
                    <li><a href="{{ route('member.index') }}"><i class="fa fa-id-card"></i> Member</a></li>
                    <li><a href="{{ route('supplier.index') }}"><i class="fa fa-truck"></i> Supplier</a></li>
                </ul>
            </li>
            @endif

            {{-- TRANSAKSI --}}
            @if (in_array(auth()->user()->level,[1,2]))
            <li class="treeview">
                <a href="#"><i class="fa fa-shopping-cart"></i> <span>Transaksi</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu">
                <li><a href="{{ route('transaksi.index') }}"><i class="fa fa-cart-arrow-down"></i> Transaksi Aktif</a></li>
                <li><a href="{{ route('transaksi.baru') }}"><i class="fa fa-plus-square"></i> Transaksi Baru</a></li>
                @if (auth()->user()->level == 1)
                    <li><a href="{{ route('pengeluaran.index') }}"><i class="fa fa-money"></i> Pengeluaran</a></li>
                    <li><a href="{{ route('pembelian.index') }}"><i class="fa fa-download"></i> Pembelian</a></li>
                    <li><a href="{{ route('penjualan.index') }}"><i class="fa fa-upload"></i> Penjualan</a></li>
                    @endif
                </ul>
            </li>
            @endif

            

            {{-- SERVIS --}}
            @if (in_array(auth()->user()->level, [1, 3]))
            <li class="treeview">
                <a href="#"><i class="fa fa-wrench"></i> <span>Servis</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu">
                    <li><a href="{{ route('servis.index') }}"><i class="fa fa-desktop"></i> Servis Komputer</a></li>
                </ul>
            </li>
            @endif

            {{-- LAPORAN --}}
            @if (auth()->user()->level == 1)
            <li><a href="{{ route('laporan.index') }}"><i class="fa fa-file-text"></i> <span>Laporan</span></a></li>

            {{-- SYSTEM --}}
            <li class="treeview">
                <a href="#"><i class="fa fa-cogs"></i> <span>System</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu">
                    <li><a href="{{ route('user.index') }}"><i class="fa fa-users"></i> User</a></li>
                    <li><a href="{{ route('setting.index') }}"><i class="fa fa-wrench"></i> Pengaturan</a></li>
                </ul>
            </li>

            {{-- CABANG --}}
            <li class="treeview">
                <a href="#"><i class="fa fa-building"></i> <span>Cabang</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu">
                    <li><a href="{{ route('user.pindah_cabang') }}"><i class="fa fa-exchange"></i> Pindah Cabang</a></li>
                    <li><a href="{{ route('cabang.index') }}"><i class="fa fa-sitemap"></i> Data Cabang</a></li>
                </ul>
            </li>
            @endif
        </ul>
    </section>
</aside>
