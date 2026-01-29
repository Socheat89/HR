<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Server\IoServer;

require dirname(__DIR__) . 'autoload.php';

class RealTimeServer implements MessageComponentInterface {
    protected $clients;
    protected $pdo;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        // ភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ (ដូចនៅក្នុង admin.php)
        $host = 'localhost';
        $dbname = 'samann1_mini_app_db';
        $username = 'samann1_mini_app_db';
        $password = 'samann1_mini_app_db@2025';
        $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "ការតភ្ជាប់ថ្មី! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if ($data['action'] === 'fetch_cart') {
            $cart_data = $this->fetchCartData();
            $from->send(json_encode(['type' => 'cart_update', 'data' => $cart_data]));
        } elseif ($data['action'] === 'fetch_orders') {
            $order_data = $this->fetchOrderData();
            $from->send(json_encode(['type' => 'order_update', 'data' => $order_data]));
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "បានបិទការតភ្ជាប់! ({$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "កំហុស: {$e->getMessage()}\n";
        $conn->close();
    }

    private function fetchCartData() {
        $stmt = $this->pdo->query("SELECT c.id, c.user_id, c.product_id, c.quantity, c.created_at, p.name AS product_name, u.full_name AS user_name 
                                   FROM cart c 
                                   JOIN products p ON c.product_id = p.id 
                                   JOIN users u ON c.user_id = u.telegram_id 
                                   ORDER BY c.created_at DESC LIMIT 100");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchOrderData() {
        $stmt = $this->pdo->query("SELECT o.id, o.user_id, o.total, o.points_earned, o.created_at, o.discount_applied, u.full_name AS user_name 
                                   FROM orders o 
                                   JOIN users u ON o.user_id = u.telegram_id 
                                   ORDER BY o.created_at DESC LIMIT 100");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($orders as &$order) {
            $stmt = $this->pdo->prepare("SELECT oi.product_id, p.name AS product_name 
                                         FROM order_items oi 
                                         JOIN products p ON oi.product_id = p.id 
                                         WHERE oi.order_id = ?");
            $stmt->execute([$order['id']]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $orders;
    }

    public function broadcast($type, $data) {
        foreach ($this->clients as $client) {
            $client->send(json_encode(['type' => $type, 'data' => $data]));
        }
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new RealTimeServer()
        )
    ),
    8080
);

$server->run();