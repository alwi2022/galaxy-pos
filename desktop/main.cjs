const {app,BrowserWindow,Menu,dialog,session,shell} = require('electron');
const fs = require('node:fs');
const path = require('node:path');
const {initializeData,environment,command,isInternal,startServer,stopChildren} = require('./runtime.cjs');

let mainWindow;
let server;
let stopping=false;
let maintenance=false;
const single = app.requestSingleInstanceLock();
if (!single) app.quit();

app.on('second-instance',() => { if(mainWindow) { if(mainWindow.isMinimized()) mainWindow.restore(); mainWindow.focus(); } });
app.on('window-all-closed',() => { if(!maintenance) app.quit(); });
app.on('before-quit',() => { stopping=true; stopChildren(); });

function configureWindow(window) {
    window.webContents.on('will-navigate',(event,url) => { if(!isInternal(url,server.origin)) event.preventDefault(); });
    window.webContents.setWindowOpenHandler(({url}) => {
        if(!isInternal(url,server.origin) && !url.startsWith('blob:'+server.origin+'/')) return {action:'deny'};
        return {action:'allow',overrideBrowserWindowOptions:{autoHideMenuBar:true,webPreferences:{nodeIntegration:false,contextIsolation:true,sandbox:true}}};
    });
    window.webContents.on('did-create-window',configureWindow);
}

async function run() {
    const resources = app.isPackaged ? process.resourcesPath : path.join(__dirname,'.staging');
    const backend = path.join(resources,'backend');
    const php = path.join(resources,'php');
    const directory = process.env.GALAXY_DESKTOP_TEST_DATA || path.join(app.getPath('appData'),'GalaxyPOS');
    const database = initializeData(directory);
    const logFile = path.join(directory,'storage/logs/desktop.log');
    const env = environment(directory,'http://127.0.0.1','','');
    const smoke = process.env.GALAXY_DESKTOP_SMOKE_TEST === '1';
    mainWindow = new BrowserWindow({title:'Galaxy POS',width:1366,height:900,minWidth:900,minHeight:650,show:false,backgroundColor:'#ecf0f5',icon:path.join(backend,'public/img/logo.png'),webPreferences:{nodeIntegration:false,contextIsolation:true,sandbox:true}});
    if(!smoke) mainWindow.once('ready-to-show',()=>mainWindow.show());
    await mainWindow.loadFile(path.join(__dirname,'loading.html'));
    if(fs.statSync(database).size>0) {
        const today = new Date().toLocaleDateString('sv-SE');
        const daily = path.join(directory,'backups','otomatis-'+today+'.galaxypos.zip');
        if(!fs.existsSync(daily)) await command(php,backend,env,['desktop:backup',daily],logFile);
    }
    await command(php,backend,env,['desktop:initialize'],logFile);
    server = await startServer(php,backend,directory,logFile);
    server.child.on('exit',() => {
        if(!stopping && !maintenance) { dialog.showErrorBox('Galaxy POS','Server lokal berhenti. Buka kembali aplikasi. Data yang sudah tersimpan tetap berada di komputer ini.'); app.quit(); }
    });

    session.defaultSession.webRequest.onBeforeRequest((details,callback) => {
        const local = isInternal(details.url,server.origin);
        const browserResource = /^(data:|blob:|chrome-extension:|devtools:)/.test(details.url);
        callback({cancel:!local && !browserResource});
    });
    session.defaultSession.webRequest.onBeforeSendHeaders((details,callback) => {
        if(isInternal(details.url,server.origin)) details.requestHeaders['X-Galaxy-Desktop-Token']=server.token;
        callback({requestHeaders:details.requestHeaders});
    });
    session.defaultSession.setPermissionRequestHandler((_contents,_permission,callback)=>callback(false));

    async function backup() {
        const result = await dialog.showSaveDialog(mainWindow,{title:'Simpan Cadangan Galaxy POS',defaultPath:path.join(app.getPath('documents'),'Galaxy-POS-'+new Date().toISOString().slice(0,10)+'.galaxypos.zip'),filters:[{name:'Cadangan Galaxy POS',extensions:['zip']}]});
        if(result.canceled) return;
        try { await command(php,backend,server.env,['desktop:backup',result.filePath],logFile); await dialog.showMessageBox(mainWindow,{type:'info',message:'Cadangan berhasil disimpan.',detail:result.filePath}); }
        catch(error) { dialog.showErrorBox('Cadangan gagal',error.message); }
    }

    async function restore() {
        const selected = await dialog.showOpenDialog(mainWindow,{title:'Pilih Cadangan Galaxy POS',properties:['openFile'],filters:[{name:'Cadangan Galaxy POS',extensions:['zip']}]});
        if(selected.canceled) return;
        const confirm = await dialog.showMessageBox(mainWindow,{type:'warning',buttons:['Batal','Pulihkan'],defaultId:0,cancelId:0,message:'Ganti data toko dengan cadangan ini?',detail:'Data saat ini akan dicadangkan terlebih dahulu. Aplikasi dibuka kembali setelah pemulihan.'});
        if(confirm.response!==1) return;
        maintenance=true;
        BrowserWindow.getAllWindows().forEach(window=>window.close());
        if(server.child.exitCode===null) { const exited=new Promise(resolve=>server.child.once('exit',resolve)); server.child.kill(); await exited; }
        try { await command(php,backend,env,['desktop:restore',selected.filePaths[0]],logFile); }
        catch(error) { dialog.showErrorBox('Pemulihan gagal',error.message); }
        app.relaunch(); app.quit();
    }

    Menu.setApplicationMenu(Menu.buildFromTemplate([
        {label:'Aplikasi',submenu:[{label:'Cetak',accelerator:'CmdOrCtrl+P',click:()=>BrowserWindow.getFocusedWindow()?.webContents.print()},{type:'separator'},{role:'quit',label:'Keluar'}]},
        {label:'Data',submenu:[{label:'Buat Cadangan...',click:backup},{label:'Pulihkan Cadangan...',click:restore},{type:'separator'},{label:'Buka Folder Data',click:()=>shell.openPath(directory)}]},
        {label:'Tampilan',submenu:[{role:'reload',label:'Muat Ulang'},{role:'resetZoom',label:'Ukuran Normal'},{role:'zoomIn',label:'Perbesar'},{role:'zoomOut',label:'Perkecil'},{role:'togglefullscreen',label:'Layar Penuh'}]}
    ]));
    configureWindow(mainWindow);
    await mainWindow.loadURL(server.origin);
    if(smoke) {
        fs.writeFileSync(path.join(directory,'smoke-result.json'),JSON.stringify({result:'PASS',packaged:app.isPackaged,url:mainWindow.webContents.getURL(),database:path.join(directory,'database.sqlite')},null,2));
        app.quit();
    }
}

if(single) app.whenReady().then(run).catch(error=>{
    if(process.env.GALAXY_DESKTOP_SMOKE_TEST === '1') { fs.mkdirSync(process.env.GALAXY_DESKTOP_TEST_DATA,{recursive:true}); fs.writeFileSync(path.join(process.env.GALAXY_DESKTOP_TEST_DATA,'smoke-result.json'),JSON.stringify({result:'FAIL',error:error.message})); }
    else if(!stopping) dialog.showErrorBox('Galaxy POS belum dapat dibuka',error.message);
    app.quit();
});
