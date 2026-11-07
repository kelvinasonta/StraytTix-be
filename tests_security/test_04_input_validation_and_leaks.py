"""
Test Suite: Input Validation, Negative Testing & Information Leakage (OWASP API8:2023 / API3:2023)
Verifies:
1. Malformed payload schemas return 422 Unprocessable Entity or 400 Bad Request.
2. Missing required parameters are properly handled by FormRequests / Validators.
3. SQL Injection / Type confusion payloads do not crash the database or leak stack traces / engine info.
"""

import pytest

NEGATIVE_PAYLOAD_TESTS = [
    # 1. Register with missing required fields
    (
        "POST",
        "/auth/register",
        {},
        422,
        "Missing all fields in register",
    ),
    # 2. Register with invalid email type and mismatched password
    (
        "POST",
        "/auth/register",
        {
            "first_name": "Test",
            "last_name": "User",
            "email": ["not_a_valid_email_string"],
            "password": "pass",
            "password_confirmation": "different_pass",
        },
        422,
        "Invalid types in register",
    ),
    # 3. Create event with invalid types and SQL injection probe in title
    (
        "POST",
        "/organizers/dummy-org-id/events",
        {
            "title": "' OR '1'='1' --",
            "event_country": 12345,  # Expected string
            "event_type": "invalid_enum_type",
            "event_category": "should_be_array_not_string",
        },
        422,
        "Malformed event creation payload",
    ),
    # 4. Top-up credits with negative or float amount
    (
        "POST",
        "/organizers/dummy-org-id/credits/top-up/initialize",
        {
            "amount": -500,
            "currency": "INVALID_CURRENCY",
        },
        422,
        "Negative credit top-up amount",
    ),
]


@pytest.mark.parametrize("method,path,payload,expected_status,scenario", NEGATIVE_PAYLOAD_TESTS)
def test_negative_payload_validation_and_no_leak(base_url, user_a_session, method, path, payload, expected_status, scenario):
    """
    Assert that the API safely validates unexpected inputs, rejects with 422/400/404,
    and never leaks SQL error traces, file paths, or 500 crashes.
    """
    url = f"{base_url}{path}"
    response = user_a_session.request(method, url, json=payload)

    # Status must be client error (never 500)
    assert response.status_code in [400, 404, 422], (
        f"Input Validation Failure ({scenario}): Endpoint {method} {path} returned "
        f"HTTP {response.status_code} instead of client error. Body: {response.text[:250]}"
    )

    body = response.text.lower()
    # Information Leakage checks
    forbidden_leak_signatures = [
        "sqlstate",
        "pdoexception",
        "stack trace:",
        "vendor/laravel",
        "syntax error at or near",
        "mysql server has gone away",
        "mariadb",
        "sqlite3",
    ]
    for leak in forbidden_leak_signatures:
        assert leak not in body, (
            f"Information Leakage Vulnerability ({scenario}): Endpoint leaked internal error signature '{leak}' "
            f"in response body: {response.text[:300]}"
        )

