<?php

namespace crazybot\mods;

use crazybot\api\CQ;
use crazybot\api\CQAPI;
use crazybot\CrazyBot;
use Exception;
use framework\ZMBuf;
use RCNB;
use crazybot\utils\DataProvider as DP;

class Additional extends ModBase
{
    public function __construct(CrazyBot $main, $data) { parent::__construct($main, $data); }

    public function onStart() { }

    /**
     * 归属地|归属地查询
     * @param $arg
     * @return bool
     */
    public function matchPhoneAddr($arg) {
        $text = $this->getArgs($arg, 0, "请输入你要查询归属地的手机号");
        if ($text === null) return true;
        $num = substr($text, 0, 7);
        $ls = file_get_contents(DP::getDataFolder() . "Additional/phone.txt");
        $ls = explode("\n", $ls);
        foreach ($ls as $k => $v) {
            $p = explode(",", $v);
            if ($num == $p[0]) return $this->reply($p[1]);
        }
        return $this->reply("no matches.");
    }

    /**
     * 赞
     * @return bool
     */
    public function sendLike() {
        $response = CQAPI::send_like($this->getConnection(), $this->getUserId(), true);
        return $response["retcode"] == 0 ? $this->reply("已赞！") : $this->reply("赞失败了！请联系炸毛管理员！");
    }

    /**
     * 二维码|生成二维码
     * @param $arg
     * @return bool
     */
    public function qrcodeGen($arg) {
        $text = $this->getArgs($arg, 0, "请输入要生成二维码的内容");
        if ($text === null) return true;
        if (strlen($text) >= 350) return $this->reply("内容过长");
        $it = urlencode(CQ::decode(CQ::removeCQ($text)));
        $link = str_replace("$(data)", $it, ZMBuf::globals("qrcode_address"));
        $link = str_replace("$(timestamp)", intval(microtime(true) * 3), $link);
        return $this->reply(CQ::image($link));
    }

    /**
     * rcnb-encode
     * @param $arg
     * @return bool
     */
    public function rcnbEncode($arg) {
        $text = $this->getArgs($arg, 0, "请输入你要加密的文本");
        if ($text === null) return true;
        $msg = CQ::decode($text);
        $msg = CQ::removeCQ($msg);
        try {
            $rcnb = new RCNB();
            $result = $rcnb->encode($msg);
        } catch (Exception $e) {
            $result = "加密失败！";
        }
        return $this->reply($result);
    }

    /**
     * rcnb-decode
     * @param $arg
     * @return bool
     */
    public function rcnbDecode($arg) {
        $text = $this->getArgs($arg, 0, "请输入你要解密的文本");
        if ($text === null) return true;
        $msg = CQ::decode($text);
        $msg = CQ::removeCQ($msg);
        try {
            $rcnb = new RCNB();
            $result = $rcnb->decode($msg);
        } catch (Exception $e) {
            $result = "加密失败！";
        }
        return $this->reply($result);
    }

    /**
     * echo
     * @param $arg
     * @return bool
     */
    public function echo($arg) {
        if (count($arg) < 2) {
            $msg = "「echo用法帮助」";
            $msg .= "\necho是让炸毛发出文本的功能";
            $msg .= "\n例如：echo hello world";
            $msg .= "\n炸毛就会返回\"hello world\"";
            $msg .= "\n所以，用法很简单：echo xxx";
            $msg .= "\necho也支持高级解析功能";
            $msg .= "\n详情请查看实验性功能列表";
            return $this->reply($msg);
        }
        array_shift($arg);
        $msg = implode(" ", $arg);
        if ($msg != "") $this->reply($msg);
        return true;
    }

    /**
     * airpods刻字|ipad刻字
     * @param $arg
     * @return bool
     */
    public function makeAppleText($arg) {
        if (array_shift($arg) == "airpods刻字") {
            $content = implode(" ", $arg);
            $api = "https://www.apple.com/cn/shop/preview/engrave/PRXJ2CH/A?s=2&th=";
            return $this->reply(CQ::image($api . urlencode($content)));
        } else {
            $content = implode(" ", $arg);
            $api = "https://www.apple.com/cn/shop/preview/engrave/PTXP2CH/A?s=2&th=";
            return $this->reply(CQ::image($api . urlencode($content)));
        }
    }


}
