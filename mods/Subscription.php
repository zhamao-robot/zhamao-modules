<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/2/10
 * Time: 下午6:58
 */

namespace crazybot\mods;

use crazybot\api\CQAPI;
use crazybot\CrazyBot;
use crazybot\manager\TimeManager;
use crazybot\traits\SubscriptionAPI;
use crazybot\utils\DataProvider as DP;
use crazybot\utils\ZMRequest;
use framework\{Console, ZMbuf};
use simple_html_dom;
use simple_html_dom_node;
use Swoole\Coroutine\Http\Client;

/**
 * Class Subscription
 * 订阅功能模块
 */
class Subscription extends ModBase
{
    use SubscriptionAPI;

    public function __construct(CrazyBot $main, $data) { parent::__construct($main, $data); }

    public function onStart() {
        ZMbuf::set("jw_list", DP::getJsonData("Subscription_jw.json"));
        ZMBuf::set("jw_subscription", DP::getJsonData("Subscription_jw_user.json"));
        ZMBuf::set("subscription_list", DP::getJsonData("Subscription_data.json"));
    }

    public function onSave() {
        DP::setJsonData("Subscription_jw.json", ZMbuf::get("jw_list"));
        DP::setJsonData("Subscription_jw_user.json", ZMBuf::get("jw_subscription"));
        DP::setJsonData("Subscription_data.json", ZMBuf::get("subscription_list"));
    }

    public function onTick($tick) {
        if ($tick % 1800 == 0 && $tick != 0) {
            self::checkJwList();
        }
    }

    /**
     * 教务群订阅|教务处群订阅
     * @return bool
     */
    public function jwGroupSub() {
        if ($this->data["message_type"] != "group") return $this->reply("请在QQ群内（管理员或群主）使用！");
        if ($this->data['sender']['role'] == 'member' && !in_array($this->getUserId(), ZMBuf::get('su'))) return false;
        return $this->reply("教务处订阅是东华大学一项特殊的订阅服务。在jw.dhu.edu.cn页面如果有发布新的教务信息，炸毛会自动转发链接到本群\n开启群订阅请回：开启教务群订阅\n取消订阅请回：关闭教务群订阅");
    }

    /**
     * 开启教务群订阅
     * @return bool
     */
    public function openJwGroupSub() {
        if ($this->data["message_type"] != "group") return $this->reply("请在QQ群内（管理员或群主）使用！");
        if ($this->data['sender']['role'] == 'member' && !in_array($this->getUserId(), ZMBuf::get('su'))) return false;
        $ls = ZMBuf::get("jw_subscription");
        if (!in_array(strval("group:" . $this->data["group_id"]), $ls[strval($this->getRobotId())])) $ls[strval($this->getRobotId())][] = strval("group:" . $this->data["group_id"]);
        ZMBuf::set("jw_subscription", $ls);
        return $this->reply("成功订阅教务处！关闭群订阅请回复：关闭教务群订阅");
    }

    /**
     * 关闭教务群订阅
     * @return bool
     */
    public function closeJwGroupSub() {
        if ($this->data["message_type"] != "group") return $this->reply("请在QQ群内（管理员或群主）使用！");
        if ($this->data['sender']['role'] == 'member' && !in_array($this->getUserId(), ZMBuf::get('su'))) return false;
        $ls = ZMBuf::get("jw_subscription");
        if (in_array(strval("group:" . $this->data["group_id"]), $ls[strval($this->getRobotId())])) {
            $inv = array_search(strval("group:" . $this->data["group_id"]), $ls[strval($this->getRobotId())]);
            array_splice($ls[strval($this->getRobotId())], $inv, 1);
            ZMBuf::set("jw_subscription", $ls);
            $this->reply("成功关闭教务群订阅！再次开启请回：教务群订阅");
        } else $this->reply("你没有在这个炸毛账号上订阅过教务处群通知哦！");
        return true;
    }

    /**
     * 教务订阅|教务处订阅
     * @return bool
     */
    public function jwSub() {
        return $this->reply("暂时无法开启私人教务订阅，请使用\"教务群订阅\"！");
    }

    /**
     * 开启教务订阅
     * @return bool
     */
    public function openJwSub() {
        return $this->reply("暂时无法开启私人教务订阅，请使用教务群订阅！");
        //$ls = ZMBuf::get("jw_subscription");
        //if (!in_array(strval($this->getUserId()), $ls[strval($this->getRobotId())])) $ls[strval($this->getRobotId())][] = strval($this->getUserId());
        //ZMBuf::set("jw_subscription", $ls);
        //return $this->reply("成功订阅教务处！关闭订阅请回复：关闭教务订阅");
    }

    /**
     * 关闭教务订阅
     * @return bool
     */
    public function closeJwSub() {
        $ls = ZMBuf::get("jw_subscription");
        if (in_array(strval($this->getUserId()), $ls[strval($this->getRobotId())])) {
            $inv = array_search($this->getUserId(), $ls[strval($this->getRobotId())]);
            array_splice($ls[strval($this->getRobotId())], $inv, 1);
            ZMBuf::set("jw_subscription", $ls);
            $this->reply("成功关闭教务处订阅！再次开启请回：教务订阅");
        } else $this->reply("你没有在这个炸毛账号上订阅过教务处通知哦！如果提醒还存在，请通过其他炸毛账号关闭。");
        return true;
    }

    /**
     * 历史上的今天订阅|历史上的今天群订阅
     * @return bool
     */
    public function historySub() {
        return $this->reply("历史上的今天订阅暂时停止维护，敬请谅解！");
    }

    /**
     * 每日一句订阅|每日一句群订阅
     * @param $it
     * @return bool
     */
    public function sentenceSub($it) {
        if (mb_strpos($it[0], "群") !== false) {
            if ($this->getMessageType() != "group") return $this->reply("请在群内使用！（群内必须为管理员）");
            if ($this->data['sender']['role'] == "member") return $this->reply("仅限管理员使用！");
            $group = $this->data["group_id"];
        }
        if (($type = $this->getKingsoftTypeParam($it)) === false) return true;
        if (($time = $this->checkTimeParam($it)) === false) return true;
        $time2 = TimeManager::getTodayTimeByHourMinSec(mb_substr($time, 0, 2), mb_substr($time, 2), 0);
        $obj = [
            "type" => '每日一句',
            "post_type" => isset($group) ? "group" : "private",
            (isset($group) ? "group_id" : "user_id") => $group ?? $this->getUserId(),
            "robot_id" => $this->getRobotId(),
            "addition_type" => $type
        ];
        ScheduleTask::addScheduleTask("subscription", "每日一句订阅", $time2, $time2, "daily", "false", "0M", $obj);
        $schedule_list = ZMBuf::get("schedule_list");
        $obj['event_type'] = 'subscription';
        $schedule_list[$time2][] = $obj;
        ZMBuf::set("schedule_list", $schedule_list);
        return $this->reply(
            "成功订阅每日一句，" .
            ZMBuf::globals("robot_alias")[$this->getRobotId()] .
            "将在每日" . TimeManager::getDateFormat($time) .
            "提醒" . (!isset($group) ? "你" : "到本群") .
            "\n查看已订阅内容或取消订阅请回复：查看" . (!isset($group) ? "" : "群") . "订阅"
        );
    }

    /**
     * 知乎订阅|知乎群订阅
     * @param $arg
     * @return bool
     */
    public function zhihuSub($arg) {
        if (mb_strpos($arg[0], "群") !== false) {
            if ($this->getMessageType() != "group") return $this->reply("请在群内使用！（群内必须为管理员）");
            if ($this->data['sender']['role'] == "member") return $this->reply("仅限管理员使用！");
            $group = $this->data["group_id"];
        }
        if (($count = $this->checkZhihuParam($arg)) === false) return true;
        if (($time = $this->checkTimeParam($arg)) === false) return true;
        $time2 = TimeManager::getTodayTimeByHourMinSec(mb_substr($time, 0, 2), mb_substr($time, 2), 0);
        $obj = [
            "type" => '知乎',
            "post_type" => isset($group) ? "group" : "private",
            (isset($group) ? "group_id" : "user_id") => $group ?? $this->getUserId(),
            "robot_id" => $this->getRobotId(),
            "addition_count" => $count
        ];
        ScheduleTask::addScheduleTask("subscription", "知乎订阅", $time2, $time2, "daily", "false", "0M", $obj);
        $schedule_list = ZMBuf::get("schedule_list");
        $obj['event_type'] = 'subscription';
        $schedule_list[$time2][] = $obj;
        ZMBuf::set("schedule_list", $schedule_list);
        return $this->reply(
            "成功订阅知乎日报！" .
            ZMBuf::globals("robot_alias")[$this->getRobotId()] .
            "将在每日" . TimeManager::getDateFormat($time) .
            "推送" . (!isset($group) ? "给你" : "到本群") .
            "\n查看已订阅内容或取消订阅请回复：查看" . (!isset($group) ? "" : "群") . "订阅"
        );
    }

    /**
     * 天气订阅|天气群订阅
     * @param $arg
     * @return bool
     */
    public function weatherSub($arg) {
        if ($this->checkWechat()) return true;
        if (mb_strpos($arg[0], "群") !== false) {
            if ($this->getMessageType() != "group") return $this->reply("请在群内使用！（群内必须为管理员）");
            if ($this->data['sender']['role'] == "member") return $this->reply("仅限管理员使用！");
            $group = $this->data["group_id"];
        }
        if (($time = $this->checkTimeParam($arg)) === false) return true;
        if (($date = $this->checkWeatherDateParam($arg)) === false) return true;
        if (($city = $this->checkWeatherCityParam($arg)) === false) return true;
        Console::info("Final test: " . $city);
        $time2 = TimeManager::getTodayTimeByHourMinSec(mb_substr($time, 0, 2), mb_substr($time, 2), 0);
        $obj = [
            "type" => '天气',
            "post_type" => isset($group) ? "group" : "private",
            (isset($group) ? "group_id" : "user_id") => $group ?? $this->getUserId(),
            "robot_id" => $this->getRobotId(),
            "addition_date" => $date,
            "addition_city" => $city
        ];
        ScheduleTask::addScheduleTask("subscription", "天气订阅：" . $city, $time2, $time2, "daily", "false", "0M", $obj);
        $schedule_list = ZMBuf::get("schedule_list");
        $obj['event_type'] = 'subscription';
        $schedule_list[$time2][] = $obj;
        ZMBuf::set("schedule_list", $schedule_list);
        return $this->reply(
            "成功订阅" . $city . "天气，" .
            ZMBuf::globals("robot_alias")[$this->getRobotId()] .
            "将在每日" . TimeManager::getDateFormat($time) .
            "提醒" . (!isset($group) ? "你" : "到本群") .
            "\n查看已订阅内容或取消订阅请回复：查看" . (!isset($group) ? "" : "群") . "订阅"
        );
    }

    /**
     * 星座运势订阅
     * @param $arg
     * @return bool
     */
    public function starSub($arg) {
        if ($this->checkWechat()) return true;
        if (($star = $this->checkStarParam($arg)) === false) return true;
        if (($date = $this->checkStarDateParam($arg)) === false) return true;
        if (($time = $this->checkTimeParam($arg)) === false) return true;
        $time2 = TimeManager::getTodayTimeByHourMinSec(mb_substr($time, 0, 2), mb_substr($time, 2), 0);
        $obj = [
            "type" => '星座',
            "post_type" => "private",
            "user_id" => $this->getUserId(),
            "robot_id" => $this->getRobotId(),
            "addition_date" => $date,
            "addition_star" => $star
        ];
        ScheduleTask::addScheduleTask("subscription", "星座运势订阅：" . $star, $time2, $time2, "daily", "false", "0M", $obj);
        $schedule_list = ZMBuf::get("schedule_list");
        $obj['event_type'] = 'subscription';
        $schedule_list[$time2][] = $obj;
        ZMBuf::set("schedule_list", $schedule_list);
        return $this->reply(
            "成功订阅" . $star . $date . "运势，" .
            ZMBuf::globals("robot_alias")[$this->getRobotId()] .
            "将在每日" . TimeManager::getDateFormat($time) . "提醒你。" .
            "\n查看已订阅内容或取消订阅请回复：查看订阅"
        );
    }

    /**
     * 查看群订阅
     * @return bool
     */
    public function listGroupSub() {
        if ($this->getMessageType() != "group") return $this->reply("请在群内使用！（群内必须为管理员）");
        if ($this->data['sender']['role'] == "member") return $this->reply("仅限管理员使用！");
        $ls = [];
        $lsr = DP::query("SELECT * FROM schedule_task WHERE event_type = ?", ['subscription']);
        foreach ($lsr as $k => $v) {
            $v['event'] = json_decode($v['event'], true);
            if ($v['event']['post_type'] == 'group' && $v['event']['group_id'] == $this->data['group_id']) $ls[] = $v;
        }
        if (empty($ls)) return $this->reply("本群还没有订阅任何内容哦！快去订阅一个吧！");
        $msg = "=====订阅列表=====";
        foreach ($ls as $k => $value) {
            $msg .= "\n【ID：" . $value['id'] . "】";
            $msg .= "\n类型：" . $value['event']["type"];
            $p = [];
            foreach ($value['event'] as $r => $rs) {
                if (mb_substr($r, 0, 9) == "addition_") {
                    $p[] = $rs;
                }
            }
            $msg .= "\n详情：" . implode("，", $p);
            $msg .= "\n时间：" . date('H:i', $value['start_time']);
        }
        $msg .= "\n\n取消订阅请输入：取消群订阅  ID\n例如：取消群订阅  30";
        return $this->reply($msg);
    }

    /**
     * 查看订阅|显示订阅
     * @return bool
     */
    public function listSub() {
        $ls = [];
        $lsr = DP::query("SELECT * FROM schedule_task WHERE event_type = ?", ['subscription']);
        foreach ($lsr as $k => $v) {
            $v['event'] = json_decode($v['event'], true);
            if ($v['event']['post_type'] == 'private' && $v['event']['user_id'] == $this->getUserId()) $ls[] = $v;
        }
        if (empty($ls)) {
            $this->reply("你还没有订阅任何内容哦！快先去订阅一个吧！");
            return true;
        }
        $msg = "=====订阅列表=====";
        foreach ($ls as $key => $value) {
            $msg .= "\n【ID：" . $value['id'] . "】";
            $msg .= "\n类型：" . $value['event']["type"];
            $p = [];
            foreach ($value['event'] as $r => $rs) {
                if (mb_substr($r, 0, 9) == "addition_") {
                    $p[] = $rs;
                }
            }
            $msg .= "\n详情：" . implode("，", $p);
            $msg .= "\n时间：" . date('H:i', $value['start_time']);
        }
        if (in_array(strval($this->getUserId()), ZMBuf::get("jw_subscription")[strval($this->getRobotId())])) {
            $msg .= "\n* 你有教务订阅，取消教务订阅请回：\"关闭教务订阅\"";
        }
        $msg .= "\n取消订阅请输入：取消订阅  ID\n例如：取消订阅  30";
        $this->reply($msg);
        return true;
    }

    /**
     * 检查教务
     */
    public function checkJw() {
        if (!$this->isAdmin()) return false;
        self::checkJwList();
        return true;
    }

    /**
     * 取消群订阅|删除群订阅
     * @param $arg
     * @return bool
     */
    public function cancelGroupSub($arg) {
        $ls = DP::query("SELECT * FROM schedule_task WHERE event_type = ? AND event -> '$.post_type' = ? AND event -> '$.group_id' = ?", [
            'subscription',
            'group',
            $this->data['group_id']
        ]);
        if (empty($ls)) return $this->reply("本群还没有订阅任何内容哦！快先去订阅一个吧！");
        elseif (!isset($arg[1])) return $this->reply("用法：" . $arg[0] . "   ID\n查看订阅ID请回复：查看群订阅");
        else {
            foreach ($ls as $k => $v) {
                if ($v['id'] == $arg[1]) {
                    DP::query('DELETE FROM schedule_task WHERE id = ?', [$arg[1]]);
                    return $this->reply("成功取消群订阅，从第二天0点起将不再提醒！");
                }
            }
            return $this->reply("本群没有ID为" . $arg[1] . "的订阅项目，查看已订阅的ID请回复：查看群订阅");
        }
    }

    /**
     * 取消订阅|删除订阅
     * @param $arg
     * @return bool
     */
    public function cancelSub($arg) {
        $ls = DP::query("SELECT * FROM schedule_task WHERE event_type = ? AND event -> '$.post_type' = ? AND event -> '$.user_id' = ?", ['subscription', 'private', $this->getUserId()]);
        if (empty($ls))
            $this->reply("你还没有订阅任何内容哦！快先去订阅一个吧！");
        elseif (!isset($arg[1]))
            $this->reply("用法：删除订阅   ID\n订阅ID获取请回复：查看订阅");
        else {
            foreach ($ls as $k => $v) {
                if ($v['id'] == $arg[1]) {
                    DP::query('DELETE FROM schedule_task WHERE id = ?', [$arg[1]]);
                    return $this->reply("成功取消订阅，从第二天0点起将不再提醒！");
                }
            }
            return $this->reply("你没有ID为" . $arg[1] . "的订阅项目，查看订阅ID请回复：查看订阅");
        }
        return true;
    }

//API part
    private function isValidTime($in) {
        if (strlen($in) != 4) return false;
        $h = substr($in, 0, 2);
        $ii = substr($in, 2);
        for ($i = 0; $i < 4; $i++) {
            if (!is_numeric(substr($in, $i, 1))) return false;
        }
        if ($h === false || $ii === false) return false;
        if ($h > 23 || $h < 0 || $ii > 59 || $ii < 0) return false;
        return true;
    }

    public static function checkJwList() {
        Console::info("正在获取教务处信息...");
        $cli = new Client('jw.dhu.edu.cn', 80);
        $cli->setHeaders([
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Language' => 'zh-CN,zh;q=0.9,en-US;q=0.8,en;q=0.7',
            'Connection' => 'keep-alive',
            'Pragma' => 'no-cache',
            'Upgrade-Insecure-Requests' => '1',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/536.36 (KHTML, like Gecko) Chrome/66.1.4323.42 Safari/537.36'
        ]);
        $cli->set(['timeout' => 10]);
        $cli->get('/tzggwxszl/list.htm');
        if ($cli->errCode != 0 || $cli->statusCode != 200) {
            Console::warning("无法连接到dhu jw，错误码：" . $cli->statusCode);
            return;
        }
        $s = new simple_html_dom();
        $s->load($cli->body);
        /** @var simple_html_dom_node $r */
        $r = $s->find('#wp_news_w6');
        if (!empty($r)) $r = $r[0];
        else {
            Console::error("获取教务处信息失败！");
            return;
        }
        $r = $r->find(".list_item");
        $list = ZMbuf::get("jw_list");
        Console::info("获取成功！");
        /** @var simple_html_dom_node $v */
        foreach ($r as $v) {
            $sr = $v->find("a")[0];
            $fields = $v->find(".Article_PublishDate")[0];
            $contain = false;
            foreach ($list as $ks => $vs) {
                if ($vs["link"] == trim('http://jw.dhu.edu.cn' . $sr->href) || trim($sr->plaintext) == "校外网络使用VPN访问本科教务管理系统的说明") {
                    $contain = true;
                    break;
                }
            }
            if ($contain !== true) {
                $list[] = [
                    "title" => trim($sr->plaintext),
                    "link" => 'http://jw.dhu.edu.cn' . $sr->href,
                    "time" => trim($fields->plaintext),
                    "status" => 0
                ];
                Console::info("找到新的教务处推送。");
            }
        }
        Console::info("Information...");
        $starttime = microtime(true);
        $run = false;
        foreach ($list as $key => $value) {
            if ($value["status"] === 0) {
                $run = true;
                $list[$key]["status"] = 1;
                $msg = $list[$key]["title"] . "\n" . $list[$key]["link"];
                //unset($msg);
                foreach (ZMBuf::get("jw_subscription") as $k => $v) {
                    foreach ($v as $ks => $vs) {
                        //$r = mt_rand(5, 50);
                        //Co::sleep($r / 10);
                        if (mb_substr($vs, 0, 6) == "group:") {
                            if ((CQAPI::send_group_msg($k, mb_substr($vs, 6), self::getRandomTitle() . $msg, true)["retcode"] ?? -1) != 0) {
                                Console::warning("教务处消息未正确送达：" . $vs);
                                $list2 = ZMBuf::get("jw_subscription");
                                unset($list2[$k][$ks]);
                                ZMBuf::set("jw_subscription", $list2);
                            }
                        }
                    }
                }
                break;
            }
        }
        ZMbuf::set("jw_list", $list);
        if ($run) {
            $msg = "教务处任务推送完成，共用时 " . round(microtime(true) - $starttime, 2) . " 秒";
            CQAPI::debug($msg);
            Console::info($msg);
        } else Console::info("教务处暂无更新.");
    }

    private static function getRandomTitle() {
        $array = [
            "炸毛教务有新的通知啦！", "教务有新通知了！", "诶，教务处更新了！",
            "教务处有更新了哦！", "请查收教务处消息！", "教务处：",
            "东华大学教务处：", "教务处最新消息：", "教务处通知："
        ];
        return $array[array_rand($array)];
    }

    /**
     * 当二次输入超时时返回超时提醒
     * @param $it
     * @return bool
     */
    private function timeoutReply($it) { return $this->reply("输入超时，请重新输入指令：" . $it[0]); }

    /**
     * 检查时间
     * @param $it
     * @return string|bool
     */
    private function checkTimeParam(&$it) {
        foreach ($it as $k => $v) {
            if (strlen($v) == 4 && is_numeric($v) && $this->isValidTime($v)) {
                $inv = array_search($v, $it);
                if ($inv !== false) array_splice($it, $inv, 1);
                return $v;
            }
        }
        $wait = $this->waitMessage("请告诉炸毛你想接收订阅的时间：\n格式：四位数，小时分钟\n例如：1950(代表19点50分)\n注：请尽量避免整点(如21点整)设置时间");
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        if (strlen($wait) == 4 && is_numeric($wait) && $this->isValidTime($wait)) return $wait;
        $wait = $this->waitMessage("你输入的时间格式有误，请重新输入！\n再次输入错误你需要重新回复：" . $it[0]);
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        if (strlen($wait) == 4 && is_numeric($wait) && $this->isValidTime($wait)) return $wait;
        $this->reply("时间格式输入错误，中止订阅。");
        return false;
    }

    /**
     * @param $it
     * @return string|bool
     */
    private function checkStarParam(&$it) {
        foreach ($it as $k => $v) {
            if ($this->getPostStar($v) !== null) {
                $inv = array_search($v, $it);
                if ($inv !== false) array_splice($it, $inv, 1);
                return $v;
            }
        }
        $wait = $this->waitMessage("请说你想要订阅的星座：\n例如：天秤座 / 天蝎座 / 摩羯座");
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        if ($this->getPostStar($wait) !== null) return $wait;
        $wait = $this->waitMessage("你输入的星座名\"" . $wait . "\"有误，请重新输入！\n注意星座名称带\"座\"字");
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        if ($this->getPostStar($wait) !== null) return $wait;
        $this->reply("时间格式输入错误，中止订阅。");
        return false;
    }

    private function checkStarDateParam(&$it) {
        foreach (['今日', '明日'] as $k => $v) {
            if (in_array($v, $it)) {
                $inv = array_search($v, $it);
                if ($inv !== false) array_splice($it, $inv, 1);
                return $v;
            }
        }
        $wait = $this->waitMessage("你想要订阅什么时候的运势？\n今日 / 明日");
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        if (in_array($wait, ["今日", "明日"])) return $wait;
        $wait = $this->waitMessage("你输入的订阅类型\"" . $wait . "\"有误，请重新输入！");
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        if (in_array($wait, ["今日", "明日"])) return $wait;
        $this->reply("订阅类型输入错误，中止订阅。");
        return false;
    }

    private function checkWeatherDateParam(&$it) {
        foreach (['今日', '明日', '未来'] as $p => $vs) {
            if (in_array($vs, $it)) {
                $inv = array_search($vs, $it);
                if ($inv !== false) array_splice($it, $inv, 1);
                return $vs;
            }
        }
        $wait = $this->waitMessage("你想要订阅什么时候的天气？\n今日 / 明日 / 未来");
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        if (in_array($wait, ["今日", "明日", "未来"])) return $wait;
        $wait = $this->waitMessage("你输入的订阅类型\"" . $wait . "\"有误，请重新输入！");
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        if (in_array($wait, ["今日", "明日", "未来"])) return $wait;
        $this->reply("订阅类型输入错误，中止订阅。");
        return false;
    }

    private function checkWeatherCityParam(&$it) {
        for ($i = 1; $i < count($it); $i++) {
            $query = ZMRequest::get(ZMBuf::globals("weather_api")["origin"] . urlencode($it[$i]));
            $result = json_decode($query, true);
            if (($result["HeWeather6"][0]["status"] ?? 0) == "ok") {
                Console::info("Returning " . $it[$i]);
                return $it[$i];
            }
            Console::info("Checking failed " . $it[$i]);
        }
        $wait = $this->waitMessage("请说你想要订阅天气的城市:");
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        $query = ZMRequest::get(ZMBuf::globals("weather_api")["origin"] . urlencode($wait));
        $result = json_decode($query, true);
        if (($result["HeWeather6"][0]["status"] ?? 0) == "ok") return $wait;
        $wait = $this->waitMessage("你输入的城市名\"$wait\"有误，请重新输入！");
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        $query = ZMRequest::get(ZMBuf::globals("weather_api")["origin"] . urlencode($wait));
        $result = json_decode($query, true);
        if (($result["HeWeather6"][0]["status"] ?? 0) == "ok") return $wait;
        $this->reply("城市名解析失败，可能城市名有误，中止订阅。");
        return false;
    }

    private function checkZhihuParam($it) {
        foreach ($it as $k => $v) {
            if (strlen($v) == 1 && is_numeric($v) && $v >= 1 && $v <= 5) {
                $inv = array_search($v, $it);
                if ($inv !== false) array_splice($it, $inv, 1);
                return intval($v);
            }
        }
        $wait = $this->waitMessage("请输入你想订阅的条数的数字：（1-5）");
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        if (strlen($wait) == 1 && is_numeric($wait) && $wait >= 1 && $wait <= 5) return $wait;
        $wait = $this->waitMessage("你输入的数字格式有误，请输入1-5的数字！");
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        if (strlen($wait) == 1 && is_numeric($wait) && $wait >= 1 && $wait <= 5) return $wait;
        $this->reply("你输入的数字格式有误，中止订阅！");
        return false;
    }

    private function getKingsoftTypeParam($it) {
        $arr = ['图文', '文字', '文字语音', '图文语音'];
        foreach ($arr as $k => $v) {
            if (in_array($v, $it)) {
                $inv = array_search($v, $it);
                if ($inv !== false) array_splice($it, $inv, 1);
                return $v;
            }
        }
        $wait = $this->waitMessage("请选择你想接收每日一句的消息类型：\n==目前支持==\n图文：图片内包含双语句子\n文字：文字双语形式\n文字语音：文字带句子发音\n图文语音：图片带句子发音");
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        if (in_array($wait, $arr)) return $wait;
        $wait = $this->waitMessage("你输入的类型有误，请重新输入！\n支持：图文 / 文字 / 文字语音 / 图文语音");
        if ($wait === null) return $this->timeoutReply($it);
        $wait = trim($wait);
        if (in_array($wait, $arr)) return $wait;
        $this->reply("你输入的类型有误，中止订阅！");
        return false;
    }
}
