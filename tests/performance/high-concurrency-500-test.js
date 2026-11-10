import http from 'k6/http';
import { check, sleep, group } from 'k6';

export const options = {
    stages: [
        { duration: '30s', target: 100 },  // Ramp up to 100
        { duration: '1m', target: 300 },   // Surge to 300
        { duration: '1m', target: 500 },   // Peak stress: 500 concurrent users
        { duration: '30s', target: 0 },    // Cool down
    ],
    thresholds: {
        http_req_failed: ['rate<0.05'],     // Target under 5% failure under extreme 500 VU load
        http_req_duration: ['p(95)<1000'],  // 95% under 1s
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';

export default function () {
    // Unique IP per iteration across a 64k space so rate limiting doesn't falsely flag load test
    const subnet = (__VU % 250) + 1;
    const host = (__ITER % 250) + 1;
    const virtualIp = `10.${subnet}.${host}.1`;

    const params = {
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Forwarded-For': virtualIp,
        },
    };

    group('Public Endpoints Under 500 Concurrency', () => {
        // 1. Ping (Liveness Probe)
        const pingRes = http.get(`${BASE_URL}/api/v1/ping`, params);
        check(pingRes, {
            'ping is 200': (r) => r.status === 200,
            'ping is not 500': (r) => r.status !== 500,
        });

        // 2. Settings & Fees (Cached App Settings)
        const settingsRes = http.get(`${BASE_URL}/api/v1/app/settings`, params);
        check(settingsRes, {
            'settings is 200': (r) => r.status === 200,
            'settings is not 500': (r) => r.status !== 500,
        });

        // 3. Events Feed (Public Discovery with in-memory caching)
        const eventsRes = http.get(`${BASE_URL}/api/v1/explore/events`, params);
        check(eventsRes, {
            'explore events is 200': (r) => r.status === 200,
            'explore events is not 500': (r) => r.status !== 500,
        });
    });

    sleep(1);
}

