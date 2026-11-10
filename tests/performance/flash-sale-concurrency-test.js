import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

// Custom metrics to track race conditions
const successfulOrders = new Counter('successful_orders');
const soldOutRejections = new Counter('sold_out_rejections');
const unexpectedErrors = new Counter('unexpected_errors');

export const options = {
    // 50 parallel buyers simultaneously submitting checkout requests
    scenarios: {
        flash_sale_rush: {
            executor: 'shared-iterations',
            vus: 50,
            iterations: 100, // 100 total checkout attempts competing at once
            maxDuration: '30s',
        },
    },
    thresholds: {
        'unexpected_errors': ['count==0'], // Zero 500 errors or uncaught exceptions
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';

// Setup: Automatically discover an active event & ticket tier with limited inventory
export function setup() {
    const res = http.get(`${BASE_URL}/api/v1/explore/events`);
    const data = JSON.parse(res.body || '{}');

    if (!data.data || !data.data.data || data.data.data.length === 0) {
        throw new Error('No public events found to run flash sale test against.');
    }

    const event = data.data.data[0];
    const ticketType = event.ticketTypes[0];

    return {
        eventUuid: event.id,
        eventTitle: event.title,
        ticketTypeUuid: ticketType.id,
        ticketTypeName: ticketType.name,
        availableCapacity: ticketType.remainingQuantity || ticketType.quantity,
    };
}

export default function (data) {
    const uniqueId = `buyer_${__VU}_${__ITER}_${Date.now()}`;
    const virtualIp = `10.200.${(__VU % 250) + 1}.${(__ITER % 250) + 1}`;

    const payload = JSON.stringify({
        customer_name: `Stress Buyer ${__VU}`,
        customer_email: `${uniqueId}@example.com`,
        customer_phone: '+2348012345678',
        items: [
            {
                ticket_type_uuid: data.ticketTypeUuid,
                quantity: 1,
                attendee_name: `Attendee ${uniqueId}`,
                attendee_email: `${uniqueId}@example.com`,
            },
        ],
    });

    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Forwarded-For': virtualIp,
        },
        timeout: '10s',
    };

    const res = http.post(`${BASE_URL}/api/v1/events/${data.eventUuid}/orders`, payload, params);

    if (res.status === 201) {
        successfulOrders.add(1);
    } else if (res.status === 422) {
        // Correct behavior when sold out or capacity exhausted
        soldOutRejections.add(1);
    } else {
        unexpectedErrors.add(1);
    }

    check(res, {
        'status is either 201 (Ordered) or 422 (Sold Out)': (r) => r.status === 201 || r.status === 422,
        'no 500 internal server errors': (r) => r.status !== 500,
    });

    sleep(0.1);
}

export function teardown(data) {
    // Check remaining inventory after the flash sale rush
    const res = http.get(`${BASE_URL}/api/v1/explore/events`);
    const parsed = JSON.parse(res.body || '{}');
    const event = (parsed.data?.data || []).find(e => e.id === data.eventUuid);
    const ticketType = event?.ticketTypes?.find(t => t.id === data.ticketTypeUuid);

    console.log(`\n=================================================`);
    console.log(`FLASH SALE TEST SUMMARY for "${data.eventTitle}"`);
    console.log(`Ticket Tier: ${data.ticketTypeName}`);
    console.log(`Initial Available: ${data.availableCapacity}`);
    if (ticketType) {
        console.log(`Remaining Available: ${ticketType.remainingQuantity}`);
        console.log(`Reserved in Carts: ${ticketType.reservedQuantity}`);
        console.log(`Sold: ${ticketType.soldQuantity}`);
    }
    console.log(`=================================================\n`);
}

