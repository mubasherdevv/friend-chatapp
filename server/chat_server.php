<?php
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/database.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;

class ChatServer implements MessageComponentInterface {
    protected $clients;
    protected $users;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->users = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg);
        
        switch($data->type) {
            case 'join':
                $this->users[$from->resourceId] = [
                    'user_id' => $data->user_id,
                    'room_id' => $data->room_id
                ];
                
                // Get last message timestamp from client
                $lastTimestamp = isset($data->last_timestamp) ? $data->last_timestamp : null;
                
                // Send missed messages if timestamp provided
                if ($lastTimestamp) {
                    global $conn;
                    $stmt = $conn->prepare("
                        SELECT m.*, u.username, u.avatar, u.is_admin
                        FROM messages m
                        JOIN users u ON m.user_id = u.id
                        WHERE m.room_id = ? AND m.created_at > ?
                        ORDER BY m.created_at ASC
                    ");
                    $stmt->bind_param("is", $data->room_id, $lastTimestamp);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        $from->send(json_encode([
                            'type' => 'message',
                            'id' => $row['id'],
                            'user_id' => $row['user_id'],
                            'username' => $row['username'],
                            'avatar' => $row['avatar'],
                            'is_admin' => $row['is_admin'],
                            'message' => $row['message'],
                            'created_at' => $row['created_at']
                        ]));
                    }
                }
                
                // Notify others about the new user
                $this->broadcastToRoom($data->room_id, [
                    'type' => 'user_joined',
                    'user_id' => $data->user_id
                ]);
                break;
                
            case 'message':
                // Save message to database first
                global $conn;
                $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->bind_param("iis", $data->room_id, $data->user_id, $data->message);
                
                if ($stmt->execute()) {
                    $messageId = $stmt->insert_id;
                    $createdAt = date('Y-m-d H:i:s');
                    
                    // Get user info for the response
                    $userStmt = $conn->prepare("SELECT username, avatar, is_admin FROM users WHERE id = ?");
                    $userStmt->bind_param("i", $data->user_id);
                    $userStmt->execute();
                    $userResult = $userStmt->get_result();
                    $userData = $userResult->fetch_assoc();
                    
                    // Prepare response with complete message data
                    $response = [
                        'type' => 'message',
                        'id' => $messageId,
                        'user_id' => $data->user_id,
                        'username' => $userData['username'],
                        'avatar' => $userData['avatar'],
                        'is_admin' => $userData['is_admin'],
                        'message' => $data->message,
                        'created_at' => $createdAt,
                        'temp_id' => isset($data->temp_id) ? $data->temp_id : null
                    ];
                    
                    // Send to everyone including sender for consistency
                    $this->broadcastToRoom($data->room_id, $response);
                } else {
                    // Send error back to sender
                    $from->send(json_encode([
                        'type' => 'error',
                        'message' => 'Failed to save message'
                    ]));
                }
                break;
                
            case 'kick':
                if ($this->isAdmin($data->user_id, $data->room_id)) {
                    $this->kickUser($data->target_user_id, $data->room_id);
                }
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        if (isset($this->users[$conn->resourceId])) {
            $userData = $this->users[$conn->resourceId];
            $this->broadcastToRoom($userData['room_id'], [
                'type' => 'user_left',
                'user_id' => $userData['user_id']
            ]);
            unset($this->users[$conn->resourceId]);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function broadcastToRoom($roomId, $message) {
        foreach ($this->clients as $client) {
            if (isset($this->users[$client->resourceId]) && 
                $this->users[$client->resourceId]['room_id'] === $roomId) {
                $client->send(json_encode($message));
            }
        }
    }

    protected function broadcastToRoomExcept($roomId, $message, $except = null) {
        foreach ($this->clients as $client) {
            if (isset($this->users[$client->resourceId]) && 
                $this->users[$client->resourceId]['room_id'] === $roomId &&
                (!$except || $client !== $except)) {
                $client->send(json_encode($message));
            }
        }
    }

    protected function isAdmin($userId, $roomId) {
        global $conn;
        $stmt = $conn->prepare("SELECT admin_id FROM rooms WHERE id = ?");
        $stmt->bind_param("i", $roomId);
        $stmt->execute();
        $result = $stmt->get_result();
        $room = $result->fetch_assoc();
        return $room && $room['admin_id'] == $userId;
    }

    protected function kickUser($userId, $roomId) {
        global $conn;
        $stmt = $conn->prepare("DELETE FROM room_members WHERE user_id = ? AND room_id = ?");
        $stmt->bind_param("ii", $userId, $roomId);
        $stmt->execute();
        
        $this->broadcastToRoom($roomId, [
            'type' => 'user_kicked',
            'user_id' => $userId
        ]);
    }
}

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server('0.0.0.0:8080', $loop);
$server = new Ratchet\Server\IoServer(
    new Ratchet\Http\HttpServer(
        new Ratchet\WebSocket\WsServer(
            new ChatServer()
        )
    ),
    $socket,
    $loop
);

// Add periodic ping to keep connections alive
$loop->addPeriodicTimer(30, function () use ($server) {
    foreach ($server->connections as $conn) {
        $conn->send(json_encode(['type' => 'ping']));
    }
});

$loop->run();
