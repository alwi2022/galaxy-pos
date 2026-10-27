const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const net = require('node:net');
const http = require('node:http');
const {spawn} = require('node:child_process');
const children = new Set();

function track(child) {
    children.add(child);
    child.once('exit',()=>children.delete(child));
    child.once('error',()=>children.delete(child));
    return child;
}

function stopChildren() { for(const child of children) { if(child.exitCode===null) child.kill(); } }

function initializeData(directory) {
    fs.mkdirSync(directory, {recursive: true});
    for (const sub of ['cache','backups','public/img','storage/app/public','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs']) fs.mkdirSync(path.join(directory,sub), {recursive:true});
    for (const file of ['config.php','services.php','packages.php','routes.php','events.php']) fs.rmSync(path.join(directory,'cache',file),{force:true});
    const database = path.join(directory,'database.sqlite');
    if (!fs.existsSync(database)) fs.closeSync(fs.openSync(database,'wx'));
    const envFile = path.join(directory,'.env');
    if (!fs.existsSync(envFile)) {
        const quote = value => '"' + value.replaceAll('\\','/').replaceAll('"','\\"') + '"';
        fs.writeFileSync(envFile, [
            'APP_NAME="Galaxy POS"','APP_ENV=production','APP_DEBUG=false',
            'APP_KEY=base64:'+crypto.randomBytes(32).toString('base64'),
            'APP_URL=http://127.0.0.1','DB_CONNECTION=sqlite','DB_DATABASE='+quote(database),
            'DB_FOREIGN_KEYS=true','CACHE_DRIVER=file','SESSION_DRIVER=file','SESSION_LIFETIME=480',
            'QUEUE_CONNECTION=sync','MAIL_MAILER=log','LOG_CHANNEL=single','LOG_LEVEL=warning',
            'BROADCAST_DRIVER=log','SESSION_SECURE_COOKIE=false','\n'
        ].join('\n'), {flag:'wx'});
    }
    return database;
}

function environment(directory, origin, token, instance) {
    const env = {...process.env};
    for (const name of Object.keys(env)) {
        if (/^(APP_|DB_|DATABASE_URL$|MYSQL_|SESSION_|CACHE_|QUEUE_|MAIL_|AWS_|REDIS_|BROADCAST_)/.test(name)) delete env[name];
    }
    Object.assign(env, {GALAXY_DESKTOP_DATA:directory,GALAXY_DESKTOP_TOKEN:token,GALAXY_DESKTOP_INSTANCE:instance,
        APP_URL:origin,APP_ENV:'production',APP_DEBUG:'false',DB_CONNECTION:'sqlite',DB_DATABASE:path.join(directory,'database.sqlite'),
        SESSION_DRIVER:'file',CACHE_DRIVER:'file',QUEUE_CONNECTION:'sync',MAIL_MAILER:'log',
        APP_CONFIG_CACHE:path.join(directory,'cache/config.php'),APP_SERVICES_CACHE:path.join(directory,'cache/services.php'),
        APP_PACKAGES_CACHE:path.join(directory,'cache/packages.php'),APP_ROUTES_CACHE:path.join(directory,'cache/routes.php'),APP_EVENTS_CACHE:path.join(directory,'cache/events.php')});
    return env;
}

function phpArguments(runtime, args) {
    return ['-c',path.join(runtime,'php.ini'),'-d','extension_dir='+path.join(runtime,'ext'),...args];
}

function command(runtime, backend, env, args, logFile) {
    return new Promise((resolve,reject) => {
        const output = fs.openSync(logFile,'a');
        const child = track(spawn(path.join(runtime,'php.exe'),phpArguments(runtime,['artisan',...args]), {cwd:backend,env,windowsHide:true,stdio:['ignore',output,output]}));
        fs.closeSync(output);
        child.once('error',reject);
        child.once('exit',code => code === 0 ? resolve() : reject(new Error('Proses '+args[0]+' gagal (kode '+code+'). Detail: '+logFile)));
    });
}

function freePort() {
    return new Promise((resolve,reject) => {
        const socket = net.createServer();
        socket.once('error',reject);
        socket.listen(0,'127.0.0.1',() => { const port = socket.address().port; socket.close(error => error ? reject(error) : resolve(port)); });
    });
}

function isInternal(url, origin) {
    try { return new URL(url).origin === origin; } catch { return false; }
}

function health(origin, token, instance) {
    return new Promise(resolve => {
        const request = http.get(origin+'/__desktop/health',{headers:{'X-Galaxy-Desktop-Token':token},timeout:800}, response => {
            let body=''; response.on('data',chunk => { body+=chunk; });
            response.on('end',() => { try { resolve(response.statusCode===200 && JSON.parse(body).instance===instance); } catch { resolve(false); } });
        });
        request.on('timeout',() => request.destroy());
        request.on('error',() => resolve(false));
    });
}

async function startServer(runtime, backend, directory, logFile) {
    const port = await freePort();
    const origin = 'http://127.0.0.1:'+port;
    const token = crypto.randomBytes(32).toString('hex');
    const instance = crypto.randomUUID();
    const env = environment(directory,origin,token,instance);
    const output = fs.openSync(logFile,'a');
    const child = track(spawn(path.join(runtime,'php.exe'),phpArguments(runtime,['-S','127.0.0.1:'+port,'-t',path.join(backend,'public'),path.join(backend,'desktop-server.php')]), {cwd:backend,env,windowsHide:true,stdio:['ignore',output,output]}));
    fs.closeSync(output);
    let failure;
    child.on('error',error => { failure=error; });
    for (let retry=0; retry<100; retry++) {
        if (failure || child.exitCode !== null) throw failure || new Error('Server lokal berhenti. Detail: '+logFile);
        if (await health(origin,token,instance)) return {child,origin,token,env};
        await new Promise(resolve=>setTimeout(resolve,150));
    }
    child.kill();
    throw new Error('Server lokal belum siap. Detail: '+logFile);
}

module.exports = {initializeData,environment,phpArguments,command,freePort,isInternal,health,startServer,stopChildren};
