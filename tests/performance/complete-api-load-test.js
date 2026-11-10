import http from 'k6/http';
import { check, sleep, group } from 'k6';

export const options = {
    stages: [
        { duration: '15s', target: 20 },  // Ramp up
        { duration: '30s', target: 50 },  // Steady load
        { duration: '15s', target: 0 },   // Cool down
    ],
    thresholds: {
        http_req_failed: ['rate<0.02'],     // Error rate < 2%
        http_req_duration: ['p(95)<500'],   // 95% of requests under 500ms
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';

export default function () {
    // Generate a unique IP per iteration so local tests don't trigger the 60 req/min single-IP rate limiter
    const virtualIp = `192.168.${(__VU % 250) + 1}.${(__ITER % 250) + 1}`;

    const params = {
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Forwarded-For': virtualIp,
        },
    };

    group('Public Endpoints', () => {
        // 1. Health Liveness Ping
        const pingRes = http.get(`${BASE_URL}/api/v1/ping`, params);
        check(pingRes, {
            'ping status 200': (r) => r.status === 200,
            'ping latency < 100ms': (r) => r.timings.duration < 100,
        });

        // 2. App Settings & Dynamic Platform Fees
        const settingsRes = http.get(`${BASE_URL}/api/v1/app/settings`, params);
        check(settingsRes, {
            'settings status 200': (r) => r.status === 200,
            'has platform fee': (r) => {
                const body = JSON.parse(r.body || '{}');
                return body.data && body.data.platform_fee_percentage !== undefined;
            },
        });

        // 3. Events Discovery (Correct route: /api/v1/explore/events or /api/v1/events/discover)
        const eventsRes = http.get(`${BASE_URL}/api/v1/explore/events`, params);
        check(eventsRes, {
            'explore events status 200': (r) => r.status === 200,
            'explore events duration < 300ms': (r) => r.timings.duration < 300,
        });
    });

    sleep(1);
}
