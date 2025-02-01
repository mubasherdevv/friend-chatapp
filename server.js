const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

// Simple health check endpoint
app.get('/health', (req, res) => {
    res.json({ status: 'healthy' });
});

// WebSocket handling
io.on('connection', (socket) => {
    console.log('Client connected');

    socket.on('join_room', (roomId) => {
        socket.join(roomId);
        console.log(`User joined room: ${roomId}`);
    });

    socket.on('leave_room', (roomId) => {
        socket.leave(roomId);
        console.log(`User left room: ${roomId}`);
    });

    socket.on('chat_message', (data) => {
        io.to(data.roomId).emit('chat_message', {
            userId: data.userId,
            username: data.username,
            message: data.message,
            timestamp: new Date().toISOString()
        });
    });

    socket.on('typing', (data) => {
        socket.to(data.roomId).emit('typing', {
            userId: data.userId,
            username: data.username,
            isTyping: data.isTyping
        });
    });

    socket.on('disconnect', () => {
        console.log('Client disconnected');
    });
});

const port = process.env.PORT || 3000;
server.listen(port, '0.0.0.0', () => {
    console.log(`WebSocket server running on port ${port}`);
});
