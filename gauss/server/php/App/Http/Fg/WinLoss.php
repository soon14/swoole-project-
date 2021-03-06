<?php
/**
 * Created by PhpStorm.
 * User: ayden
 * Date: 19-3-12
 * Time: 下午4:52
 */

namespace App\Http\Fg;

use Lib\Config;
use Lib\Http\Context;
use Lib\Http\Handler;
/*
 * fg游戏结算
 * $partnerId String 代理商账号
 * $nonce_str String 随机字符串,不长于32位
 * $sign String 通过签名算法计算得出的签名值
 * $winLossList String winLoss json 奖励列表每次结算仅有一笔,betId:注单号,prize:结算金额,username:用户名称,reckon:结算 ID
 * http://127.0.0.1:8080/2/ExternalGame/Fg/WinLoss
 */
class WinLoss extends Handler
{
    public function onRequest(Context $context, Config $config)
    {
        $common = new Common();
        $request = $context->requestPost();
        parse_str($request, $params); //将url参数字符串转换成数组

        if(!$params){
            $res = $common->return_data(2,'参数为空');
            $this -> responseJson($context,$res);
            return;
        }
        //判断代理商账号是否正确
        $partnerId = $common->__get('partnerId');
        if($partnerId != $params['partnerId']){
            $res = $common->return_data(2,'参数错误');
            $this -> responseJson($context,$res);
            return;
        }

        //生成签名
        $signCheck = $common->MakeSign($params);

        //校验加密后的参数
        if($signCheck !== $params['sign']){
            $res = $common -> return_data(108,'签名失败');
            $this -> responseJson($context,$res);
            return;
        }

        //查询账户余额
        $winLossList = $params['winLossList'];
        $winLossList = str_replace("\\","",$winLossList);
        $winLossList = str_replace("[","",$winLossList);
        $winLossList = str_replace("]","",$winLossList);
        $winLossList = json_decode($winLossList,true);
        if(!is_numeric($winLossList['prize'])){
            $res = $common->return_data(104,'金额非法');
            $this -> responseJson($context,$res);
            return;
        }
        $mysql = $config->data_user;
        $sql = 'SELECT user_id FROM user_fungaming WHERE fg_member_code=:fg_member_code';
        $param = [':fg_member_code' => $winLossList['username']];
        $userId = '';
        foreach ($mysql->query($sql, $param) as $row) {
            $userId = $row['user_id'];
        }
        if(!$userId){
            $res = $common->return_data(105,'用户不存在');
            $this -> responseJson($context,$res);
            return;
        }
        $sql = 'SELECT deal_key FROM user_info WHERE user_id=:user_id';
        $param = [':user_id' => $userId];
        $dealKey = '';
        foreach ($mysql->query($sql, $param) as $row) {
            $dealKey = $row['deal_key'];
        }
        $mysql = $config->__get('data_' . $dealKey);
        $sql = 'SELECT money,account_name,layer_id,user_key FROM account WHERE user_id=:user_id';
        $param = [':user_id' => $userId];
        $balance = '';
        $accountName = '';
        $layerId = '';
        $userKey = '';
        foreach ($mysql->query($sql, $param) as $row) {
            $balance = $row['money'];
            $accountName = $row['account_name'];
            $layerId = $row['layer_id'];
            $userKey = $row['user_key'];
        }

        //判断结算id是否存在
        $sql = 'SELECT export_serial FROM external_export_fungaming WHERE fg_bet_id=:fg_bet_id';
        $param = [':fg_bet_id' => $winLossList['betId']];
        $exportSerial = '';
        foreach ($mysql->query($sql, $param) as $row) {
            $exportSerial = $row['export_serial'];
        }
        if(!$exportSerial){
            $res = $common->return_data(105,'注单号不存在');
            $this -> responseJson($context,$res);
            return;
        }

        //判断是否重复结算，重复结算的返回交易流水号
        $fgWinLossList = serialize($winLossList);
        $sql = 'SELECT import_serial FROM external_import_fungaming WHERE fg_winlosslist=:fg_winLossList';
        $param = [':fg_winLossList' => $fgWinLossList];
        $importSerial = '';
        foreach ($mysql->query($sql, $param) as $row) {
            $importSerial = $row['import_serial'];
        }
        if($importSerial){
            $sql = 'SELECT success_deal_serial FROM external_import_success WHERE import_serial=:import_serial';
            $param = [':import_serial' => $importSerial];
            $successDealSerial = '';
            foreach ($mysql->query($sql, $param) as $row) {
                $successDealSerial = $row['success_deal_serial'];
            }
            $data = [
                'uuid' => $successDealSerial
            ];
            $res = $common -> return_data(0,'已结算',$data);
            $this -> responseJson($context,$res);
        }else{
            $time = time();
            $param = array(
                'user_id' => $userId,
                'user_key' => $userKey,
                'account_name' => $accountName,
                'layer_id' => $layerId,
                'external_type' => 'fg',
                'launch_money' => ($winLossList['prize']/100),
                'launch_time' => $time,
            );
            $str = '';
            foreach ($param as $key => $val){
                $str .= $key.'='."'".$val."',";
            }
            $str = trim($str,',');
            $sql = "INSERT INTO external_import_launch SET ".$str;
            $importSerial = '';
            try{
                $mysql->execute($sql);
                $sql = 'SELECT serial_last("external_import") as import_serial';
                foreach ($mysql->query($sql) as $row){
                    $importSerial = $row['import_serial'];
                }
            }catch (\PDOException $e){
                $res = $common -> return_data(2,'获取失败');
                $this -> responseJson($context,$res);
                throw new \PDOException($e);
            }
            $sql = "INSERT INTO external_import_success SET import_serial = '$importSerial',success_time = '$time'";
            $mysql->execute($sql);
            $sql = "INSERT INTO external_import_fungaming SET import_serial = '$importSerial',fg_winlosslist = '$fgWinLossList'";
            $mysql->execute($sql);
            $sql = 'SELECT success_deal_serial FROM external_import_success WHERE import_serial=:import_serial';
            $param = [':import_serial' => $importSerial];
            $successDealSerial = '';
            foreach ($mysql->query($sql, $param) as $row) {
                $successDealSerial = $row['success_deal_serial'];
            }

            $data = [
                'data' => array(
                    'betId' => $winLossList['betId'],
                    'uuid' => $successDealSerial,
                    'balance' => $balance*100+$winLossList['prize'],
                    "msg" => "该注单结算成功",
                    'state' => 0
                ),
                'walletTime' => $common ->utc_time()
            ];
            $res = $common -> return_data(0,'结算成功',$data);
            $this -> responseJson($context,$res);
        }
    }
}