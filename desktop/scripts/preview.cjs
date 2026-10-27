const fs = require('node:fs');
const path = require('node:path');
const {spawn} = require('node:child_process');
const {initializeData,environment,command,freePort,phpArguments} = require('../runtime.cjs');

async function main() {
    const root = path.resolve(__dirname,'../..');
    const directory = path.join(root,'desktop/.test-data','preview-'+Date.now());
    initializeData(directory);
    const runtime = path.join(root,'desktop/.staging/php');
    const port = await freePort();
    const origin = 'http://127.0.0.1:'+port;
    const env = environment(directory,origin,'','');
    await command(runtime,path.join(root,'desktop/.staging/backend'),env,['desktop:initialize'],path.join(directory,'storage/logs/preview.log'));
    const child = spawn(path.join(runtime,'php.exe'),phpArguments(runtime,['-S','127.0.0.1:'+port,'-t',path.join(root,'public'),path.join(root,'server.php')]),{cwd:root,env,windowsHide:true,stdio:'ignore'});
    process.on('SIGINT',()=>{child.kill();process.exit();});
    process.on('exit',()=>child.kill());
    child.once('error',error=>{console.error(error);process.exitCode=1;});
    fs.writeFileSync(path.join(root,'desktop/.test-data/preview.json'),JSON.stringify({origin,directory,pid:child.pid}));
    console.log('Pratinjau lokal dengan database uji: '+origin);
}
main().catch(error=>{console.error(error);process.exitCode=1;});
