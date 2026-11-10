
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '1m', target: 50 },
        { duration: '2m', target: 100 },
        { duration: '2m', target: 250 },
        { duration: '2m', target: 500 },
        { duration: '3m', target: 1000 },
        { duration: '2m', target: 0 },
    ],

    thresholds: {
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<500'],
    },
};

const BASE_URL = __ENV.BASE_URL;

export default function () {
    const response = http.get(
        `${BASE_URL}/api/v1/events`
    );

    check(response, {
        'status is 200': (r) => r.status === 200,
        'response below 500ms': (r) => r.timings.duration < 500,
    });

    sleep(1);
}

