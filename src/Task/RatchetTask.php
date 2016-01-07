<?php
namespace ProjectRena\Task;

use Cilex\Command\Command;
use ProjectRena\RenaApp;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\Wamp\Topic;
use Ratchet\Wamp\WampServer;
use Ratchet\Wamp\WampServerInterface;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\Server;
use React\ZMQ\Context;
use Stomp;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use ZMQ;

/**
 * Starts up a ratchet server that sends killmails to the user
 */
class RatchetTask extends Command
{

    /**
     */
    protected function configure()
    {
        $this->setName('run:ratchet')->setDescription('Starts up a zmq listener, with a websocket server that also passes data to stomp..');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //Init rena
        $app = RenaApp::getInstance();

        // Setup the react event loop and call up the pusher class
        $loop = Factory::create();
        $pusher = new Pusher();
        $stomper = new stompSend();

        // ZeroMQ server
        $context = new Context($loop);
        $pull = $context->getSocket(ZMQ::SOCKET_PULL);
        $pull->bind("tcp://127.0.0.1:5555");
        $pull->on("message", array($pusher, "onMessage"));
        $pull->on("message", array($stomper, "onMessage"));

        // Websocket server
        $webSock = new Server($loop);
        $webSock->listen(8800, "0.0.0.0");
        $webServer = new IoServer(new HttpServer(new WsServer(new WampServer($pusher))), $webSock);
        $loop->run();
    }
}

class stompSend
{
    protected $stomp;
    protected $app;

    public function __construct()
    {
        $this->app = RenaApp::getInstance();
        $this->stomp = new Stomp($this->app->baseConfig->getConfig("server", "stomp"), $this->app->baseConfig->getConfig("username", "stomp"), $this->app->baseConfig->getConfig("password", "stomp"));
    }

    public function onMessage($message)
    {
        $this->app->StatsD->increment("stompSent");
        $this->stomp->send("/topic/kills", $message);
    }
}

/**
 * Class Pusher
 *
 * @package ProjectRena\Task
 */
class Pusher implements WampServerInterface
{
    /**
     * @var \SplObjectStorage
     */
    protected $clients;

    /**
     *
     */
    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    /**
     * @param ConnectionInterface $conn
     */
    function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
    }

    /**
     * @param ConnectionInterface $conn
     */
    function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $conn->close();
    }

    /**
     * @param ConnectionInterface $conn
     * @param \Exception $e
     */
    function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->clients->detach($conn);
        $conn->close();
    }

    /**
     * @param $entry
     */
    public function onMessage($entry)
    {
        //$entryData = json_decode($entry, true);
        foreach ($this->clients as $client)
            $client->send($entry);
    }

    /**
     * @param ConnectionInterface $conn
     * @param string $id
     * @param Topic|string $topic
     * @param array $params
     */
    function onCall(ConnectionInterface $conn, $id, $topic, array $params)
    {
    }

    /**
     * @param ConnectionInterface $conn
     * @param Topic|string $topic
     */
    function onSubscribe(ConnectionInterface $conn, $topic)
    {
    }

    /**
     * @param ConnectionInterface $conn
     * @param Topic|string $topic
     */
    function onUnSubscribe(ConnectionInterface $conn, $topic)
    {
    }

    /**
     * @param ConnectionInterface $conn
     * @param Topic|string $topic
     * @param string $event
     * @param array $exclude
     * @param array $eligible
     */
    function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible)
    {
    }
}