<?php

namespace app\member\controller;

use think\captcha\facade\Captcha;
use think\facade\Db;
use think\facade\Request;
use think\facade\Session;

class Index extends Base{

    public function login(){
        if (Request::method() == 'GET'){
            return view('index/login');
        }
        if (Request::method() == 'POST'){
            $username = input('post.username');
            $password = input('post.password');
            $captchaCode = input('post.captcha_code');

            // if(!captcha_check($captchaCode)){ return response('验证码错误'); }
            if(!$this->checkLogin($username, $password)){ return response('登录失败'); }

            $findMember = Db::name('member')->where(['username' => $username])->find();

            Session::set('member.is_login', 1);
            Session::set('member.member_id', $findMember['pk_id']);
            Session::set('member.username', $findMember['username']);

            return redirect('/member');
        }
    }
    public function logout(){
        Session::delete('member.is_login');
        Session::delete('member.member_id');
        Session::delete('member.username');
        return redirect('/member');
    }
    public function loginVerify(){
        return Captcha::create();
    }

    public function index(){
        // 验证登录
        if (!Session::get('member.is_login')){ return redirect('/member/index/login'); }

        // 会员信息
        $findMember = Db::name('member')->find(Session::get('member.member_id'));

        // 账号信息
        $findAccount = Db::name('account')->where(['account' => $findMember['account']])->find();

        // 盈利信息
        // 盈利sql SELECT * FROM `vnpy`.`slice` WHERE account = 30201139 AND is_close = 1 AND ( ( buy_or_sell = 'buy' AND open_price < close_price ) OR ( buy_or_sell = 'sell' AND open_price > close_price ) )
        // 亏损sql SELECT * FROM `vnpy`.`slice` WHERE account = 30201139 AND is_close = 1 AND ( ( buy_or_sell = 'buy' AND open_price > close_price ) OR ( buy_or_sell = 'sell' AND open_price < close_price ) )
        $selectSliceIsClose = Db::name('slice')
            ->where('account', $findAccount['account'])
            ->where('is_close', 1)
            ->where("( ( buy_or_sell = 'buy' AND open_price < close_price ) OR ( buy_or_sell = 'sell' AND open_price > close_price ) )")
            ->order('create_date DESC')
            ->select()
            ->each(function($v){

                # 手续费计算
                $v['open_charge'] = calculate_profit_open($v['open_price']);
                $v['close_charge'] = calculate_profit_open($v['close_price']);

                # 盈利
                # $v['slice_profit_num'] = (($v['open_price'] - $v['close_price']) * 10 - $v['open_charge'] - $v['close_charge']) * $v['volume'];
                $v['slice_profit_num'] = (($v['open_price'] - $v['close_price']) * 10) * $v['volume'];

                # 持仓数量
                $v['slice_today'] = Db::name('slice_today')->where('account', $v['account'])->where('date', date('Y-m-d', strtotime($v['create_date'])))->find();

                return $v;
            });

        // 日盈利信息

        // 月盈利信息

        $data = [
            'member' => $findMember,
            'account' => $findAccount,
            'slice_is_close' => $selectSliceIsClose,
        ];
        return view('index/index', $data);
    }
}
