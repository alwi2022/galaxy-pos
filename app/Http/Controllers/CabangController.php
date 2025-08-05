<?php
// app/Http/Controllers/CabangController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cabang;

class CabangController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('cabang.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    public function data()
{
    $cabang = Cabang::orderBy('nama_cabang')->get();

    return datatables()
        ->of($cabang)
        ->addIndexColumn()
        ->addColumn('aksi', function ($cabang) {
            return '
                <div class="btn-group">
                    <button class="btn btn-xs btn-info" onclick="editForm('. $cabang->id_cabang .')"><i class="fa fa-edit"></i></button>
                    <button class="btn btn-xs btn-danger" onclick="deleteData('. $cabang->id_cabang .')"><i class="fa fa-trash"></i></button>
                </div>
            ';
        })
        ->rawColumns(['aksi'])
        ->make(true);
}


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama_cabang' => 'required|string|max:100',
            'alamat' => 'nullable|string',
            'telepon' => 'nullable|string|max:20',
        ]);
    
        Cabang::create($request->all());
    
        return back()->with('success', 'Cabang berhasil ditambahkan');
    }
    
    

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $cabang = Cabang::findOrFail($id);
        return response()->json($cabang);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_cabang' => 'required|string|max:100',
            'alamat' => 'nullable|string',
            'telepon' => 'nullable|string|max:20',
        ]);
    
        $cabang = Cabang::findOrFail($id);
        $cabang->update($request->all());
    
        return response()->json(['message' => 'Cabang berhasil diperbarui']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $cabang = Cabang::findOrFail($id);
        $cabang->delete();
    
        return response()->json(['message' => 'Cabang berhasil dihapus']);
    }
}
