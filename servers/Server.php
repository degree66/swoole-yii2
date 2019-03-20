<?php

namespace degree757\yii2s\servers;

use Yii;

/**
 * Class Server
 * @package degree757\yii2s\servers
 */
abstract class Server
{
    /**
     * Sw server instance
     * @var Server
     */
    public $swServer;

    /**
     * Sw server events
     * @var array
     */
    public $swEvents = ['WorkerStart',
                        'task',
                        'finish'];

    /**
     * Sw server process name
     * @var string
     */
    public $processName = 'sw-server';

    /**
     * Sw server ip
     * @var string
     */
    public $ip = '0.0.0.0';

    /**
     * Sw server port
     * @var int
     */
    public $port = 18757;

    /**
     * Sw server config set, see: https://wiki.swoole.com/wiki/page/274.html
     * @var
     */
    public $set = [
        'worker_num'      => 1,
        'task_worker_num' => 1,
        'pid_file'        => '@app/server.pid',
        'log_file'        => '@runtime/sw.log',
    ];

    /**
     * Start sw server
     */
    public function start()
    {
        @swoole_set_process_name($this->processName);
        Yii::info("Sw Server {$this->ip}:{$this->port} Start");

        $this->swServer = $this->getSwServer();
        $this->swServer->set($this->set);
        $this->bindEvents();
        $this->swServer->start();
    }

    abstract public function getSwServer();

    /**
     * Bind sw callback events
     */
    public function bindEvents()
    {
        foreach ($this->swEvents as $event) {
            if (method_exists($this, 'on' . $event)) {
                $this->swServer->on($event, [
                    $this,
                    'on' . $event,
                ]);
            }
        }
    }

    /**
     * The sw work process starts the callback event
     * @param $server
     * @param $workerId
     */
    public function onWorkerStart($server, $workerId)
    {
        @swoole_set_process_name($this->processName);

        // Save sw server in yii2 components，Convenient use of the sw server method
        if (Yii::$app->has('sw')) {
            Yii::$app->sw->setSwServer($server);
        }

        if ($server->taskworker) {
            Yii::info("Task Worker Start #{$workerId}");
        } else {
            Yii::info("Worker Start #{$workerId}");
        }
    }

    /**
     * The sw work task process starts the callback event, use sw components to accomplish async
     *
     * ```php
     * Yii::$app->sw->task('increment', $a);
     * ```
     *
     * @param $server
     * @param $taskId
     * @param $srcWorkerId
     * @param $data
     */
    public function onTask($server, $taskId, $srcWorkerId, $data)
    {
        try {
            call_user_func(...$data);
            $server->finish($data);
        } catch (\Exception $e) {
            $msg = "Task #{$taskId} srcWorker #{$srcWorkerId} Exception:\n";
            $msg .= "Err_data : {" . json_encode($data) . "}\n";
            $msg .= "Err_file : {$e->getFile()}\n";
            $msg .= "Err_line : {$e->getLine()}\n";
            $msg .= "Err_msg  : {$e->getMessage()}\n";
            $msg .= "Err_trace: {$e->getTraceAsString()}";
            Yii::error($msg);
        }
    }

    /**
     * The sw task process finish the callback event
     * @param $server
     * @param $taskId
     * @param $data
     */
    public function onFinish($server, $taskId, $data)
    {
        Yii::info("[Task #{$taskId}] Finish,data is " . json_encode($data));
    }

    /**
     * Get sw server pid file, use pid for server process control
     * @return mixed
     */
    public function getPidFile()
    {
        return $this->set['pid_file'];
    }
}