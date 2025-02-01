# API Documentation

## Authentication Endpoints

### POST /login.php
Authenticates a user and creates a session.

**Request Body:**
```json
{
    "username": "string",
    "password": "string"
}
```

**Response:**
```json
{
    "success": true,
    "user": {
        "id": "integer",
        "username": "string",
        "avatar": "string"
    }
}
```

## Message Endpoints

### POST /chat.php
Sends a new message.

**Request Body:**
```json
{
    "message": "string",
    "room_id": "integer"
}
```

**Response:**
```json
{
    "success": true,
    "message": {
        "id": "integer",
        "content": "string",
        "user_id": "integer",
        "timestamp": "string",
        "avatar_url": "string"
    }
}
```

### GET /get_messages.php
Retrieves messages for a chat room.

**Query Parameters:**
- room_id (integer)
- last_id (integer)

**Response:**
```json
{
    "success": true,
    "messages": [
        {
            "id": "integer",
            "content": "string",
            "user_id": "integer",
            "timestamp": "string",
            "avatar_url": "string"
        }
    ]
}
```

## Friend Management Endpoints

### POST /friend_action.php
Handles friend-related actions (add, accept, reject, block).

**Request Body:**
```json
{
    "action": "string",
    "friend_id": "integer"
}
```

**Response:**
```json
{
    "success": true,
    "message": "string"
}
```

### GET /friends.php
Retrieves friend list and requests.

**Response:**
```json
{
    "friends": [
        {
            "id": "integer",
            "username": "string",
            "avatar": "string",
            "last_seen": "string",
            "status": "string"
        }
    ],
    "requests": [
        {
            "id": "integer",
            "user_id": "integer",
            "username": "string",
            "avatar": "string",
            "created_at": "string"
        }
    ]
}
```

## Error Responses
All endpoints may return error responses in the following format:

```json
{
    "success": false,
    "error": "string"
}
```

Common HTTP status codes:
- 200: Success
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 500: Internal Server Error
