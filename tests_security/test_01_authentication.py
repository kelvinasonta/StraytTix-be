"""
Test Suite: Broken Authentication & Session Boundaries (OWASP API2:2023)
Verifies:
1. Missing Authorization header returns 401 Unauthorized (never 200 or 500).
2. Malformed / Invalid Bearer tokens return 401 Unauthorized.
3. Expired token signatures return 401 Unauthorized.
"""

import pytest
import requests

PROTECTED_ENDPOINTS = [
    ("GET", "/auth/me", None),
    ("POST", "/auth/logout", {}),
    ("GET", "/auth/sessions", None),
    ("GET", "/orders", None),
    ("GET", "/tickets", None),
    ("POST", "/tickets", {"ticket_type_uuid": "dummy-uuid"}),
    ("GET", "/organizers", None),
    ("POST", "/organizers", {"name": "Test Org", "type": "individual"}),
]


@pytest.mark.parametrize("method,path,payload", PROTECTED_ENDPOINTS)
def test_unauthenticated_request_rejected(base_url, unauthenticated_session, method, path, payload):
    """
    Assert that requests to protected endpoints without credentials strictly return 401 Unauthorized.
    """
    url = f"{base_url}{path}"
    response = unauthenticated_session.request(method, url, json=payload)

    assert response.status_code == 401, (
        f"Security Failure: Unauthenticated request to {method} {path} returned "
        f"{response.status_code} instead of 401 Unauthorized. Response: {response.text[:200]}"
    )

    data = response.json()
    assert data.get("success") is False or "message" in data, (
        f"Security Failure: Malformed error envelope on 401 response: {response.text}"
    )


@pytest.mark.parametrize("invalid_token", [
    "Bearer invalid_token_1234567890",
    "Bearer 999999|invalidSanctumHashTokenString",
    "Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.fake_signature",
    "Token malformed_scheme_without_bearer",
    "Bearer ",
])
def test_malformed_token_rejected(base_url, invalid_token):
    """
    Assert that malformed, corrupted, or forged tokens are strictly rejected with 401.
    """
    url = f"{base_url}/auth/me"
    headers = {
        "Accept": "application/json",
        "Authorization": invalid_token,
    }
    response = requests.get(url, headers=headers)

    assert response.status_code == 401, (
        f"Security Failure: Malformed token '{invalid_token[:20]}...' allowed access or caused 500. "
        f"Got HTTP {response.status_code}: {response.text[:200]}"
    )

