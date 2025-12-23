// C:\xampp\htdocs\ws-server\server.js
const WebSocket = require('ws');
const server = new WebSocket.Server({ port: 8080 });

let clients = {}; // userId -> ws bağlantısı

server.on('connection', (ws) => {
    console.log('Yeni bağlantı kuruldu');

    ws.on('message', (msg) => {
        try {
            const data = JSON.parse(msg);
            if (data.type === 'register') {
                clients[data.userId] = ws;
                console.log(`Kullanıcı ${data.userId} kayıt oldu`);
            } else if (data.type === 'task') {
                // Birine görev atandıysa mesajı gönder
                const receiver = clients[data.receiverId];
                if (receiver && receiver.readyState === WebSocket.OPEN) {
                    receiver.send(JSON.stringify({
                        type: 'task_notification',
                        title: data.title,
                        description: data.description,
                        sender: data.sender
                    }));
                    console.log(`Görev bildirimi gönderildi -> ${data.receiverId}`);
                }
            }
        } catch (e) {
            console.error('Hatalı mesaj:', e);
        }
    });

    ws.on('close', () => {
        for (let id in clients) {
            if (clients[id] === ws) {
                delete clients[id];
                console.log(`Kullanıcı ${id} bağlantıyı kapattı`);
            }
        }
    });
});

console.log("✅ WebSocket sunucusu çalışıyor: ws://127.0.0.1:8080");
