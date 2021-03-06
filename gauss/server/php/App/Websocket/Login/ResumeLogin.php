<?php
namespace App\Websocket\Login;

use Lib\Config;
use Lib\Websocket\Context;
use Lib\Websocket\IHandler;

/*
 * 恢复登录
 *  Login/ResumeLogin {"resume_key":"dededf4525d9e59bb14d92f0c0e3e2a12d39265f","login_device":0}
 * */

class ResumeLogin implements IHandler
{
    public function onReceive(Context $context, Config $config)
    {
        $data = $context->getData();
        $resume_key = $data['resume_key'];
        $login_device = $data["login_device"];
        if(empty($resume_key)){
            $context->reply(["status"=>202,"msg"=>"登录失败"]);
            return;
        }
        $user_agent = sha1($context->getInfo("User-Agent"));
        $sql = "SELECT * FROM user_session WHERE resume_key=:resume_key AND user_agent=:user_agent and lose_time > :lose_time";
        $params = [
            ":resume_key"=>$resume_key,
            ":user_agent"=>$user_agent,
            ":lose_time"=>time()-86400*3
        ];
        $mysql = $config->data_user;
        $info = array();
        foreach ($mysql->query($sql,$params) as $row){
            $info = $row;
        }
        if(empty($info)){
            $context->reply(["status"=>401,"msg"=>"恢复登录失败,请重新登录"]);
            return;
        }else{
            $userId = $info["user_id"];
            //获取用户基本信息
            $sql = "SELECT * FROM user_info_intact WHERE user_id=:user_id";
            $param = [":user_id"=>$userId];
            $user_info = array();
            foreach ($mysql->query($sql,$param) as $rows){
                $user_info = $rows;
            }
            if(empty($user_info)){
                $context->reply(["status"=>204,"msg"=>"连接超时,请重新登录"]);
                return;
            }

            //监测层级是否被冻结
            $layer_id = $user_info['layer_id'];
            $auth_sql = "select operate_key from layer_permit where layer_id = '$layer_id'";
            $authArray = [];
            foreach ($mysql->query($auth_sql) as $row) {
                $authArray[] = $row['operate_key'];
            }
            $stop_rebate = 0;
            if(!empty($authArray)){
                if(in_array('account_freeze',$authArray)) {
                    $context->reply(["status"=>209,"msg"=>"该账号被冻结,请联系客服"]);
                    return;
                }
                //禁止返点信息
                if(in_array('rebate_prohibit',$authArray)) {
                    $stop_rebate = 1;
                }
            }
            $context->setInfo('Auth',json_encode($authArray));
            //更新缓存信息
            try{
                //用户掉线3天内 重新上线更新用户的信息
                $sql = "UPDATE user_session SET client_id=:client_id,lose_time=:lose_time WHERE resume_key = :resume_key and user_id =:user_id";
                $param = [':client_id'=>$context->clientId(),':lose_time'=>0,':resume_key'=>$resume_key,":user_id"=>$userId];
                $mysql->execute($sql,$param);
            } catch (\PDOException $e) {
                $context->reply(['status' => 400, 'msg' => '连接超时,请重新登录']);
                throw new \PDOException($e);
            }

            //记录恢复日志
            $serverHost = $context->getServerHost();
            $clientAddr = $context->getClientAddr();
            $userAgent = $context->getInfo("User-Agent");
            $sql = 'INSERT INTO operate_log SET user_id=:user_id, operate_key=:operate_key, detail=:detail';
            $params = [
                ':user_id' => $user_info['user_id'],
                ':operate_key' => 'self_login',
                ':detail' => '服务器' . $serverHost . ';恢复登录' . 'ip' . $clientAddr . ",User-Agent:" . $userAgent,
            ];
            $invite_code = empty($user_info["invite_code"])?"0":$user_info["invite_code"];
            $mysql->execute($sql, $params);
            $context->reply(['status' => 200, 'msg' => '恢复登录成功','resume_key'=>$resume_key,"invite_code"=>$invite_code,"stop_rebate"=>$stop_rebate]);
            //存redis
            $deal_key = $user_info["deal_key"];

            $account_name =  empty($user_info["account_name"])?" ":$user_info["account_name"];
            $context->setInfo("UserId",$userId);
            $context->setInfo("LoginDevice",$login_device);
            $context->setInfo("LayerId",$user_info["layer_id"]);
            $context->setInfo("DealKey",$deal_key);
            $context->setInfo("InviteCode",$invite_code);
            $context->setInfo("AccountName",$account_name);
            $context->setInfo("UserKey",$user_info["user_key"]);
            $context->setInfo("LoginDevice",intval($login_device));
            
            //会员私信
            $id = $context->clientId();
            $taskAdapter = new \Lib\Task\Adapter($config->cache_daemon);
            $taskAdapter->plan('User/UserInfo', ['user_id' => $userId,'id'=>$id]);
            $taskAdapter->plan('Message/UserMessage', ['user_id' => $userId,'id'=>$id]);
            $taskAdapter->plan('Message/LayerMessage', ['layer_id' => $user_info["layer_id"],'id'=>$id]);
            $taskAdapter->plan('User/Balance', ['user_id' => $userId,'deal_key'=>$deal_key,'id'=>$id]);
        }
    }
}
