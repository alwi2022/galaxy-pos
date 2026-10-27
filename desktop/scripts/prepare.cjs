const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const {execFileSync} = require('node:child_process');
const root = path.resolve(__dirname,'../..');
const desktop = path.join(root,'desktop');
const staging = path.join(desktop,'.staging');

function copyRuntime(source, destination) {
    if (fs.existsSync(destination) && fs.statSync(source).size === fs.statSync(destination).size) {
        const digest = file => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
        if (digest(source) === digest(destination)) return;
    }
    fs.copyFileSync(source,destination);
}

async function download(url, destination) {
    if(fs.existsSync(destination)) return;
    fs.mkdirSync(path.dirname(destination),{recursive:true});
    const response = await fetch(url,{signal:AbortSignal.timeout(120000)});
    if(!response.ok) throw new Error('Unduhan gagal: '+url+' ('+response.status+')');
    fs.writeFileSync(destination,Buffer.from(await response.arrayBuffer()));
}

async function font(cssUrl, name) {
    const directory = path.join(root,'public/vendor');
    const file = path.join(directory,name+'.css');
    if(fs.existsSync(file)) return;
    const response = await fetch(cssUrl,{headers:{'User-Agent':'Mozilla/5.0 Chrome/130.0.0.0 Safari/537.36'},signal:AbortSignal.timeout(60000)});
    if(!response.ok) throw new Error('Font gagal diunduh: '+name);
    let css = await response.text();
    const urls = [...new Set([...css.matchAll(/url\((https:\/\/[^)]+)\)/g)].map(match=>match[1]))];
    await Promise.all(urls.map(async url=>{
        const filename = crypto.createHash('sha256').update(url).digest('hex').slice(0,20)+path.extname(new URL(url).pathname);
        await download(url,path.join(directory,'fonts',filename));
        css = css.replaceAll(url,'fonts/'+filename);
    }));
    fs.writeFileSync(file,css);
}

async function main() {
    console.log('Menyiapkan aset lokal dan runtime Windows...');
    await Promise.all([
        download('https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css',path.join(root,'public/vendor/sweetalert2/sweetalert2.min.css')),
        download('https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js',path.join(root,'public/vendor/sweetalert2/sweetalert2.all.min.js')),
        download('https://cdn.jsdelivr.net/npm/sweetalert2@11/LICENSE',path.join(root,'public/vendor/sweetalert2/LICENSE')),
        font('https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic','source-sans-pro'),
        font('https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap','nunito'),
        download('https://raw.githubusercontent.com/adobe-fonts/source-sans/release/LICENSE.md',path.join(root,'public/vendor/fonts/SOURCE-SANS-LICENSE.md')),
        download('https://raw.githubusercontent.com/google/fonts/main/ofl/nunito/OFL.txt',path.join(root,'public/vendor/fonts/NUNITO-LICENSE.txt')),
        download('https://aka.ms/vs/17/release/vc_redist.x64.exe',path.join(desktop,'build/VC_redist.x64.exe'))
    ]);

    const binary = process.env.GALAXY_PHP_DIR ? path.join(process.env.GALAXY_PHP_DIR,'php.exe') : execFileSync('where.exe',['php'],{encoding:'utf8'}).trim().split(/\r?\n/)[0];
    execFileSync('powershell.exe',['-NoProfile','-NonInteractive','-ExecutionPolicy','Bypass','-File',path.join(desktop,'build/icon.ps1'),'-Source',path.join(root,'public/img/logo.png'),'-Destination',path.join(desktop,'build/icon.ico')]);
    const source = path.dirname(binary);
    const version = execFileSync(binary,['-r','echo PHP_VERSION;'],{encoding:'utf8'}).trim();
    if(!/^8\.4\./.test(version)) throw new Error('Build ini memerlukan PHP 8.4 sesuai composer.lock; ditemukan '+version);
    fs.mkdirSync(staging,{recursive:true});
    const php = path.join(staging,'php');
    fs.mkdirSync(path.join(php,'ext'),{recursive:true});
    for(const file of fs.readdirSync(source)) {
        if(/\.dll$/i.test(file) || ['php.exe','license.txt','readme-redist-bins.txt'].includes(file)) copyRuntime(path.join(source,file),path.join(php,file));
    }
    const extensions = ['curl','fileinfo','gd','mbstring','openssl','pdo_sqlite','sqlite3','zip'];
    for(const extension of extensions) copyRuntime(path.join(source,'ext','php_'+extension+'.dll'),path.join(php,'ext','php_'+extension+'.dll'));
    fs.writeFileSync(path.join(php,'php.ini'),[
        '[PHP]','date.timezone=Asia/Jakarta','memory_limit=512M','max_execution_time=120','upload_max_filesize=256M','post_max_size=260M',
        'display_errors=Off','log_errors=On','expose_php=Off','variables_order=EGPCS','error_reporting=E_ALL & ~E_DEPRECATED',
        ...extensions.map(extension=>'extension='+extension),'\n'
    ].join('\n'));
    execFileSync(path.join(php,'php.exe'),['-c',path.join(php,'php.ini'),'-d','extension_dir='+path.join(php,'ext'),'-r','require '+JSON.stringify(path.join(root,'vendor/autoload.php').replaceAll('\\','/'))+'; if(!extension_loaded("pdo_sqlite") || !extension_loaded("sqlite3")) exit(2);']);

    const backend = path.join(staging,'backend');
    if(!backend.startsWith(staging+path.sep)) throw new Error('Lokasi staging tidak valid.');
    if(fs.existsSync(backend)) fs.rmSync(backend,{recursive:true,force:true});
    fs.mkdirSync(backend,{recursive:true});
    for(const folder of ['app','bootstrap','config','public','resources','routes','vendor']) {
        const sourceDirectory = path.join(root,folder);
        fs.cpSync(sourceDirectory,path.join(backend,folder),{recursive:true,filter:file=>{
            const relative=path.relative(sourceDirectory,file).replaceAll('\\','/');
            if(folder==='bootstrap' && relative.startsWith('cache/')) return false;
            if(folder==='public' && /^(storage|hot)(\/|$)/.test(relative)) return false;
            return !file.endsWith('.map') && !file.endsWith('.log');
        }});
    }
    fs.mkdirSync(path.join(backend,'bootstrap/cache'),{recursive:true});
    fs.mkdirSync(path.join(backend,'database'),{recursive:true});
    fs.cpSync(path.join(root,'database/migrations'),path.join(backend,'database/migrations'),{recursive:true});
    for(const file of ['artisan','composer.json','composer.lock']) fs.copyFileSync(path.join(root,file),path.join(backend,file));
    fs.copyFileSync(path.join(desktop,'server.php'),path.join(backend,'desktop-server.php'));
    fs.mkdirSync(path.join(backend,'storage/framework'),{recursive:true});
    const manifest={php:version,createdAt:new Date().toISOString(),assets:{}};
    for(const file of ['sweetalert2/sweetalert2.all.min.js','sweetalert2/sweetalert2.min.css','source-sans-pro.css','nunito.css']) manifest.assets[file]=crypto.createHash('sha256').update(fs.readFileSync(path.join(root,'public/vendor',file))).digest('hex');
    fs.writeFileSync(path.join(staging,'build-manifest.json'),JSON.stringify(manifest,null,2));
    console.log('Backend dan PHP '+version+' siap. Database cloud, .env, sertifikat Aiven, dan data toko tidak dimasukkan ke paket.');
}

main().catch(error=>{console.error(error);process.exitCode=1;});
