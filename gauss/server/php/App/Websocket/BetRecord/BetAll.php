<?php

namespace App\Websocket\BetRecord;

use Lib\Config;
use Lib\Websocket\Context;
use App\Websocket\CheckLogin;

/*
 * 注单--注单记录
 * BetRecord/BetAll {"type":"betChaseList","status":"0"}
 * type(不传则为普通投注):
 * normalBetList:普通投注数据;status（不传则为全部）:0:待开奖；1：未中奖；2：已中奖;
 * betChaseList：追号投注数据;status（不传则为全部）:in_chase:追号中;end_chase:停止追号
 * */

class BetAll extends CheckLogin
{
    public function onReceiveLogined(Context $context, Config $config)
    {
        $data = $context->getData();
        $context->reply(['status' => 200, 'msg' => '获取成功', 'data' => $data]);exit;
        $type = $data['type'];
        $status = isset($data['status'])?$data['status']:4;
        $userId = $context->getInfo('UserId');
        $deal_key = $context->getInfo('DealKey');
        //选择数据库
        $mysql = $config->__get("data_".$deal_key);
        switch ($type)
        {
            case 'betChaseList':
                    if ($status == 'in_chase')
                    {
                        $bet_in_ChaseList = $this->ChaseBet($mysql,$userId,$status);
                        $context->reply(['status' => 200, 'msg' => '获取成功', 'data' => $bet_in_ChaseList]);
                    }elseif ($status == 'end_chase')
                    {
                        $bet_end_ChaseList = $this->ChaseBet($mysql,$userId,$status);
                        $context->reply(['status' => 200, 'msg' => '获取成功', 'data' => $bet_end_ChaseList]);
                    }else
                    {
                        $ChaseList = $this->ChaseBet($mysql,$userId,$status);
                        $context->reply(['status' => 200, 'msg' => '获取成功', 'data' => $ChaseList]);
                    }
                $id = $context->clientId();
                $taskAdapter = new \Lib\Task\Adapter($config->cache_daemon);
                $taskAdapter->plan('User/Balance', ['user_id' => $userId, 'deal_key' => $deal_key, 'id' => $id]);
                break;
            default:
                //普通投注数据
                if ($status == 1)
                {
                    $normalBet = $this->normalBet($mysql,$userId,$status);
                }else if($status == 2)
                {
                    $normalBet = $this->normalBet($mysql,$userId,$status);
                }else if($status == "0")
                {
                    $normalBet = $this->normalBet($mysql,$userId,$status);
                }else if($status == 3)
                {
                    $normalBet = $this->normalBet($mysql,$userId,$status);
                }else
                {
                    $status=4;
                    $normalBet = $this->normalBet($mysql,$userId,$status);
                }

                //返回数据
                $context->reply(['status' => 200, 'msg' => '获取成功', 'data' => $normalBet]);
                $id = $context->clientId();
                $taskAdapter = new \Lib\Task\Adapter($config->cache_daemon);
                $taskAdapter->plan('User/Balance', ['user_id' => $userId, 'deal_key' => $deal_key, 'id' => $id]);
                break;

        }
    }
    private function normalBet($mysql,$userId,$result)
    {
        $normalBetListWin= [];
        $normalBetListLose = [];
        $normalBetListWait = [];
        $normalBetList = [];
        $normalBetListTie = [];
        $sql = 'SELECT bet_serial,rule_id,game_key,launch_time,bet_launch,quantity,bonus,result AS status FROM bet_unit_intact WHERE period_list IS NULL AND user_id=:user_id ORDER BY launch_time DESC';
        $param = [':user_id' => $userId];
        foreach ($mysql->query($sql,$param) as $row)
        {
            $normalBetList[] = $row;
        }
        foreach ($normalBetList as $key => $value)
        {
            if ($normalBetList[$key]['bonus'] > 0)
            {
                $normalBetList[$key]['status'] = '2';
            }
            if ($normalBetList[$key]['bonus'] > 0 && $normalBetList[$key]['status'] == 2)
            {
                $normalBetListWin[] = $normalBetList[$key];
            }
            if ($normalBetList[$key]['bonus'] ==0 && $normalBetList[$key]['status'] == 1)
            {
                $normalBetListLose[] = $normalBetList[$key];
            }
            if ($normalBetList[$key]['bonus'] ==0 && $normalBetList[$key]['status'] == 0)
            {
                $normalBetListWait[] = $normalBetList[$key];
            }
            if ($normalBetList[$key]['status'] == 3)
            {
                $normalBetListTie[] = $normalBetList[$key];
            }
        }
        switch ($result)
        {
            case 2:
                return $normalBetListWin;
                break;
            case 1:
                return $normalBetListLose;
                break;
            case 0:
                return $normalBetListWait;
                break;
            case 3:
                return $normalBetListTie;
                break;
            case 4 :
                return $normalBetList;
                break;
        }
    }

    private function ChaseBet($mysql,$userId,$result)
    {
        $chaseList = [];
        $inChase = [];
        $endChase = [];
        $list = [];
        $serial = [];
        $sql = 'SELECT bet_serial,rule_id,game_key,launch_time,SUM(bet_launch) AS bet_launch,SUM(bonus) AS bonus,SUM(quantity) AS quantity FROM bet_unit_intact WHERE  period_list IS NOT null AND user_id=:user_id GROUP BY rule_id,bet_serial,game_key,launch_time ORDER BY launch_time DESC';
        //$chase_sql = 'SELECT  bet_launch,bonus,quantity FROM `bet_unit_intact` WHERE bet_serial=:bet_serial AND rule_id=:rule_id';
        $is_chase_sql = 'SELECT bet_serial FROM bet_chase_run WHERE user_id=:user_id';
        $param = [':user_id' => $userId];
        foreach ($mysql->query($is_chase_sql,$param) as $chase)
        {
            $serial[] = $chase['bet_serial'];
        }
        foreach($mysql->query($sql,$param) as $row)
        {
            $list[] = $row;
        }
        foreach ($list as $k => $v)
        {
            $chaseList[$k]['bet_serial'] = $v['bet_serial'];
            $chaseList[$k]['game_key'] = $v['game_key'];
            $chaseList[$k]['launch_time'] = $v['launch_time'];
            $chaseList[$k]['rule_id'] = $v['rule_id'];
            $chaseList[$k]['bet_launch'] = $v['bet_launch'];
            $chaseList[$k]['bonus'] = $v['bonus'];
            $chaseList[$k]['rule_count'] = $v['quantity'];
            if (in_array($v['bet_serial'],$serial))
            {
                $chaseList[$k]['status'] = 'in_chase';
                $inChase[] = $chaseList[$k];
            }else
            {
                $chaseList[$k]['status'] = 'end_chase';
                $endChase[] = $chaseList[$k];
            }
        }
        switch ($result)
        {
            case 'in_chase':
                return $inChase;
                break;
            case 'end_chase':
                return $endChase;
                break;
            default:
                return $chaseList;
                break;
        }
    }

}