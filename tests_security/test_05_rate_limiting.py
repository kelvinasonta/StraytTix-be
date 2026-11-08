"""
Test Suite: Rate-Limiting & Unrestricted Resource Consumption (OWASP API4:2023)
Verifies:
1. Sensitive endpoints enforce rate limiting (HTTP 429 Too Many Requests).
2. Rate-limited endpoints include standard Retry-After or X-RateLimit headers.
"""

import pytest


@pytest.mark.parametrize("endpoint,payload,expected_limit", [
    ("/auth/password/forgot", {"email": "rate_limit_probe@eventsos.com"}, 15),
])
def test_rate_limiting_enforced_under_burst_load(base_url, unauthenticated_session, endpoint, payload, expected_limit):
    """
    Simulates high-frequency burst traffic to sensitive auth routes.
    Asserts that the rate limiter halts execution with HTTP 429 after exceeding quota.
    """
    url = f"{base_url}{endpoint}"
    got_429 = False

    for attempt in range(1, expected_limit + 15):
        response = unauthenticated_session.post(url, json=payload)

        if response.status_code == 429:
            got_429 = True
            # Assert rate limit headers are present
            headers = response.headers
            has_rate_limit_header = any(
                h in headers for h in ["Retry-After", "X-RateLimit-Remaining", "X-RateLimit-Reset"]
            )
            assert has_rate_limit_header or "retry" in response.text.lower() or "too many" in response.text.lower(), (
                f"Rate Limiting Warning: 429 returned but missing standard rate limit header context."
            )
            break

    assert got_429, (
        f"Rate Limiting Vulnerability: Endpoint {endpoint} allowed {expected_limit + 15} burst requests "
        f"without triggering HTTP 429 Too Many Requests!"
    )

