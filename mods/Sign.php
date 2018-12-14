<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/4/10
 * Time: 14:35
 */

use DataProvider as DP;

class Sign extends ModBase
{
    public function __construct(CrazyBot $main, $data) {
        parent::__construct($main, $data);
        $cq = ZMUtil::getCQ($data["message"]);
        if ($cq !== null) {
            if ($cq["type"] == "sign") {
                $time = $this->getUser()->getSignTime();//[年月日，小时分钟秒，时间戳]
                if ($time[0] != date("Ymd")) {
                    $this->doSign($time);
                }
            }
        }
    }

    public static function initValues() {
        $sign = DP::getJsonData("Sign_config.json");
        ZMBuf::set("sign_mode", $sign["mode"] ?? true);
        ZMBuf::set("sign_attribute", $sign["attribute"] ?? []);
    }

    public static function saveValues() {
        $sign = DP::getJsonData("Sign_config.json");
        $sign["mode"] = ZMBuf::get("sign_mode");
        $sign["attribute"] = ZMBuf::get("sign_attribute");
        DP::setJsonData("Sign_config.json", $sign);
    }

    public function execute($it) {
        switch ($it[0]) {
            case "强行签到":
                if (!$this->main->isAdmin()) return false;
                $date = date("Y", time() - 86400);
                $date2 = date("m", time() - 86400);
                $date3 = date("d", time() - 86400);
                $this->doSign([$date, $date2, $date3]);
                return true;
            case "签到":
                if ($this->getMessageType() == "wechat") {
                    $this->reply("微信暂不支持此功能哦！");
                    return true;
                }
                $time = $this->getUser()->getSignTime();//[年月日，小时分钟秒，时间戳]
                if ($time[0] != date("Ymd")) {
                    $this->doSign($time);
                }
                return true;
            case "签到排行":
                $users = ZMUtil::getAllUsers(true);
                $points = [];
                foreach ($users as $k => $v) {
                    $points[$k] = $v->getPoint();
                }
                arsort($points);
                $msg = "「积分排行」";
                $i = 0;
                foreach ($points as $k => $v) {
                    if ($i >= 7) break;
                    $msg .= "\n[" . ($i + 1) . "] " . $k . ": " . $v . "积分";
                    $i++;
                }
                $msg .= "\n===============";
                $msg .= "\n「连签排行」";
                $points = [];
                foreach ($users as $k => $v) {
                    $points[$k] = $v->getSignDay();
                }
                arsort($points);
                $i = 0;
                foreach ($points as $k => $v) {
                    if ($i >= 4) break;
                    $msg .= "\n[" . ($i + 1) . "] " . $k . ": " . $v . "天";
                    $i++;
                }
                $this->reply($msg);
                return true;
            case "设置签到":
                if (!$this->main->isAdmin($this->getUserId())) return true;
                if (isset($it[1])) {
                    switch ($it[1]) {
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
                            if (count($it) < 4) {
                                $this->reply("用法：设置签到 " . $it[1] . " min max");
                                return true;
                            }
                            if (!is_numeric($it[2]) || !is_numeric($it[3])) {
                                $this->reply("请输入数字！");
                                return true;
                            }
                            $min = intval($it[2]);
                            $max = intval($it[3]);
                            if ($min > $max) {
                                $this->reply("请输入从小到大的数字！");
                                return true;
                            }
                            $attribute = ZMBuf::get("sign_attribute");
                            $attribute[($it[1] == "积分范围" ? "point" : "first")] = [$min, $max];
                            ZMBuf::set("sign_attribute", $attribute);
                            $this->reply("成功设置签到" . $it[1] . "为从" . $min . "到" . $max . "!");
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
        return false;
    }

    public function isLastDay($yesterday, $today) {
        $ly = substr($yesterday, 0, 4);
        $lm = substr($yesterday, 4, 2);
        $ld = substr($yesterday, 6, 2);
        $y = substr($today, 0, 4);
        $m = substr($today, 4, 2);
        $d = substr($today, 6, 2);
        if ($lm == $m) {
            return (intval($d) - intval($ld)) == 1 ? true : false;
        } else {
            if (((intval($m) - intval($lm)) == 1) || ($lm == 12 && ($y - $ly) == 1 && $m == 1)) {
                if ($this->getMonthDay(intval($lm), intval($ly)) == intval($ld) && intval($d) == 1) return true;
                else return false;
            } else return false;
        }
    }

    private function getMonthDay($m, $y) {
        switch ($m) {
            case 1:
            case 3:
            case 5:
            case 7:
            case 8:
            case 10:
            case 12:
                return 31;
            case 4:
            case 6:
            case 9:
            case 11:
                return 30;
            default:
                if ($y % 4 == 0) return 29;
                return 28;
        }
    }

    public function doSign($time) {
        if ($this->isLastDay($time[0], date("Ymd"))) {
            $this->getUser()->setSignDay($this->getUser()->getSignDay() + 1);
        } else {
            $this->getUser()->setSignDay(1);
        }
        $time[0] = date("Ymd");
        $time[1] = date("His");
        $time[2] = microtime(true);
        $this->getUser()->setSignTime($time);
        $is_first = true;
        /**
         * @var string $k
         * @var User $v
         */
        foreach (ZMUtil::getAllUsers(true) as $k => $v) {
            $t = $v->getSignTime();
            if ($time[0] == $t[0]) {
                if ($time[1] > $t[1]) {
                    $is_first = false;
                    break;
                } elseif ($time[1] == $t[1]) {
                    if ($time[2] > $t[2]) {
                        $is_first = false;
                        break;
                    }
                }
            }
        }
        $msg = "「签到成功」";
        $this->getUser()->addSignCount(1);
        $attribute = ZMBuf::get("sign_attribute");
        $add_count = mt_rand($attribute["point"][0], $attribute["point"][1]);
        $this->getUser()->addPoint($add_count);
        $msg .= "\n获得积分：+" . $add_count;
        if ($is_first) {
            $counts = mt_rand($attribute["first"][0], $attribute["first"][1]);
            $this->getUser()->addPoint($counts);
            $msg .= "\n今日首签加成：+" . $counts . "，棒棒哒～";
        }
        if ($this->getUser()->getSignDay() >= 7) {
            $this->getUser()->addPoint(1);
            $msg .= "\n连续签到加成：+1";
            $msg .= "\n连续签到：" . $this->getUser()->getSignDay() . "天";
        }
        CQAPI::send_like($this->getConnection(), $this->getUserId());
        $msg .= "\n累计签到：" . $this->getUser()->getSignCount() . "次";
        $msg .= "\n当前积分：" . $this->getUser()->getPoint();
        $msg .= "\n炸毛给你一个赞～！";
        //$msg .= "\n「祝大家七夕节快乐哦～」";
        $this->reply($msg);
    }
}