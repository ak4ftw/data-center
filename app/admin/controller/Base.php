<?php

namespace app\admin\controller;

use app\admin\BaseController;
use Exception;
use GuzzleHttp\Client;
use think\facade\Db;

class Base extends BaseController
{
    public function initialize(){
        parent::initialize();
    }

    protected function checkLogin($username, $password): bool{
        $find = Db::name('user')->where(['username' => $username])->find();
        if (empty($find)){ return false; }
        if (!password_verify($password, $find['password'])){ return false; }
        return true;
    }

    protected function checkToken($username, $password_hash): bool{
        $find = Db::name('user')->where(['username' => $username, 'password' => $password_hash])->find();
        if (empty($find)){ return false; }
        return true;
    }

    /**
     * 发起请求
     * @param string $url
     * @param $head
     * @param $body
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function sendPost(string $url, $head = [], $body = []): array{
        try {
            $client = new Client();
            $option = [
                'headers' => $head,
                'json' => $body,
            ];
            $response = $client->post($url, $option);
            $data = $response->getBody()->getContents();
            $data = json_decode($data, true);
        } catch (Exception $e){
            return [];
        }
        return $data;
    }

    /**
     * 发起请求
     * @param string $url
     * @param $head
     * @param $body
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function sendPostForm(string $url, $head = [], $body = []): array{
        try {
            $client = new Client();
            $option = [
                'headers' => $head,
                'form_params' => $body,
            ];
            $response = $client->post($url, $option);
            $data = $response->getBody()->getContents();
            $data = json_decode($data, true);
        } catch (Exception $e){
            return [];
        }
        return $data;
    }
}
