<?php

/**
 * Class TuringDetect
 * 图灵机器人模块，无指令，非基于基类
 */
class TuringDetect extends ModBase
{
    const mod_level = 2;

    public function __construct(CrazyBot $main, $data) {
        parent::__construct($main, $data);
        $this->function_call = true;
        if ($this->main->isFunctionCalled()) return;
        $msg = $data["message"];
        if ($data["message_type"] == "private" || $data["message_type"] == "wechat") {
            if (substr($msg, 0, 1) == "*") $msg = substr($msg, 1);
            elseif (($a = mb_substr($msg, 0, 3)) == "炸毛，" || $a == "卷毛，") $msg = mb_substr($msg, 3);
            elseif (($a = mb_substr($msg, 0, 2)) == "炸毛" || $a == "卷毛") $msg = mb_substr($msg, 2);
            elseif (($a = mb_substr($msg, -3)) == "，炸毛" || $a == "，卷毛") $msg = mb_substr($msg, 0, -3);
            else return;
            $this->function_call = "block";
            Console::info("正在执行图灵消息：" . $msg);
            $data["message"] = $msg;
            $s = new CrazyBot($data, $this->getConnection(), $this->main->circle + 1);
            if ($s->execute() === true) return;
            if ($data["message"] == "") return;
            $msg = self::getAPIMsg($msg, $this->getUserId());
            $this->reply($msg);
        } elseif ($data["message_type"] == "group") {
            if (strstr($data["message"], "[CQ:at,qq=" . $this->getRobotId() . "]") !== false) {
                $data["message"] = trim(str_replace("[CQ:at,qq=" . $this->getRobotId() . "]", "", $data["message"]));
                $s = new CrazyBot($data, $this->getConnection(), $this->main->circle + 1);
                if ($s->execute() === true) return;
                if ($data["message"] == "") return;
                $msg = $this->getAPIMsg($data["message"], $data["user_id"]);
                $main->reply(CQ::at($this->getUserId()) . $msg);
                return;
            } elseif (substr($msg, 0, 1) == "*") $msg = substr($msg, 1);
            elseif (($a = mb_substr($msg, 0, 3)) == "炸毛，" || $a == "卷毛，") $msg = mb_substr($msg, 3);
            elseif (($a = mb_substr($msg, 0, 2)) == "炸毛" || $a == "卷毛") $msg = mb_substr($msg, 2);
            elseif (($a = mb_substr($msg, -3)) == "，炸毛" || $a == "，卷毛") $msg = mb_substr($msg, 0, -3);
            elseif (mb_substr($msg, 0, 3) == "为什么" && mt_rand(0, 100) > 30) $msg = trim($msg);
            else return;
            $data["message"] = $msg;
            $s = new CrazyBot($data, $this->getConnection(), $this->main->circle + 1);
            if ($s->execute() === true) {
                $this->main->setFunctionCalled();
                return;
            }
            if ($data["message"] == "") return;
            $msg = self::getAPIMsg($msg, $this->getUserId());
            $this->reply($msg);
        }
    }

    public static function initValues() {
        $func = ZMUtil::getUser(date("Y"))->getFunction("turing_robot");
        ZMBuf::set("turing_robot", ($func === false ? 0 : $func));
        ZMBuf::set("turing_api_keys", DataProvider::getJsonData("TuringDetect_api.json"));
    }

    public static function saveValues() {
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
        if ($i >= count(ZMBuf::get("turing_api_keys"))) {
            $msg = self::getTencentMsg($userId, $msg);
            return $msg;
        }
        //请求第i个API
        $r = self::requestTuring($msg, $userId, ZMBuf::get("turing_api_keys")[$i]);
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
        $client = new \Swoole\Coroutine\Http\Client("openapi.tuling123.com", 80);
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
