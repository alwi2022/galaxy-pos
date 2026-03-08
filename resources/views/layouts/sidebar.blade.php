<aside class="main-sidebar">
    <section class="sidebar">
        @php
            $masterOpen = request()->routeIs('kategori.*', 'produk.*', 'member.*', 'supplier.*');
            $transaksiOpen = request()->routeIs('transaksi.*', 'pengeluaran.*', 'pembelian.*', 'penjualan.*');
            $servisOpen = request()->routeIs('servis.*');
            $systemOpen = request()->routeIs('user.*', 'setting.*');
            $cabangOpen = request()->routeIs('cabang.*', 'user.pindah_cabang', 'user.update_cabang');
        @endphp

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
            <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <a href="{{ route('dashboard') }}">
                    <i class="fa fa-dashboard"></i> <span>Dashboard</span>
                </a>
            </li>

            {{-- MASTER --}}
            @if (auth()->user()->level == 1)
            <li class="treeview {{ $masterOpen ? 'active menu-open' : '' }}">
                <a href="#"><i class="fa fa-folder"></i> <span>Master</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu">
                    <li class="{{ request()->routeIs('kategori.*') ? 'active' : '' }}"><a href="{{ route('kategori.index') }}"><i class="fa fa-cube"></i> Kategori</a></li>
                    <li class="{{ request()->routeIs('produk.*') ? 'active' : '' }}"><a href="{{ route('produk.index') }}"><i class="fa fa-cubes"></i> Produk</a></li>
                    <li class="{{ request()->routeIs('member.*') ? 'active' : '' }}"><a href="{{ route('member.index') }}"><i class="fa fa-id-card"></i> Member</a></li>
                    <li class="{{ request()->routeIs('supplier.*') ? 'active' : '' }}"><a href="{{ route('supplier.index') }}"><i class="fa fa-truck"></i> Supplier</a></li>
                </ul>
            </li>
            @endif

            {{-- TRANSAKSI --}}
            @if (in_array(auth()->user()->level,[1,2]))
            <li class="treeview {{ $transaksiOpen ? 'active menu-open' : '' }}">
                <a href="#"><i class="fa fa-shopping-cart"></i> <span>Transaksi</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu">
                <li class="{{ request()->routeIs('transaksi.index') ? 'active' : '' }}"><a href="{{ route('transaksi.index') }}"><i class="fa fa-cart-arrow-down"></i> Transaksi Aktif</a></li>
                <li class="{{ request()->routeIs('transaksi.baru') ? 'active' : '' }}"><a href="{{ route('transaksi.baru') }}"><i class="fa fa-plus-square"></i> Transaksi Baru</a></li>
                @if (auth()->user()->level == 1)
                    <li class="{{ request()->routeIs('pengeluaran.*') ? 'active' : '' }}"><a href="{{ route('pengeluaran.index') }}"><i class="fa fa-money"></i> Pengeluaran</a></li>
                    <li class="{{ request()->routeIs('pembelian.*', 'pembelian_detail.*') ? 'active' : '' }}"><a href="{{ route('pembelian.index') }}"><i class="fa fa-download"></i> Pembelian</a></li>
                    <li class="{{ request()->routeIs('penjualan.*') ? 'active' : '' }}"><a href="{{ route('penjualan.index') }}"><i class="fa fa-upload"></i> Penjualan</a></li>
                    @endif
                </ul>
            </li>
            @endif

            

            {{-- SERVIS --}}
            @if (in_array(auth()->user()->level, [1, 3]))
            <li class="treeview {{ $servisOpen ? 'active menu-open' : '' }}">
                <a href="#"><i class="fa fa-wrench"></i> <span>Servis</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu">
                    <li class="{{ request()->routeIs('servis.*') ? 'active' : '' }}"><a href="{{ route('servis.index') }}"><i class="fa fa-desktop"></i> Servis Komputer</a></li>
                </ul>
            </li>
            @endif

            {{-- LAPORAN --}}
            @if (auth()->user()->level == 1)
            <li class="{{ request()->routeIs('laporan.*') ? 'active' : '' }}"><a href="{{ route('laporan.index') }}"><i class="fa fa-file-text"></i> <span>Laporan</span></a></li>

            {{-- SYSTEM --}}
            <li class="treeview {{ $systemOpen ? 'active menu-open' : '' }}">
                <a href="#"><i class="fa fa-cogs"></i> <span>System</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu">
                    <li class="{{ request()->routeIs('user.index', 'user.create', 'user.edit', 'user.show') ? 'active' : '' }}"><a href="{{ route('user.index') }}"><i class="fa fa-users"></i> User</a></li>
                    <li class="{{ request()->routeIs('setting.*') ? 'active' : '' }}"><a href="{{ route('setting.index') }}"><i class="fa fa-wrench"></i> Pengaturan</a></li>
                </ul>
            </li>

            {{-- CABANG --}}
            <li class="treeview {{ $cabangOpen ? 'active menu-open' : '' }}">
                <a href="#"><i class="fa fa-building"></i> <span>Cabang</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu">
                    <li class="{{ request()->routeIs('user.pindah_cabang', 'user.update_cabang') ? 'active' : '' }}"><a href="{{ route('user.pindah_cabang') }}"><i class="fa fa-exchange"></i> Pindah Cabang</a></li>
                    <li class="{{ request()->routeIs('cabang.*') ? 'active' : '' }}"><a href="{{ route('cabang.index') }}"><i class="fa fa-sitemap"></i> Data Cabang</a></li>
                </ul>
            </li>
            @endif
        </ul>
    </section>
</aside>
