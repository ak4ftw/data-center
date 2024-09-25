<?php

namespace app\admin\controller;

use think\captcha\facade\Captcha;
use think\facade\Db;
use think\facade\Request;
use think\facade\Session;

class Index extends Base
{

    public function login(){
        if (Request::method() == 'GET'){
            return view('index/login');
        }
        if (Request::method() == 'POST'){
            $username = input('post.username');
            $password = input('post.password');
            $captchaCode = input('post.captcha_code');


            if(!captcha_check($captchaCode)){ return response('验证码错误'); }
            if(!$this->checkLogin($username, $password)){ return response('登录失败'); }

            $find = Db::name('user')->where(['username' => $username])->find();

            Session::set('is_login', 1);
            Session::set('username', $find['username']);

            Db::name('user_log')->insert(['username' => $username, 'ip' => Request::ip(), 'msg' => 'login', 'create_date' => date('Y-m-d H:i:s')]);

            return redirect('/admin');
        }
    }
    public function logout(){
        Session::delete('is_login');
        Session::delete('username');
        return redirect('/admin');
    }
    public function loginVerify(){
        return Captcha::create();
    }

    public function index(){
        // 验证登录
        if (!Session::get('is_login')){ return redirect('/admin/index/login'); }

        $data = [];
        return view('index/index', $data);
    }

    public function test(){

        return success('account');
    }
}
