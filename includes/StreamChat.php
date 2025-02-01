<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/avatar_helper.php';

use GetStream\StreamChat\Client;

class StreamChat {
    private static $instance = null;
    private $client;
    private $config;

    private function __construct() {
        $this->config = require __DIR__ . '/../config/stream.php';
        $this->client = new Client(
            $this->config['api_key'],
            $this->config['api_secret']
        );
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getApiKey() {
        return $this->config['api_key'];
    }

    public function getApiSecret() {
        return $this->config['api_secret'];
    }

    public function createUserToken($userId) {
        return $this->client->createToken((string)$userId);
    }

    public function upsertUser($userData) {
        $userData = array_merge([
            'id' => (string)$userData['id'],
            'role' => 'user',
            'image' => getAvatarUrl($userData['avatar'] ?? null),
            'name' => $userData['username'] ?? 'Anonymous'
        ], $userData);
        return $this->client->upsertUser($userData);
    }

    public function createChannel($channelType, $channelId, $userId, $data = []) {
        $channel = $this->client->Channel($channelType, (string)$channelId);
        $channel->create((string)$userId, $data);
        return $channel;
    }

    public function sendMessage($channelType, $channelId, $userId, $message, $attachments = []) {
        $channel = $this->client->Channel($channelType, (string)$channelId);
        $messageData = [
            'text' => $message,
            'user_id' => (string)$userId
        ];

        if (!empty($attachments)) {
            $messageData['attachments'] = $attachments;
        }

        return $channel->sendMessage($messageData);
    }

    public function uploadFile($file) {
        try {
            $response = $this->client->uploadFile(
                $file['tmp_name'],
                $file['name'],
                $file['type']
            );
            return $response;
        } catch (Exception $e) {
            error_log("Stream Chat File Upload Error: " . $e->getMessage());
            return null;
        }
    }

    public function addMembers($channelType, $channelId, $userIds) {
        $channel = $this->client->Channel($channelType, (string)$channelId);
        return $channel->addMembers(array_map('strval', $userIds));
    }

    public function removeMembers($channelType, $channelId, $userIds) {
        $channel = $this->client->Channel($channelType, (string)$channelId);
        return $channel->removeMembers(array_map('strval', $userIds));
    }

    public function keyGenerator() {
        return bin2hex(random_bytes(16));
    }
}
