import http from 'k6/http';
import { check, sleep, group } from 'k6';

export const options = {
    stages: [
        { duration: '30s', target: 250 },   // Ramp up to 250 VUs
        { duration: '45s', target: 500 },   // Step up to 500 VUs
        { duration: '1m',  target: 1000 },  // Peak surge: 1,000 CONCURRENT USERS
        { duration: '30s', target: 0 },     // Ramp down / cool down
    ],
    thresholds: {
        http_req_failed: ['rate<0.10'],     // Keep failure rate under 10% under extreme 1000 VU load
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';

export default function () {
    // Generate distinct IP per user to emulate 1,000 distinct real devices
    const octet2 = (__VU % 250) + 1;
    const octet3 = ((__ITER + __VU) % 250) + 1;
    const virtualIp = `172.16.${octet2}.${octet3}`;

    const params = {
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Forwarded-For': virtualIp,
        },
        timeout: '15s', // 15-second socket timeout for local queue depth
    };

    group('Stress Endpoints (1000 Concurrent VUs)', () => {
        // 1. Liveness Ping
        const pingRes = http.get(`${BASE_URL}/api/v1/ping`, params);
        check(pingRes, {
            'ping is 200': (r) => r.status === 200,
            'ping not 500': (r) => r.status !== 500,
        });

        // 2. Platform App Settings & Fees
        const settingsRes = http.get(`${BASE_URL}/api/v1/app/settings`, params);
        check(settingsRes, {
            'settings is 200': (r) => r.status === 200,
            'settings not 500': (r) => r.status !== 500,
        });

        // 3. Public Events Feed
        const eventsRes = http.get(`${BASE_URL}/api/v1/explore/events`, params);
        check(eventsRes, {
            'explore events is 200': (r) => r.status === 200,
            'explore events not 500': (r) => r.status !== 500,
        });
    });

    sleep(1);
}

