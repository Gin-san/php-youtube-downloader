<?php

namespace Gin\Downloader\Extractor;

use Gin\Tools\Curl\Curl;
use UnexpectedValueException;
use RuntimeException;

class Youtube
{
    const VIDEO_INFO_URL = "https://www.youtube.com/get_video_info?&video_id=%s%s&ps=default&eurl=&gl=US&hl=en";
    const VIDEO_API_URL  = "https://youtube.googleapis.com/v/%s";
    const VIDEO_WEB_URL  = "https://www.youtube.com/watch?v=%s&gl=US&hl=en&has_verified=1";
    const USER_AGENT     = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.14 Safari/537.36";

    protected $video_id;
    protected $player_content;

    public function __construct($video_id)
    {
        $this->video_id = $video_id;
        $this->headers  = [
            'Accept-charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'Accept-language: en-us,en;q=0.5',
            'Accept-encoding: gzip, deflate',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ];
    }

    public function extract()
    {
        $info = [];
        foreach (['&el=embedded', '&el=detailpage', '&el=vevo', ''] as $extra) {
            try {
                $info = $this->getInfo($extra);
            } catch (\Exception $e) {
                continue;
            }
            if (isset($info['token'])) {
                echo $extra . PHP_EOL;
                break;
            }
        }
        if (!isset($info['token'])) {
            if (isset($info['reason'])) {
                throw new RuntimeException("Youtube error: " . $info['reason']);
            }
            throw new RuntimeException("Unable to get info");
        }
        $title                              = $info['title'];
        $stream_data                        = [];
        if (preg_match("#[&,]s=#", $info['url_encoded_fmt_stream_map'])) {
            echo "encrypted signatures detected" . PHP_EOL;
            $info['url_encoded_fmt_stream_map'] = $this->getEncryptedStream();
        }
        $info['url_encoded_fmt_stream_map'] = explode(',', $info['url_encoded_fmt_stream_map']/* . ',' . $info['adaptive_fmts']*/);
        $urls                               = [];
        // print_r($info);exit;
        foreach ($info['url_encoded_fmt_stream_map'] as $stream_map) {
            parse_str($stream_map, $stream_data);
            $sig                = isset($stream_data['sig']) ? $stream_data['sig']:null;
            // $stream_data['url'] = urldecode($stream_data['url']);
            if (isset($stream_data['s'])) {
                // echo strlen($stream_data['s']) . PHP_EOL;
                // print_r($stream_data);
                // continue;
                // $stream_data['s'] = "8181DCD93F1C31F62AB31E460035D7B88F0B31FED4.DF5A871DEEEA94E29A0218E640496E5E4B9F9E58E599";
                $sig = $this->findSignature($stream_data['s']);
                // exit;
            }
            $url = $stream_data['url'];
            if (!is_null($sig)) {
                $url .= '&signature=' . $sig;
            }
            $headers = get_headers($url, true);
            if (strpos($headers[0], "403 Forbidden") !== false) {
                echo $url . PHP_EOL;
                // exit;
                echo "403 Forbidden" . PHP_EOL . PHP_EOL;
                continue;
            }

            $t           = explode(';', $stream_data['type']);
            $mime        = current($t);
            $mime        = explode('/', $mime);
            $ext         = end($mime);
            $itag        = $stream_data['itag'] . " - " . $ext;
            if (isset($stream_data['quality'])) {
                $itag .= " - " . $stream_data['quality'];
            }
            $urls[$itag] = [
                'title' => $title,
                'url'   => $url,
                'ext'   => $ext

            ];
        }

        return $urls;
    }

    protected function getInfo($extra = "")
    {
        $url      = sprintf(self::VIDEO_INFO_URL, $this->video_id, $extra);
        $response = [];
        parse_str((new Curl([], self::USER_AGENT/*, constant('YT_COOKIE_PATH')*/))->setHttpHeader($this->headers)->execute($url), $response);

        return $response;
    }

    protected function getEncryptedStream()
    {
        $matches     = [];
        $url         = sprintf(self::VIDEO_WEB_URL, $this->video_id);
        $web_content = gzdecode((new Curl([], self::USER_AGENT/*, constant('YT_COOKIE_PATH')*/))->setHttpHeader($this->headers)->execute($url));
        preg_match("#;ytplayer.config = ({.*?});#", $web_content, $matches);
        $data = json_decode($matches[1], true);

        return $data['args']['url_encoded_fmt_stream_map'];
    }

    protected function findSignature($s)
    {
        $url         = sprintf(self::VIDEO_WEB_URL, $this->video_id);
        $web_content = gzdecode((new Curl([], self::USER_AGENT/*, constant('YT_COOKIE_PATH')*/))->setHttpHeader($this->headers)->execute($url));
        $matches     = [];
        $player_url  = null;

        preg_match('#"assets":.+?"js":\s*("[^"]+")#', $web_content, $matches);
        if (isset($matches[0])) {
            $asset_found = preg_replace("#\"assets\":[^{]#", "", $matches[0]);
            if (strpos($asset_found, "}") === false) {
                $asset_found .= "}";
            }
            $asset_json = json_decode($asset_found, true);
            $player_url = $asset_json['js'];
        }
        if (is_null($player_url)) {
            throw new UnexpectedValueException("Player video not found");
        }

        if (strpos($player_url, "/") === 0) {
            $player_url = 'https:' . $player_url;
        }

        $this->player_content = gzdecode((new Curl([], self::USER_AGENT/*, constant('YT_COOKIE_PATH')*/))->setHttpHeader($this->headers)->execute($player_url));
        $matches              = [];

        preg_match("#signature=(?P<funcname>[a-zA-Z]+)#", $this->player_content, $matches);
        $funcname  = $matches['funcname'];
        $signature = $this->interpret_function($funcname, [$s]);

        echo "before: " . $s . " - " . strlen($s) . PHP_EOL;
        echo "after: " . $signature . " - " . strlen($signature) . PHP_EOL;

        return $signature;
    }

    public function interpret_function($funcname, $args)
    {
        $matches    = [];
        $regex      = sprintf('#function %s\((?P<args>[a-z,]+)\){(?P<code>[^}]+)}#', $funcname);
        preg_match($regex, $this->player_content, $matches);
        $codes      = explode(';', $matches['code']);
        $local_vars = array_combine(explode(",", $matches['args']), $args);
        $res        = null;

        foreach ($codes as $code) {
            $res = $this->interpret_statement($code, $local_vars);
        }

        return $res;
    }

    protected function interpret_statement($stmt, &$local_vars)
    {
        if (strpos($stmt, 'return') !== false) {
            return $this->interpret_expression(str_replace('return ', '', $stmt), $local_vars);
        } elseif (strpos($stmt, 'var ') === 0) {
            $stmt = str_replace('var ', '', $stmt);
        }
        $matches = [];
        preg_match('#^(?P<out>[a-z]+)(?:\[(?P<index>[^\]]+)\])?=(?P<expr>.*)$#', $stmt, $matches);
        $val = $this->interpret_expression($matches['expr'], $local_vars);
        if (strlen($matches['index'])) {
            $index = $this->interpret_expression($matches['index'], $local_vars);
            $local_vars[$matches['out']][$index] = $val;

            return $val;
        }
        $local_vars[$matches['out']] = $val;

        return $val;
    }

    protected function interpret_expression($exp, &$local_vars)
    {
        if (ctype_digit($exp)) {
            return $exp;
        }
        if (ctype_alpha($exp)) {
            return $local_vars[$exp];
        }

        $matches = [];
        preg_match("#^(?P<in>[a-z]+)\.(?P<member>.*)$#", $exp, $matches);
        if (isset($matches['member']) && isset($matches['in'])) {
            $member  = $matches['member'];
            $in      = $matches['in'];

            if ($member == 'split("")') {
                return str_split($local_vars[$in]);
            } elseif ($member == 'reverse()') {
                return array_reverse($local_vars[$in]);
            } elseif ($member == 'join("")') {
                return implode("", $local_vars[$in]);
            } elseif ($member == 'length') {
                if (is_string($local_vars[$in])) {
                    return strlen($local_vars[$in]);
                }
                return count($local_vars[$in]);
            } elseif (strpos($member, 'slice') !== false) {
                $matches = [];
                preg_match("#slice\((?P<idx>.*)\)#", $member, $matches);
                $idx = $this->interpret_expression($matches['idx'], $local_vars);
                return array_slice($local_vars[$in], $idx);
            }
        }

        $matches = [];
        preg_match("#^(?P<in>[a-z]+)\[(?P<idx>.+)\]$#", $exp, $matches);
        if (isset($matches['idx'])) {
            $in = $matches['in'];
            $idx = $this->interpret_expression($matches['idx'], $local_vars);
            return $local_vars[$in][$idx];
        }

        $matches = [];
        preg_match("#^(?P<a>.+?)(?P<op>[%])(?P<b>.+?)$#", $exp, $matches);
        if (isset($matches['a']) && isset($matches['b'])) {
            $a = $this->interpret_expression($matches['a'], $local_vars);
            $b = $this->interpret_expression($matches['b'], $local_vars);
            return $a % $b;
        }

        $matches  = [];
        preg_match("#^(?P<func>[a-zA-Z$]+)\((?P<args>[a-z0-9,]+)\)$#", $exp, $matches);
        $funcname = $matches['func'];
        $args     = explode(",", $matches['args']);
        $res      = $this->interpret_function($funcname, array_map(function ($v) use ($local_vars) {
            if (!ctype_digit($v)) {
                $v = $local_vars[$v];
            }

            return $v;
        }, $args));

        return $res;
    }

}
