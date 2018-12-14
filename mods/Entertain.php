<?php

/**
 * Class Entertain
 * 娱乐功能模块
 */
class Entertain extends ModBase
{
    public $stat = [
        "sorry" => [
            "api" => "https://sorry.xuty.tk/api/sorry/make",
            "need" => 9,
            "pro" => "https://sorry.xuty.tk"
        ],
        "王境泽" => [
            "api" => "https://sorry.xuty.tk/api/wangjingze/make",
            "need" => 4,
            "pro" => "https://sorry.xuty.tk"
        ],
        "窃格瓦拉" => [
            "api" => "https://sorry.xuty.tk/api/dagong/make",
            "need" => 6,
            "pro" => "https://sorry.xuty.tk"
        ],
        "诸葛孔明" => [
            "api" => "https://sorry.xuty.tk/api/kongming/make",
            "need" => 2,
            "pro" => "https://sorry.xuty.tk"
        ],
        "曾小贤" => [
            "api" => "https://sorry.xuty.tk/api/dati/make",
            "need" => 4,
            "pro" => "https://sorry.xuty.tk"
        ],
        "谁反对" => [
            "api" => "https://sorry.xuty.tk/mini-disagree/make",
            "need" => 4,
            "pro" => "https://sorry.xuty.tk"
        ],
        "耶稣也留不住" => [
            "api" => "https://sorry.xuty.tk/jesus/make",
            "need" => 3,
            "pro" => "https://sorry.xuty.tk"
        ],
        "压力大爷" => [
            "api" => "https://sorry.xuty.tk/daye/make",
            "need" => 3,
            "pro" => "https://sorry.xuty.tk"
        ],
        "工作细胞" => [
            "api" => "https://sorry.xuty.tk/hataraku/make",
            "need" => 3,
            "pro" => "https://sorry.xuty.tk"
        ]
    ];

    public function __construct(CrazyBot $main, $data) { parent::__construct($main, $data); }

    public function execute($it) {
        $it = explodeMsg(implode(" ", $it));
        switch (strtolower($it[0])) {
            case "表情包模板":
            case "表情包模版":
                $msg = "";
                for($i = 1; $i <= 3;$i++) $msg .= CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=face_template_".$i.".jpg");
                $this->reply($msg);
                $this->reply("上面是模板的编号。\n示例：表情包 13 找打是不\n用法：表情包 编号 你的内容");
                return true;
            case "动图模板":
            case "动图模版":
                $this->reply(CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=face_template_dynamic.jpg"));
                $msg = "上方右侧内为表情包对应名字，下面将举例：";
                $msg .= "\n" . CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=face_template_example.jpg");
                $msg .= "\n当然你也可以多行输入，只需换行即可。使用空格或换行均可分开句子。\n" . CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=face_template_example2.jpg");
                $this->reply($msg);
                return true;
            case "表情包":
            case "斗图":
                if (!isset($it[1])) {
                    $this->sendHelp();
                    return true;
                }
                array_shift($it);
                $package_name = array_shift($it);
                if (!file_exists(DataProvider::getResourceFolder() . "face/" . ($name = ($package_name . ".jpg"))) || !is_numeric($package_name)) {
                    $this->reply("对不起，炸毛没有找到名字为 " . $package_name . " 的表情包模板哦～");
                    $msg = "";
                    for($i = 1; $i <= 3;$i++) $msg .= CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=face_template_".$i.".jpg");
                    $this->reply($msg);
                    $this->reply("上面是模板的编号。\n示例：表情包 13 找打是不\n用法：表情包 编号 你的内容");
                    return true;
                }

                $color = null;
                foreach ($it as $ks => $vs) {
                    if (mb_substr($vs, 0, 8) == '-color=#') {
                        $color = mb_substr($vs, 7);
                        unset($it[$ks]);
                        break;
                    }
                }
                $msg = implode(" ", $it);
                if (empty($it) || $msg == "") {
                    $this->reply(CQ::image(HTTP_ADDRESS . "?pict_type=face_template&file=" . $name));
                    $this->reply("上图为 " . $package_name . " 的模板\n回复：表情包 " . $package_name . " 你的内容");
                    return true;
                }
                Console::debug("正在加载模板图");
                $img = new FaceGenerator();
                $r = $img->loadImg($name);
                if ($r !== true) {
                    $this->reply("创建图片失败，请联系我的主人！");
                    CQAPI::debug("Unable to generate image.\nError info: " . $r, "", $this->getRobotId());
                    return true;
                }
                if (mb_strlen($msg) >= 18) $msg = "太长了 给炸毛一个短一点的吧";
                $img->addText($msg, $color);
                Console::debug("正在获取图片流");
                $add = $img->saveImageBase64($name, $msg);
                $this->reply(CQ::image(HTTP_ADDRESS . "?pict_type=face_temp&file=" . $add));
                return true;
            case "sorry":
            case "王境泽":
            case "窃格瓦拉":
            case "诸葛孔明":
            case "曾小贤":
            case "谁反对":
            case "耶稣也留不住":
            case "压力大爷":
            case "工作细胞":
                if ($this->checkWechat()) return true;
                $stat = $this->stat;
                $msg_help = "【" . $it[0] . "动图帮助】";
                $msg_help .= "\n用法：\n" . $it[0];
                for ($i = 1; $i <= $stat[$it[0]]["need"]; $i++) $msg_help .= " 第" . $i . "句";
                $msg_help .= "\n你也可以换行输入：\n" . $it[0];
                for ($i = 1; $i <= $stat[$it[0]]["need"]; $i++) $msg_help .= "\n第" . $i . "句";
                if (count($it) < ($stat[$it[0]]["need"] + 1)) {
                    $this->reply($msg_help);
                    return true;
                }
                $at = array_shift($it);
                $content = [];
                if (count($it) >= $stat[$at]["need"]) {
                    for ($i = 0; $i < $stat[$at]["need"]; $i++) $content[strval($i)] = $it[$i];
                } else {
                    $this->reply("你的内容不够" . $stat[$at]["need"] . "句哦！\n" . $msg_help);
                    return true;
                }
                $opts = array(
                    'http' => array(
                        'method' => 'POST',
                        'header' => 'Content-Type: application/json; charset=utf-8',
                        'content' => json_encode((object)$content, JSON_UNESCAPED_UNICODE)
                    )
                );
                $context = stream_context_create($opts);
                $result = file_get_contents($stat[$at]["api"], false, $context);//阻塞IO
                if ($result == false) {
                    $this->reply("抱歉，请求失败，请过一会儿再试吧～");
                    return true;
                }
                if (mb_strpos($result, "点击下载") !== false) {
                    $dom = new simple_html_dom();
                    $dom->load($result);
                    $rs = $dom->find("a")[0];
                    $result = $rs->href;
                }
                $this->reply("正在生成，请稍等。");
                $result = $stat[$at]["pro"] . $result;
                $msg = CQ::image($result);
                $param = [
                    "message_type" => $this->getMessageType(),
                    "message" => $msg
                ];
                if ($this->getMessageType() == "group") $param["group_id"] = $this->data["group_id"];
                else $param["user_id"] = $this->getUserId();
                CQAPI::send_msg_async($this->getConnection(), $this->getMessageType(), ($this->getMessageType() == "private" ? $param["user_id"] : $param["group_id"]), $msg);
                return true;
            case "炸毛":
            case "卷毛":
                if (count($it) < 2) {
                    $this->reply("听着呢，有事吗？\n" . $it[0] . "使用帮助查看请回复：帮助");
                    return true;
                }
                array_shift($it);
                $msg = implode(" ", $it);
                $this->data["message"] = $msg;
                $t = new CrazyBot($this->data, $this->getConnection(), $this->main->circle + 1);
                $t->execute();
                //CQAPI::debug("执行[".$msg."]".($r ? "成功了" : "失败了"));
                return true;
            case "随机数":
                if (!isset($it[1]) || !isset($it[2])) {
                    $this->reply("用法：随机数 开始整数 结束整数");
                    return true;
                }
                $c1 = intval($it[1]);
                $c2 = intval($it[2]);
                if ($c1 > $c2) {
                    $this->reply("随机数范围错误！应该从小的一方到大的一方！例如：\n随机数 1 99");
                    return true;
                }
                $this->reply("生成的随机数是 " . mt_rand($c1, $c2));
                return true;
            case "选哪个":
                if (count($it) <= 1) {
                    $this->reply("「选哪个」\n用法：选哪个 xxx yyy zzz ...\n你只要在选哪个后面如用法所示填上两个或以上的选择词语即可，炸毛会随机返回一个。\n例如：选哪个 可口可乐 百事可乐");
                    return true;
                }
                $i = mt_rand(1, count($it) - 1);
                $this->reply($it[$i]);
                return true;
            case "掷硬币":
                $r = mt_rand(0, 1);
                $this->reply(($r == 1 ? "你看到的是：正面" : "你看到的是：反面"));
                return true;
            case "掷骰子":
                if ($this->main->data["message_type"] == "wechat") {
                    $this->reply("微信暂不支持此功能，敬请期待！");
                    return true;
                }
                $this->reply("[CQ:dice,type=1]");
                return true;
            case "找图片":
                if (!$this->main->isAdmin($this->getUserId())) {
                    $this->reply("暂不支持此功能，敬请期待！");
                    return true;
                }
                $this->reply(CQ::image($it[1]));
                return true;
        }
        return false;
    }

    private function sendHelp() {
        $msg = CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=face_help.jpg");
        $this->reply($msg);
    }
}
