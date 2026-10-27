const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {initializeData,environment,command,startServer} = require('../runtime.cjs');

async function main() {
    const base = path.resolve(__dirname,'..');
    const directory = path.join(base,'.test-data','Uji Desktop '+Date.now());
    const backend = path.join(base,'.staging/backend');
    const php = path.join(base,'.staging/php');
    initializeData(directory);
    const log = path.join(directory,'storage/logs/smoke.log');
    const env = environment(directory,'http://127.0.0.1','','');
    await command(php,backend,env,['desktop:initialize'],log);
    const server = await startServer(php,backend,directory,log);
    try {
        const denied = await fetch(server.origin+'/login');
        assert.equal(denied.status,403);
        const options = {headers:{'X-Galaxy-Desktop-Token':server.token},redirect:'manual'};
        const first = await fetch(server.origin+'/login',options);
        assert.equal(first.status,302);
        assert.match(first.headers.get('location'),/desktop\/setup$/);
        const setup = await fetch(server.origin+'/desktop/setup',options);
        assert.equal(setup.status,200);
        const html = await setup.text();
        assert.match(html,/Pengaturan Awal/);
        assert.doesNotMatch(html,/(?:src|href)=["']https?:\/\/(?!127\.0\.0\.1)/);
        for(const asset of ['/vendor/source-sans-pro.css','/vendor/sweetalert2/sweetalert2.all.min.js','/img/logo.png']) assert.equal((await fetch(server.origin+asset,options)).status,200);
        const jar = new Map();
        function remember(response) { for(const cookie of response.headers.getSetCookie()) { const value=cookie.split(';')[0]; const split=value.indexOf('='); jar.set(value.slice(0,split),value.slice(split+1)); } }
        remember(setup);
        const csrf = html.match(/name="_token" value="([^"]+)"/)[1];
        const credentials = new URLSearchParams({_token:csrf,store_name:'Toko Uji Desktop',name:'Admin Uji',email:'offline-test@example.test',password:'Offline-Test-123',password_confirmation:'Offline-Test-123'});
        const created = await fetch(server.origin+'/desktop/setup',{...options,method:'POST',headers:{...options.headers,'Content-Type':'application/x-www-form-urlencoded',Cookie:[...jar].map(([key,value])=>key+'='+value).join('; ')},body:credentials});
        assert.equal(created.status,302);
        remember(created);
        const authenticated = {...options,headers:{...options.headers,Cookie:[...jar].map(([key,value])=>key+'='+value).join('; ')}};
        assert.equal((await fetch(server.origin+'/dashboard',authenticated)).status,200);
        const table = await fetch(server.origin+'/usaha/stok?data_table=stock_page&draw=1&offset=0&length=10',authenticated);
        assert.equal(table.status,200);
        assert.equal((await table.json()).recordsTotal,0);
        const pdf = await fetch(server.origin+'/laporan/pdf/2026-10-01/2026-10-27',authenticated);
        assert.equal(pdf.status,200);
        assert.match(pdf.headers.get('content-type'),/application\/pdf/);
        const excel = await fetch(server.origin+'/laporan/excel/ringkasan/2026-10-01/2026-10-27',authenticated);
        assert.equal(excel.status,200);
        const zip = path.join(directory,'backups/test.galaxypos.zip');
        await command(php,backend,env,['desktop:backup',zip],log);
        assert.ok(fs.statSync(zip).size>0);
        await command(php,backend,env,['desktop:initialize'],log);
        console.log(JSON.stringify({result:'PASS',data:directory,origin:server.origin,checks:['fresh SQLite migrations','first administrator creation','authenticated dashboard','server-side tables','PDF export','Excel export','blocked unauthenticated local access','local assets','backup','safe reopening']},null,2));
    } finally { server.child.kill(); }
}
main().catch(error=>{console.error(error);process.exitCode=1;});
