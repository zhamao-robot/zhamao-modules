<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2019/1/21
 * Time: 1:35 PM
 */


class Music extends ModBase
{
    public function __construct(CrazyBot $main, $data) {
        parent::__construct($main, $data);
        $this->function_call = true;
        if (mb_substr($data["message"], 0, 6) == "我想听炸毛唱") {
            $word = trim(mb_substr($data["message"], 6));
            if ($word == "") return false;
            $id = self::get163Music($word);
            if ($id === false) return $this->reply("对不起，炸毛搜不到名为 $word 的歌曲哦！");
            $this->main->setFunctionCalled(true);
            $msg = [
                "啊，炸毛清清嗓子",
                "稍等，马上就好",
                "别急，我在准备话筒",
                "调音中"
            ];
            $this->reply($msg[mt_rand(0, 3)]);
            $cli = new Swoole\Coroutine\Http\Client("music.163.com", 80);
            $cli->get("/song/media/outer/url?id=" . $id . ".mp3");
            $client = $cli->headers["location"];
            if (mb_substr($client, -3) == "404") return $this->reply("对不起，这首歌还没有版权暂无法获取哦！");
            CQAPI::send_msg(
                $this->getConnection(),
                $this->getMessageType(),
                ($this->getMessageType() == "private" ? $this->getUserId() : $data[$this->getMessageType() . "_id"]),
                CQ::record($client)
            );
        } elseif (mb_substr($data["message"], 0, 4) == "下载音乐") {
            $word = trim(mb_substr($data["message"], 4));
            if ($word == "") return false;
            $id = self::get163Music($word);
            if ($id === false) return $this->reply("对不起，炸毛搜不到名为 $word 的歌曲哦！");
            $this->main->setFunctionCalled(true);
            $cli = new Swoole\Coroutine\Http\Client("music.163.com", 80);
            $cli->get("/song/media/outer/url?id=" . $id . ".mp3");
            $client = $cli->headers["location"];
            if (mb_substr($client, -3) == "404") return $this->reply("对不起，这首歌还没有版权暂无法获取哦！");
            $this->reply("下载链接:\n".$client);
        } elseif (($word = $this->hasKeyWord($data["message"])) !== false) {
            if ($this->getMessageType() == "group" && in_array($data["group_id"], ZMBuf::globals("special_music_group"))) {
                return $this->processSpecialMusic($word, $data["message"]);
            }
            $id = self::getQQMusic($word);
            if ($id === false) return $this->reply("对不起，炸毛搜不到名为 " . $word . "的歌曲哦！");
            return $this->reply(CQ::music("qq", $id));
        }
        return false;
    }

    public function hasKeyWord($word) {
        CQ::removeCQ($word);
        $len3 = mb_substr($word, 0, 3);
        $len2 = mb_substr($word, 0, 2);
        if ($len3 == "来一首" || $len3 == "我想听" || $len3 == "整一首" || $len3 == "点一首") $word = mb_substr($word, 3);
        elseif ($len2 == "点歌" || $len2 == "播放") $word = mb_substr($word, 2);
        else return false;
        $ends = ["吧", "呗"];
        $word = trim($word);
        if (in_array(mb_substr($word, -1, 1), $ends)) $word = trim(mb_substr($word, 0, -1));
        return $word == "" ? false : $word;
    }

    public static function getQQMusic($word, $cq_return = false) {
        $cli = new Swoole\Coroutine\Http\Client("c.y.qq.com", 443, true);
        $cli->setHeaders([
            "User-Agent" => 'Chrome/49.0.2587.3'
        ]);
        //$cli->set(['timeout' => );
        $cli->get("/soso/fcgi-bin/client_search_cp?g_tk=5381&p=1&n=20&w=" . urlencode($word) . "&format=json&loginUin=0&hostUin=0&inCharset=utf8&outCharset=utf-8&notice=0&platform=yqq&needNewCode=0&remoteplace=txt.yqq.song&t=0&aggr=1&cr=1&catZhida=1&flag_qc=0");
        if ($cli->errCode != 0 || $cli->statusCode != 200) return false;
        $a = $cli->body;
        $cli->close();
        $a = json_decode($a, true);
        $a = $a["data"]["song"]["list"][0]["songid"] ?? false;
        if ($a !== false && $cq_return !== false) return CQ::music("qq", $a);
        return $a;
    }

    public static function get163Music($word, $cq_return = false) {
        $cli = new Swoole\Coroutine\Http\Client("music.163.com", 80);
        $cli->setHeaders([
            "User-Agent" => 'Chrome/49.0.2587.3'
        ]);
        //$cli->set(['timeout' => );
        $cli->post("/api/search/pc", [
            "s" => $word,
            "type" => 1,
            "limit" => 1,
            "offset" => 0
        ]);
        if ($cli->errCode != 0 || $cli->statusCode != 200) return false;
        $a = $cli->body;
        $cli->close();
        $a = json_decode($a, true);
        $a = $a["result"]["songs"][0]["id"] ?? false;
        if ($a !== false && $cq_return !== false) return CQ::music("163", $a);
        return $a;
    }

    /**
     * 和奶茶互动
     * @param $word
     * @param $origin
     * @return bool|int
     */
    public function processSpecialMusic($word, $origin) {
        $s_stamp = intval(time() / 30);
        if ($s_stamp % 2 != 0) {
            $id = self::getQQMusic($word);
            if ($id === false) return $this->reply("对不起，炸毛搜不到名为 " . $word . "的歌曲哦！");
            return $this->reply(CQ::music("qq", $id));
        } else {//如果奶茶发了音乐，则炸毛不发且互动一下下
            return swoole_timer_after(2000, function () use ($origin) {
                if (mb_substr($origin, 0, 2) == "点歌")
                    $this->reply("奶茶已经回复你啦！");
            });
        }
    }
}
