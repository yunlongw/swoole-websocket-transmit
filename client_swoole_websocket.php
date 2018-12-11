<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/4/004
 * Time: 14:34
 */

/**
 * websocket_client 客户端
 * 获得数据后通过 curl 的方式发送给 websocket_server
 * Class Client
 */
class Client
{
    private $client;
    private $tick_time;

    const target_host = "www.example.com";

    public function __construct()
    {

    }


    public function start()
    {
        /**
         * https  $this->client = new \swoole_http_client(self::target_host, 443, true);
         * http  $this->client = new \swoole_http_client(self::target_host, 8080);
         */
        $this->client = new \swoole_http_client(self::target_host, 443, true);
        $this->client->setHeaders([
            'Host' => "es.avia-gaming.com",
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
            'UserAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36'
        ]);

        // Set the connect timeout
        $this->client->set(['timeout' => 3.0]);
        // Set the keep alive option
        $this->client->set(['keep_alive' => true]);
        // Set websocket mask
        $this->client->set(['websocket_mask' => true]);
        // Connect
        $this->client->on('Connect', array($this, 'onConnect'));
        // Message
        $this->client->on('message', array($this, "onMessage"));

        //api address
        $this->client->upgrade('/', function ($cli) {

            if ($cli->statusCode != 101) {
                echo "statusCode : {$cli->statusCode} , start fail";
                return;
            }
            echo "swoole_http_client is start...";
            echo "statusCode :  {$cli->statusCode} \n";

            $this->tick_time = swoole_timer_tick(1000, function () use ($cli) {
                @$cli->push(json_encode(['action' => 'Ping']));
            });
        });

    }


    public function onConnect($cli)
    {
        echo "connect ... \n";

    }

    function onUpgrade($cli)
    {
        echo 'push...';
    }

    public function onMessage(swoole_http_client $cli, $frame)
    {
        $time = date('Y-m-d H:i:s');
        echo "[{$time}] other server message \n";

        if (empty($frame)) {
            echo "frame is empty... \n";
            return;
        }

        try {
            $data = json_decode($frame->data, true);
            if (isset($data['action']) && $data['action'] != "Pong") {
                $this->push(['info' => json_encode($data)]);
            }

        } catch (Exception $e) {
            echo $e->getMessage();
            echo "\n";
        }
    }

    public function __destruct()
    {
        try {
            if ($this->tick_time) {
                swoole_timer_clear($this->tick_time);
            }
        } catch (Exception $e) {
//            Log
        }
        echo 'exit';
    }

    public function push($param)
    {
        $param['token'] = 'abc';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8080");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        curl_exec($ch);
        curl_close($ch);
    }

}

$client = new Client();
$client->start();