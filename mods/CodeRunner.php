<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2019-02-14
 * Time: 09:59
 */

class CodeRunner extends ModBase
{
    const API_URL = "https://glot.io/run/{lang}?version=latest";
    const SUPPORTED_LANG = [
        "assembly" => ["asm"],
        "bash" => ["sh"],
        "php" => ["php"],
        "c" => ["c"],
        "clojure" => ["clj"],
        "coffeescript" => ["coffee"],
        "cpp" => ["cpp"],
        "csharp" => ["cs"],
        "erlang" => ["erl"],
        "fsharp" => ["fs"],
        "d" => ["d"],
        "go" => ["go"],
        "groovy" => ["groovy"],
        "haskell" => ["hs"],
        "java" => ["java", "Main"],
        "javascript" => ["js"],
        "julia" => ["jl"],
        "kotlin" => ["kt"],
        "lua" => ["lua"],
        "perl" => ["pl"],
        "python" => ["py"],
        "ruby" => ["rb"],
        "rust" => ["rs"],
        "scala" => ["scala"],
        "swift" => ["swift"],
        "typescript" => ["ts"],
        "nim" => ["nim"]
    ];

    public function __construct(CrazyBot $main, $data) {
        parent::__construct($main, $data);
        $this->function_call = true;
        if ($this->main->isFunctionCalled()) return false;

        if (in_array(mb_substr($data["message"], 0, 4), ["执行代码", "运行代码"])) {
            $other = trim(mb_substr($data["message"], 4));
            $exp = explode("\n", $other);
            $lang = strtolower(trim($exp[0]));
            if (mb_strpos($lang, "带输入") !== false) $stdin = true;
            if ($lang == "") {
                $this->reply("请输入你要执行的编程语言名称：\nps:查看支持的语言请回复\"执行代码\"");
                $lang = $this->waitUserMessage($data);
                if ($lang === null) return $this->reply("输入超时，请重新输入：执行代码");
                $lang = strtolower(trim($lang));
            }
            if (!isset(self::SUPPORTED_LANG[$lang])) {
                $this->reply("不支持的语言[$lang]，请重新输入代码名称：");
                $lang = $this->waitUserMessage($data);
                if ($lang === null) return $this->reply("输入超时，请重新输入：执行代码");
                $lang = strtolower(trim($lang));
            }
            if (!isset(self::SUPPORTED_LANG[$lang])) return $this->reply("输入语言不存在，退出执行代码.");
            if (!isset($exp[1])) {
                $this->reply("请输入你要执行的代码：");
                $code = $this->waitUserMessage($data);
                if ($code === null) return $this->reply("输入超时，请重新输入：执行代码");
            } else {
                array_shift($exp);
                $code = implode("\n", $exp);
            }
            if (isset($stdin)) {
                $this->reply("请输入你要输入到键盘的文本：");
                $text = $this->waitUserMessage($data);
                if ($code === null) return $this->reply("输入超时，请重新输入：执行代码");
            } else $text = "";
            //执行片段
            $this->reply("正在执行，请稍后");
            $r = self::runCode($lang, $code, $text);
            if ($r === false) return $this->reply("啊哦，服务器出现了点问题，请稍后再试！");
            $result = json_decode($r, true);
            if ($result === null) return $this->reply("啊哦，服务器出现了点问题哦，请稍后再试！");
            DataProvider::query("INSERT INTO user_code_record VALUES(?,?,?,?)", [$this->getUserId(), time(), $lang, $code]);
            $msg = $result['error'] == "" ? "标准输出:\n" . $result['stdout'] : "错误输出:\n" .
                ($result['stdout'] == "" ? "" : $result['stdout'] . "\n\n") .
                ($result['stderr'] == "" ? "" : ($result['stderr'] . "\n\n")) .
                $result['error'];
            return $this->reply(strlen($msg) <= 3000 ? $msg : "你的代码标准输出的内容太长了！");
        }
        return false;
    }

    public static function runCode($lang, $code, $stdin = "") {
        $req = [
            "files" => [
                [
                    "name" => (self::SUPPORTED_LANG[$lang][1] ?? "main") . "." . self::SUPPORTED_LANG[$lang][0],
                    "content" => $code
                ]
            ],
            "stdin" => $stdin,
            "command" => ""
        ];
        return ZMRequest::post(str_replace("{lang}", $lang, self::API_URL), ["Content-type" => "application/json"], json_encode($req, 256));
    }

    public function saveCode($lang, $code, $stdin, $keyword) {
        if (strlen($code) > 5000) {
            $this->reply("代码过长");
            return;
        }
        $keyword = str_replace([" ", "　", "\t", "\n", "\r"], '', $keyword);
        $user = $this->getUser();
        $stdin = $stdin ? 1 : 0;
        $v = "[run_code,$lang,$stdin]" . $code;
        $user->setLexicon($keyword, $v);
        $this->reply("已添加代码片段 " . $keyword);
    }
}
