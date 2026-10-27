const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {initializeData,environment,isInternal} = require('../runtime.cjs');

test('initialization retains the database, uploads and encryption key on reopening',()=>{
    const folder = fs.mkdtempSync(path.join(os.tmpdir(),'galaxy-pos-'));
    try {
        const database = initializeData(folder);
        const original = fs.readFileSync(path.join(folder,'.env'),'utf8');
        fs.writeFileSync(database,'existing transactions');
        fs.writeFileSync(path.join(folder,'public/img/upload.png'),'uploaded content');
        initializeData(folder);
        assert.equal(fs.readFileSync(database,'utf8'),'existing transactions');
        assert.equal(fs.readFileSync(path.join(folder,'.env'),'utf8'),original);
        assert.equal(fs.readFileSync(path.join(folder,'public/img/upload.png'),'utf8'),'uploaded content');
        assert.match(original,/DB_CONNECTION=sqlite/);
        assert.doesNotMatch(original,/aiven|DB_PASSWORD/);
    } finally { fs.rmSync(folder,{recursive:true,force:true}); }
});

test('desktop environment forces local database and rejects external origins',()=>{
    const directory = path.resolve('sample data');
    const env = environment(directory,'http://127.0.0.1:12345','secret','instance');
    assert.equal(env.DB_CONNECTION,'sqlite');
    assert.equal(env.DB_DATABASE,path.join(directory,'database.sqlite'));
    assert.equal(env.APP_DEBUG,'false');
    assert.equal(isInternal('http://127.0.0.1:12345/produk',env.APP_URL),true);
    for(const url of ['http://127.0.0.1:12346','http://127.0.0.1.example.com:12345','https://example.com','file:///etc/passwd','javascript:alert(1)']) assert.equal(isInternal(url,env.APP_URL),false);
});
