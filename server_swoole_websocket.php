<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/5/005
 * Time: 17:34
 */

/**
 * websocket 转发服务器
 * Class Server
 */
class Server
{
    /**
     * @var \swoole_websocket_server
     */
    public $webSocketServ;

    protected $master_pid_file = '/home/wwwroot/swoole-doc/src/09/swoole_master_pid.txt';

    static public $client_lists;

    public function __construct($host, $port)
    {
        $this->webSocketServ = new \swoole_websocket_server($host, $port);

        $this->webSocketServ->set([
            'reactor_num' => 2, //通过此参数来调节poll线程的数量，以充分利用多核
            'daemonize' => false, //加入此参数后，执行php server.php将转入后台作为守护进程运行,ps -ef | grep {this->process_name}
            'worker_num' => 4,//worker_num配置为CPU核数的1-4倍即可
//            'dispatch_mode' => 2,//'https://wiki.swoole.com/wiki/page/277.html
//            'max_request' => 100,//此参数表示worker进程在处理完n次请求后结束运行，使用Base模式时max_request是无效的
//            'backlog' => 128,   //此参数将决定最多同时有多少个待accept的连接，swoole本身accept效率是很高的，基本上不会出现大量排队情况。
//            'log_level' => 5,//'https://wiki.swoole.com/wiki/page/538.html
//            'log_file' => '/var/www/charRoom/runtime/log_file.'.date("Ym").'.txt',// 'https://wiki.swoole.com/wiki/page/280.html 仅仅是做运行时错误记录，没有长久存储的必要。
//            'heartbeat_check_interval' => 30, //每隔多少秒检测一次，单位秒，Swoole会轮询所有TCP连接，将超过心跳时间的连接关闭掉
//            'heartbeat_idle_time' => 3600, //TCP连接的最大闲置时间，单位s , 如果某fd最后一次发包距离现在的时间超过heartbeat_idle_time会把这个连接关闭。
//            'task_worker_num' => 2,
            'pid_file' => $this->master_pid_file,//kill -SIGUSR1 $(cat server.pid)  重启所有worker进程
//            'task_max_request' => 1000,//设置task进程的最大任务数，一个task进程在处理完超过此数值的任务后将自动退出，防止PHP进程内存溢出
//            'user'  => 'apache',
//            'group' => 'apache',
//            'chroot' => '/tmp/root'
//            'open_eof_split' => true,
//            'package_eof' => "\r\n"
        ]);

    }

    public function start()
    {
        $this->webSocketServ->on('start', [$this, 'onStart']);
        $this->webSocketServ->on('shutdown', [$this, 'onShutdown']);
        $this->webSocketServ->on('workerStart', [$this, 'onWorkerStart']);
        $this->webSocketServ->on('workerStop', [$this, 'onWorkerStop']);
        $this->webSocketServ->on('workerError', [$this, 'onWorkerError']);
        $this->webSocketServ->on('connect', [$this, 'onConnect']);
        $this->webSocketServ->on('request', [$this, 'onRequest']);
        $this->webSocketServ->on('open', [$this, 'onOpen']);
        $this->webSocketServ->on('message', [$this, 'onMessage']);
        $this->webSocketServ->on('close', [$this, 'onClose']);

        $this->webSocketServ->start();
    }

    /**
     * 作为转发服务器使用
     *
     * @param swoole_http_request $request
     * @param swoole_http_response $response
     */
    public function onRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
        $data = date('Y-m-d H:i:s');
        debug_log("[{$data}] request is coming...");
        if ($request->post['token'] != 'abc') {
            debug_log('request error');
            return;
        }
//        var_dump($request->post['info']);
//        var_dump(json_decode($request->post['info'], true));
        foreach ($this->webSocketServ->connections as $fd) {
            try {
                @$this->webSocketServ->push($fd, $request->post['info']);
            } catch (Exception $e) {

            }
        }
    }

    public function onOpen(swoole_websocket_server $server, $request)
    {
        foreach ($server->connections as $fd) {
            if ($request->fd != $fd) {
                $message = json_encode(['message' => "fd-id:{$request->fd} Enter Chat"]);
                $server->push($fd, $message);
            }
        }
        debug_log("{$request->fd} opened ...");
    }

    public function onConnect()
    {
        debug_log("connecting ......");
    }

    public function onClose()
    {
        debug_log("closing .....");
    }

    public function onStart(\swoole_websocket_server $swooleServer)
    {
        debug_log("swoole_server starting .....");
    }

    public function onShutdown(\swoole_websocket_server $swooleServer)
    {
        debug_log("swoole_server shutdown .....");
    }

    public function onWorkerStart(\swoole_websocket_server $swooleServer, $workerId)
    {
        debug_log("worker #$workerId starting .....");
    }

    public function onWorkerStop(\swoole_websocket_server $swooleServer, $workerId)
    {
        debug_log("worker #$workerId stopping ....");
    }

    public function onWorkerError(\swoole_websocket_server $swooleServer, $workerId, $workerPid, $exitCode, $sigNo)
    {
        debug_log("worker error happening [workerId=$workerId, workerPid=$workerPid, exitCode=$exitCode, signalNo=$sigNo]...");
    }

    public function onMessage(swoole_websocket_server $server, $frame)
    {
        $server->push($frame->fd, json_encode(["action" => "Pong"]));
    }
}

$server = new Server("0.0.0.0", 8080);
$server->start();


function debug_log($str, $handle = STDERR)
{
    if ($handle === STDERR) {
        $tpl = "\033[31m[%d %s] %s\033[0m\n";
    } else {
        $tpl = "[%d %s] %s\n";
    }
    if (is_resource($handle)) {
        fprintf($handle, $tpl, posix_getpid(), date("Y-m-d H:i:s", time()), $str);
    } else {
        printf($tpl, posix_getpid(), date("Y-m-d H:i:s", time()), $str);
    }
}

function getConfig($filename = '')
{
    if (!file_exists($filename)) {
        return false;
    }

    return file_get_contents($filename);
}