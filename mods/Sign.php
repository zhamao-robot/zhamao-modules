<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/4/10
 * Time: 14:35
 */

namespace crazybot\mods;

use crazybot\CrazyBot;
use crazybot\manager\TimeManager;
use crazybot\utils\DataProvider as DP;
use crazybot\utils\ZMUtil;
use framework\ZMBuf;

class Sign extends ModBase
{
    public function __construct(CrazyBot $main, $data) {
        parent::__construct($main, $data);
    }

    public function onMessage($msg) {
        $cq = ZMUtil::getCQ($msg);
        if (($cq !== null && $cq["type"] == "sign") || mb_strpos($msg, '群签到') !== false) {
            $this->doSign();
        }
        return true;
    }

    public function onStart() {
        $sign = DP::getJsonData("Sign_config.json");
        ZMBuf::set("sign_mode", $sign["mode"] ?? true);
        ZMBuf::set("sign_attribute", $sign["attribute"] ?? []);
        ZMBuf::set("sign_first", $sign["first"] ?? []);
    }

    public function onSave() {
        $sign = DP::getJsonData("Sign_config.json");
        $sign["mode"] = ZMBuf::get("sign_mode");
        $sign["attribute"] = ZMBuf::get("sign_attribute");
        $sign["first"] = ZMBuf::get("sign_first");
        DP::setJsonData("Sign_config.json", $sign);
    }

    /**
     * 签到
     * @return bool
     */
    public function sign() {
        if ($this->getMessageType() == "wechat") {
            $this->reply("微信暂不支持此功能哦！");
            return true;
        }
        if ($this->doSign() === false) {
            $this->reply("你已经签过到啦！");
        }
        return true;
    }

    /**
     * 设置签到
     * @param $arg
     * @return bool
     */
    public function signSettings($arg) {
        if (!$this->isAdmin($this->getUserId())) return true;
        if (isset($arg[1])) {
            switch ($arg[1]) {
                case "开启":
                    ZMBuf::set("sign_mode", true);
                    $this->reply("成功开启签到功能！");
                    return true;
                case "关闭":
                    ZMBuf::set("sign_mode", false);
                    $this->reply("成功关闭签到功能！");
                    return true;
                case "积分范围":
                case "首签加成":
                    if (count($arg) < 4) {
                        $this->reply("用法：设置签到 " . $arg[1] . " min max");
                        return true;
                    }
                    if (!is_numeric($arg[2]) || !is_numeric($arg[3])) {
                        $this->reply("请输入数字！");
                        return true;
                    }
                    $min = intval($arg[2]);
                    $max = intval($arg[3]);
                    if ($min > $max) {
                        $this->reply("请输入从小到大的数字！");
                        return true;
                    }
                    $attribute = ZMBuf::get("sign_attribute");
                    $attribute[($arg[1] == "积分范围" ? "point" : "first")] = [$min, $max];
                    ZMBuf::set("sign_attribute", $attribute);
                    $this->reply("成功设置签到" . $arg[1] . "为从" . $min . "到" . $max . "!");
                    return true;
            }
        }
        $msg = "「签到设置系统帮助」";
        $msg .= "\n设置签到 积分范围 min max";
        $msg .= "\n设置签到 开启：开启签到功能";
        $msg .= "\n设置签到 关闭：关闭签到功能";
        $msg .= "\n设置签到 首签加成 min max";
        $this->reply($msg);
        return true;
    }

//API part
    private function initData() {
        DP::query("INSERT INTO sign_data (user_id, point, sign_day_long, last_sign, sign_count) VALUES (?,?,?,?,?)", [$this->getUserId(), 0, 1, 0, 0]);
        return [
            "user_id" => $this->getUserId(),
            "point" => 0,
            "sign_day_long" => 1,
            "last_sign" => 0,
            "sign_count" => 0
        ];
    }

    private function doSign() {
        $sign_data = DP::query("SELECT * FROM sign_data WHERE user_id = ?", [$this->getUserId()]);
        if ($sign_data == []) $sign_data = $this->initData();
        else $sign_data = $sign_data[0];
        $today_zero = TimeManager::getTodayZero();
        if ($sign_data["last_sign"] >= $today_zero) return false;//签过了

        $last_day_zero = $today_zero - 86400;
        if ($sign_data["last_sign"] >= $last_day_zero) {//是不是连签
            $sign_data["sign_day_long"]++;
        } else {
            $sign_data["sign_day_long"] = 1;
        }
        $sign_data["sign_count"]++;
        $sign_data["last_sign"] = microtime(true);

        $msg = "「签到成功」";
        $attribute = ZMBuf::get("sign_attribute");
        $add_count = mt_rand($attribute["point"][0], $attribute["point"][1]);
        if (!ZMBuf::array_key_exists("sign_first", date("Ymd"))) {
            $counts = mt_rand($attribute["first"][0], $attribute["first"][1]);
            $add_count += $counts;
            $msg .= "\n今日首签加成：+" . $counts . "，棒棒哒～";
            $is_first = true;
            ZMBuf::appendKey("sign_first", date("Ymd"), $this->getUserId());
        }
        if ($sign_data["sign_day_long"] >= 3) {
            $add_count++;
            $msg .= "\n连续签到加成：+1";
            $msg .= "\n连续签到：" . $sign_data["sign_day_long"] . "天";
        }
        $msg .= "\n本次签到共获得：+" . $add_count;
        $sign_data["point"] += $add_count;
        DP::query("UPDATE sign_data SET point = ?, sign_day_long = ?, last_sign = ?, sign_count = ? WHERE user_id = ?", [
            $sign_data["point"],
            $sign_data["sign_day_long"],
            $sign_data["last_sign"],
            $sign_data["sign_count"],
            $this->getUserId()
        ]);
        DP::query("INSERT INTO sign_record (user_id, sign_time, add_point, is_first) VALUES (?,?,?,?)", [
            $this->getUserId(),
            $sign_data["last_sign"],
            $add_count,
            (isset($is_first) ? 1 : 0)
        ]);
        $msg .= "\n累计签到：" . $sign_data["sign_count"] . "次";
        $msg .= "\n当前积分：" . $sign_data["point"];
        return $this->reply($msg);
    }
}
