// S5: chat. SOCKETS clients connect to the Reverb server and stay subscribed
// to their conversation channel while a sender posts messages. Pass: p95
// delivery <= 500 ms; no message lost after a reconnect storm.
//   k6 run -e BASE_URL=https://host/api -e WS_URL=wss://host:443/app/<REVERB_APP_KEY> \
//          -e CONVERSATION_ID=5 -e TOKEN=<student token> -e SENDER_TOKEN=<teacher token> loadtests/s5_chat_sockets.js
import http from 'k6/http';
import ws from 'k6/ws';
import { check } from 'k6';
import { Trend } from 'k6/metrics';
import { BASE, auth } from './common.js';

const delivery = new Trend('chat_delivery_ms', true);
const WS_URL = `${__ENV.WS_URL}?protocol=7&client=k6&version=1.0`;
const CONVERSATION = __ENV.CONVERSATION_ID;
const SOCKETS = Number(__ENV.SOCKETS || 1000);

export const options = {
  scenarios: {
    listeners: { executor: 'per-vu-iterations', vus: SOCKETS, iterations: 1, maxDuration: '10m' },
    sender: { executor: 'constant-arrival-rate', rate: 2, timeUnit: '1s', duration: '5m', preAllocatedVUs: 5, exec: 'send', startTime: '30s' },
  },
  thresholds: { chat_delivery_ms: ['p(95)<500'] },
};

export default function () {
  const res = ws.connect(WS_URL, {}, (socket) => {
    socket.on('message', (raw) => {
      const frame = JSON.parse(raw);
      if (frame.event === 'pusher:connection_established') {
        const socketId = JSON.parse(frame.data).socket_id;
        const authRes = http.post(
          `${BASE}/broadcasting/auth`,
          JSON.stringify({ socket_id: socketId, channel_name: `private-conversation.${CONVERSATION}` }),
          auth(__ENV.TOKEN),
        );
        socket.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel: `private-conversation.${CONVERSATION}`, auth: authRes.json('auth') } }));
      } else if (frame.event === 'message.sent') {
        const sentAt = Number(JSON.parse(frame.data).message.body.split('@')[1]);
        delivery.add(Date.now() - sentAt);
      } else if (frame.event === 'pusher:ping') {
        socket.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
      }
    });
    socket.setTimeout(() => socket.close(), 6 * 60 * 1000);
  });
  check(res, { 'ws 101': (r) => r && r.status === 101 });
}

export function send() {
  const res = http.post(
    `${BASE}/conversations/${CONVERSATION}/messages`,
    JSON.stringify({ body: `load@${Date.now()}`, client_id: `k6-${__VU}-${__ITER}` }),
    auth(__ENV.SENDER_TOKEN),
  );
  check(res, { sent: (r) => r.status === 201 });
}
