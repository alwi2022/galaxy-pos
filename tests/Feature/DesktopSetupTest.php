<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DesktopSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_desktop_setup_is_unavailable_in_the_web_application(): void
    {
        config(['desktop.enabled'=>false]);
        $this->get('/desktop/setup')->assertNotFound();
        $this->post('/desktop/setup',[])->assertNotFound();
        $this->artisan('desktop:initialize')->assertExitCode(1);
    }

    public function test_first_start_creates_only_the_store_and_its_administrator(): void
    {
        config(['desktop.enabled'=>true]);
        $this->get('/login')->assertRedirect(route('desktop.setup'));
        $this->get('/desktop/setup')->assertOk()->assertSee('Pengaturan Awal');
        $data=['store_name'=>'Toko Lokal','name'=>'Pemilik Toko','email'=>'pemilik@example.test','password'=>'Local-Test-123','password_confirmation'=>'Local-Test-123'];
        $this->post('/desktop/setup',$data)->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseCount('users',1);
        $this->assertDatabaseCount('cabang',1);
        $this->assertDatabaseCount('produk',0);
        $this->assertDatabaseHas('setting',['nama_perusahaan'=>'Toko Lokal','ppn_default'=>0]);
        $this->assertDatabaseHas('users',['email'=>'pemilik@example.test','level'=>1]);
        $this->assertTrue(Hash::check('Local-Test-123',DB::table('users')->value('password')));
        $this->post('/desktop/setup',$data)->assertStatus(409);
        $this->assertDatabaseCount('users',1);
    }

    public function test_invalid_setup_does_not_create_partial_store_data(): void
    {
        config(['desktop.enabled'=>true]);
        $this->post('/desktop/setup',['store_name'=>'Toko','email'=>'invalid','password'=>'123'])->assertSessionHasErrors(['name','email','password']);
        $this->assertDatabaseCount('users',0);
        $this->assertDatabaseCount('cabang',0);
        $this->assertDatabaseCount('setting',0);
    }
}
