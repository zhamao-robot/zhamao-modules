<?php

namespace crazybot\mods;

use crazybot\api\CQ;
use crazybot\api\CQAPI;
use crazybot\CrazyBot;
use crazybot\utils\{ZMRequest, ZMUtil};
use framework\Console;
use framework\ZMBuf;
use Swoole\Coroutine\Http\Client;

/**
 * Class TuringDetect
 * 图灵机器人模块
 */
class TuringDetect extends ModBase
{
    const mod_level = 20;

    public function __construct(CrazyBot $main, $data) { parent::__construct($main, $data); }

    public function onMessage($msg) {
        $data = $this->data;
        if ($this->main->isFunctionCalled()) return false;
        if(mb_substr($msg,0, 7) == "炸毛是什么垃圾") return false;
        if ($this->getMessageType() == "private" || $this->getMessageType() == "wechat") {
            if (substr($msg, 0, 1) == "*") $msg = substr($msg, 1);
            elseif (($a = mb_substr($msg, 0, 3)) == "炸毛，" || $a == "卷毛，") $msg = mb_substr($msg, 3);
            elseif (($a = mb_substr($msg, 0, 2)) == "炸毛" || $a == "卷毛") $msg = mb_substr($msg, 2);
            elseif (($a = mb_substr($msg, -3)) == "，炸毛" || $a == "，卷毛") $msg = mb_substr($msg, 0, -3);
            else return false;
            Console::info("正在执行图灵消息：" . $msg);
            $data["message"] = $msg;
            $s = new CrazyBot($data, $this->getConnection(), $this->main->circle + 1);
            if ($s->callMessage() === true) {
                $this->main->setFunctionCalled();
                $this->function_call = "block";
                return false;
            }
            if ($data["message"] == "") return false;
            $msg = self::getAPIMsg($msg, $this->getUserId());
            $this->reply($msg);
            $this->function_call = "block";
        } elseif ($this->getMessageType() == "group") {
            if (strstr($msg, "[CQ:at,qq=" . $this->getRobotId() . "]") !== false) {
                $data["message"] = trim(str_replace("[CQ:at,qq=" . $this->getRobotId() . "]", "", $msg));
                $s = new CrazyBot($data, $this->getConnection(), $this->main->circle + 1);
                if ($s->callMessage() === true) {
                    $this->main->setFunctionCalled();
                    $this->function_call = "block";
                    return false;
                }
                if ($data["message"] == "") return false;
                $msg = $this->getAPIMsg($data["message"], $data["user_id"]);
                $this->reply(CQ::at($this->getUserId()) . $msg);
                return false;
            } elseif (substr($msg, 0, 1) == "*") $msg = substr($msg, 1);
            elseif (($a = mb_substr($msg, 0, 3)) == "炸毛，" || $a == "卷毛，") $msg = mb_substr($msg, 3);
            elseif (($a = mb_substr($msg, 0, 2)) == "炸毛" || $a == "卷毛") $msg = mb_substr($msg, 2);
            elseif (($a = mb_substr($msg, -3)) == "，炸毛" || $a == "，卷毛") $msg = mb_substr($msg, 0, -3);
            elseif (mb_substr($msg, 0, 3) == "为什么" && mt_rand(0, 100) > 30) $msg = trim($msg);
            else return false;
            $data["message"] = $msg;
            $s = new CrazyBot($data, $this->getConnection(), $this->main->circle + 1);
            if ($s->callMessage() === true) {
                $this->main->setFunctionCalled();
                $this->function_call = "block";
                return false;
            }
            if ($data["message"] == "") return false;
            $msg = self::getAPIMsg($msg, $this->getUserId());
            $this->reply($msg);
        }
        return true;
    }

    public function onStart() {
        //$func = ZMUtil::getUser(date("Y"))->getFunction("turing_robot");
        $func = false;
        ZMBuf::set("turing_robot", ($func === false ? 0 : $func));
    }

    public function onSave() {
        ZMUtil::getUser(date("Y"))->setFunction("turing_robot", ZMBuf::get("turing_robot"));
    }

    /**
     * 请求图灵机器人的消息
     * @param $msg
     * @param $userId
     * @return bool|string
     */
    static public function getAPIMsg($msg, $userId) {
        Console::debug("TuringAPI[$userId]:$msg");
        $origin = $msg;
        $i = ZMBuf::get("turing_robot");
        //5个API都用光了
        if ($i >= count(ZMBuf::globals("turing_api"))) {
            Console::info("正在请求腾讯AI: ".$msg);
            $msg = self::getTencentMsg($userId, $msg);
            return $msg;
        }
        //请求第i个API
        $r = self::requestTuring($msg, $userId, ZMBuf::globals("turing_api")[$i]);
        if (!isset($r["intent"]["code"])) return "XD 哎呀，炸毛脑子突然短路了，请稍后再问我吧！";
        $status = self::getResultStatus($r);
        if ($status !== true) {
            if ($status == "err:输入文本内容超长(上限150)") return "你的话太多了！！！";
            if ($r["intent"]["code"] == 4003) {
                ZMBuf::set("turing_robot", $i + 1);
                CQAPI::debug("炸毛API " . $i . " 号的API已用尽！");
                return "哎呀，炸毛刚才有点走神了，可能忘记你说什么了，可以重说一遍吗";
            }
            ZMUtil::errorLog("图灵机器人发送错误！\n错误原始内容：" . $origin . "\n来自：" . $userId . "\n错误信息：" . $status);
            return "XD 哎呀，我突然没电了，请稍后再问我吧！";
        }
        $result = $r["results"];
        //Console::info(Console::setColor(json_encode($result, 128 | 256), "green"));
        $final = "";
        foreach ($result as $k => $v) {
            switch ($v["resultType"]) {
                case "url":
                    $final .= "\n" . $v["values"]["url"];
                    break;
                case "text":
                    $final .= "\n" . $v["values"]["text"];
                    break;
                case "image":
                    $final .= "\n" . CQ::image($v["values"]["image"]);
                    break;
            }
        }
        go(function() use ($msg, $final, $userId, $i){
            Console::info("正在保存图灵消息 $i [".$msg."]");
            $conn = ZMBuf::$sql_pool->get();
            $ps = $conn->prepare("INSERT INTO turing_record VALUES(?,?,?,?,?)");
            if ($ps === false) {
                Console::error("SQL语句查询错误！");
                Console::error("错误信息：" . $conn->error);
                ZMBuf::$sql_pool->put($conn);
                return;
            }
            $ps->execute([
                0,
                time(),
                $msg,
                trim($final),
                strval($userId)
            ]);
            if($ps->errno != 0) {
                Console::error("execute错误！".$ps->error);
            }
            $msg_tencent = trim(self::getTencentMsg($userId, $msg));
            if($msg_tencent != "") {
                $ps = $conn->prepare("INSERT INTO tencent_ai_record VALUES (?,?,?,?,?)");
                if ($ps === false) {
                    Console::error("SQL语句查询错误！");
                    Console::error("错误信息：" . $conn->error);
                    ZMBuf::$sql_pool->put($conn);
                    return;
                }
                $ps->execute([
                    0,
                    time(),
                    $msg,
                    trim($msg_tencent),
                    strval($userId)
                ]);
                if($ps->errno != 0) {
                    Console::error("execute错误！".$ps->error);
                }
            }
            ZMBuf::$sql_pool->put($conn);
        });
        return trim($final);
    }

    private static function requestTuring($msg, $user_id, $api) {
        if (($cq = ZMUtil::getCQ($msg)) !== null) {//如有CQ码则去除
            if ($cq["type"] == "image") {
                $url = $cq["params"]["url"];
                $msg = str_replace(mb_substr($msg, $cq["start"], $cq["end"] - $cq["start"] + 1), "", $msg);
            }
            $msg = trim($msg);
        }
        //构建将要发送的json包给图灵
        $content = [
            "reqType" => 0,
            "userInfo" => [
                "apiKey" => $api,
                "userId" => $user_id
            ]
        ];
        if ($msg != "") {
            $content["perception"]["inputText"]["text"] = $msg;
        }
        $msg = trim($msg);
        if (mb_strlen($msg) < 1 && !isset($url)) return "请说出你想说的话";
        if (isset($url)) {
            $content["perception"]["inputImage"]["url"] = $url;
            $content["reqType"] = 1;
        }
        if (!isset($content["perception"])) return "请说出你想说的话";
        $client = new Client("openapi.tuling123.com", 80);
        $client->setHeaders(["Content-type" => "application/json"]);
        $client->post("/openapi/api/v2", json_encode($content, JSON_UNESCAPED_UNICODE));
        $r = json_decode($client->body, true);
        return $r;
    }

    public static function getResultStatus($r) {
        switch ($r["intent"]["code"]) {
            case 5000:
                return "err:无解析结果";
            case 6000:
                return "err:暂不支持该功能";
            case 4000:
                return "err:暂不支持该功能";
            case 4001:
                return "err:加密方式错误";
            case 4002:
                return "err:无功能权限";
            case 4003:
                return "err:该apikey没有可用请求次数";
            case 4005:
                return "err:无功能权限";
            case 4007:
                return "err:apikey不合法";
            case 4100:
                return "err:userid获取失败";
            case 4200:
                return "err:上传格式错误";
            case 4300:
                return "err:批量操作超过限制";
            case 4400:
                return "err:没有上传合法userid";
            case 4500:
                return "err:userid申请个数超过限制";
            case 4600:
                return "err:输入内容为空";
            case 4602:
                return "err:输入文本内容超长(上限150)";
            case 7002:
                return "err:上传信息失败";
            case 8008:
                return "err:服务器错误";
            default:
                return true;
        }
    }

    public static function getTencentMsg($user_id, $message) {
        $arr = [
            'app_id' => ZMBuf::globals("tencent_api")["app_id"],
            'time_stamp' => time(),
            'nonce_str' => ZMBuf::globals("tencent_api")["nonce_head"] . mt_rand(0, 100000),
            'sign' => '',
            'session' => $user_id,
            'question' => $message
        ];
        $arr['sign'] = self::getTencentSign($arr, ZMBuf::globals("tencent_api")["app_key"]);
        $data = ZMRequest::post('https://api.ai.qq.com/fcgi-bin/nlp/nlp_textchat', ['Accept-Encoding' => 'gzip'], $arr);
        $json = json_decode($data, true);
        if ($json === null) return null;
        return $json["data"]["answer"];
    }

    private static function getTencentSign($params /* 关联数组 */, $appkey /* 字符串*/) {
        // 1. 字典升序排序
        ksort($params);
        // 2. 拼按URL键值对
        $str = '';
        foreach ($params as $key => $value) {
            if ($value !== '') {
                $str .= $key . '=' . urlencode($value) . '&';
            }
        }
        // 3. 拼接app_key
        $str .= 'app_key=' . $appkey;
        // 4. MD5运算+转换大写，得到请求签名
        $sign = strtoupper(md5($str));
        return $sign;
    }
}
