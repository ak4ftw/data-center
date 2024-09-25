<?php

namespace app\admin\controller;

use think\facade\Db;
use think\facade\Session;

class Config extends Base
{

    public function index(){
        // 验证登录
        if (!Session::get('is_login')){ return redirect('/admin/index/login'); }

        $KVSelect = Db::name('kv')->order('pk_id ASC')->select()->toArray();

        $kv = [];
        foreach ($KVSelect as $v){
            $kv[$v['key']] = $v;
        }

        $data = [];
        $data['kv_select'] = $KVSelect;
        $data['kv'] = $kv;
        return view('config/index', $data);
    }

    public function update(){
        // 验证登录
        if (!Session::get('is_login')){ return redirect('/admin/index/login'); }

        $post = input("post.");

        foreach ($post as $k => $v){
            Db::name('kv')->where('key',$k)->update(['value' => $v]);
        }

        return "设置成功";
    }
}
