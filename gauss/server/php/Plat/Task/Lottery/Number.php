<?php
namespace Plat\Task\Lottery;

use Lib\Config;
use Lib\Task\Context;
use Lib\Task\IHandler;

class Number implements IHandler
{
    public function onTask(Context $context, Config $config)
    {
        ['game_key' => $game_key, 'period' => $period] = $context->getData();
        $adapter = $context->getAdapter();
        $mysql = $config->data_public;
        $now = time();

        $sql = 'select game_key,model_key,official from lottery_game where game_key=:game_key';
        $params = ['game_key' => $game_key];
        foreach ($mysql->query($sql, $params) as $row) {
            ['model_key' => $model_key, 'official' => $official] = $row;

            $sql = 'select period,plan_time from lottery_period_current where game_key=:game_key';
            foreach ($mysql->query($sql, $params) as $row) {
                if ($period != $row['period']) {
                    $adapter->plan('Lottery/Number', ['game_key' => $game_key, 'period' => $row['period']], $row['plan_time']);
                }
            }

            if ($official) {
                // 官方彩
                if (0 == $period) {
                    $adapter->plan('Lottery/Spider/History', ['game_key' => $game_key]);
                } else {
                    $adapter->plan('Lottery/Spider/ApiLottery', ['game_key' => $game_key, 'period' => $period]);
                    $adapter->plan('Lottery/Spider/OpenCai', ['game_key' => $game_key, 'period' => $period]);
                    $adapter->plan('Lottery/Spider/B1cp', ['game_key' => $game_key, 'period' => $period]);
                }
            } elseif (method_exists($this, $model_key)) {
                // 自开彩
                $numbers = [];
                if (0 == $period) {
                    $sql = 'select period,plan_time from lottery_period_opening where game_key=:game_key order by plan_time';
                    foreach ($mysql->query($sql, $params) as $row) {
                        $numbers[] = $this->$model_key($row['period']);
                    }
                } else {
                    $numbers[] = $this->$model_key($period);
                }
                if (0 < count($numbers)) {
                    $defaults = ['game_key' => $game_key, 'open_time' => time()];
                    $mysql->lottery_number->load($numbers, $defaults, 'ignore');
                    $lastNumber = end($numbers);
                    $adapter->plan('NotifySite', ['path' => 'Lottery/Number', 'data' => [
                        'game_key' => $game_key, 'period' => $lastNumber['period'],
                    ]]);
                }
            }
        }
    }
    private function tiktok(string $period): array
    {
        return [
            'period' => $period,
            'normal1' => rand(0, 9),
            'normal2' => rand(0, 9),
            'normal3' => rand(0, 9),
            'normal4' => rand(0, 9),
            'normal5' => rand(0, 9),
        ];
    }
    private function dice(string $period): array
    {
        return [
            'period' => $period,
            'normal1' => rand(1, 6),
            'normal2' => rand(1, 6),
            'normal3' => rand(1, 6),
        ];
    }
    private function lucky(string $period): array
    {
        $random = range(1, 20);
        shuffle($random);
        return [
            'period' => $period,
            'normal1' => $random[0],
            'normal2' => $random[1],
            'normal3' => $random[2],
            'normal4' => $random[3],
            'normal5' => $random[4],
            'normal6' => $random[5],
            'normal7' => $random[6],
            'normal8' => $random[7],
        ];
    }
    private function racer(string $period): array
    {
        $random = range(1, 10);
        shuffle($random);
        return [
            'period' => $period,
            'normal1' => $random[0],
            'normal2' => $random[1],
            'normal3' => $random[2],
            'normal4' => $random[3],
            'normal5' => $random[4],
            'normal6' => $random[5],
            'normal7' => $random[6],
            'normal8' => $random[7],
            'normal9' => $random[8],
            'normal10' => $random[9],
        ];
    }
    private function eleven(string $period): array
    {
        $random = range(1, 11);
        shuffle($random);
        return [
            'period' => $period,
            'normal1' => $random[0],
            'normal2' => $random[1],
            'normal3' => $random[2],
            'normal4' => $random[3],
            'normal5' => $random[4],
        ];
    }
    private function six(string $period): array
    {
        $random = range(1, 49);
        shuffle($random);
        return [
            'period' => $period,
            'normal1' => $random[0],
            'normal2' => $random[1],
            'normal3' => $random[2],
            'normal4' => $random[3],
            'normal5' => $random[4],
            'normal6' => $random[5],
            'special1' => $random[6],
        ];
    }
    private function ladder(string $period): array
    {
        return [
            'period' => $period,
            'normal1' => rand(1, 2),
            'special1' => rand(3, 4),
        ];
    }
}
