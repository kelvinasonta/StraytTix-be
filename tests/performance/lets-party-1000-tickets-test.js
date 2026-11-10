import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

// Track purchase outcomes
const successfulTickets = new Counter('successful_tickets_ordered');
const failedOrders = new Counter('failed_orders');

export const options = {
    scenarios: {
        rush_1000_tickets: {
            executor: 'shared-iterations',
            vus: 100,             // 100 concurrent buyers
            iterations: 1000,     // Exact 1,000 orders attempted
            maxDuration: '2m',    // Complete within 2 minutes
        },
    },
    thresholds: {
        'failed_orders': ['count==0'],  // Zero failures expected
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';

// Event details for "Lets Party"
const EVENT_UUID = '857d5fd9-4501-46c5-b64e-869fc97fc335';
const TICKET_TYPE_UUID = 'edca4307-cfc3-43ba-92bf-9c43f5b3d05f';

export default function () {
    const buyerId = `party_goer_${__VU}_${__ITER}_${Date.now()}`;
    const virtualIp = `10.150.${(__VU % 250) + 1}.${(__ITER % 250) + 1}`;

    const payload = JSON.stringify({
        customer_name: `Party Guest ${__VU}`,
        customer_email: `${buyerId}@example.com`,
        customer_phone: '+2348098765432',
        items: [
            {
                ticket_type_uuid: TICKET_TYPE_UUID,
                quantity: 1, // 1 ticket per order
                attendee_name: `Party Guest ${__VU}`,
                attendee_email: `${buyerId}@example.com`,
            },
        ],
    });

    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Forwarded-For': virtualIp,
        },
        timeout: '15s',
    };

    const res = http.post(`${BASE_URL}/api/v1/events/${EVENT_UUID}/orders`, payload, params);

    const isCreated = res.status === 201;
    if (isCreated) {
        successfulTickets.add(1);
    } else {
        failedOrders.add(1);
    }

    check(res, {
        'order created (HTTP 201)': (r) => r.status === 201,
        'no 500 error': (r) => r.status !== 500,
    });

    sleep(0.05);
}

export function teardown() {
    console.log(`\n=================================================`);
    console.log(`1,000 TICKETS RUSH TEST FOR "Lets Party"`);
    console.log(`Event UUID: ${EVENT_UUID}`);
    console.log(`Ticket UUID: ${TICKET_TYPE_UUID}`);
    console.log(`=================================================\n`);
}

