"""
Test Suite: Broken Function-Level Authorization / BFLA (OWASP API5:2023)
Verifies:
1. Standard user / tenant cannot invoke administrative endpoints (/admin/*).
2. Standard user cannot toggle global feature flags.
3. Standard user cannot approve organizer verifications or inspect system audit logs.
4. Privilege escalation attempts strictly return 403 Forbidden.
"""

import pytest

ADMIN_RESTRICTED_ENDPOINTS = [
    ("GET", "/admin/audit-logs", None),
    ("GET", "/admin/audit-logs/1", None),
    ("GET", "/admin/auth/me", None),
    ("POST", "/admin/feature-flags/credits_system/toggle", {}),
    ("GET", "/admin/verifications", None),
    ("POST", "/admin/verifications/dummy-uuid/review", {"status": "approved"}),
]


@pytest.mark.parametrize("method,path,payload", ADMIN_RESTRICTED_ENDPOINTS)
def test_standard_user_cannot_access_admin_endpoints(base_url, user_a_session, method, path, payload):
    """
    Assert that User A (standard organizer/customer) receives 403 Forbidden when calling admin endpoints.
    """
    url = f"{base_url}{path}"
    response = user_a_session.request(method, url, json=payload)

    assert response.status_code in [403, 401], (
        f"BFLA / Privilege Escalation Critical Vulnerability: Standard user accessed admin endpoint "
        f"{method} {path}! Expected 403 Forbidden, but received HTTP {response.status_code}: {response.text[:250]}"
    )

    data = response.json()
    assert data.get("success") is False, (
        f"Security Failure: Admin endpoint did not return failure envelope: {response.text}"
    )


def test_standard_user_cannot_elevate_to_admin_via_profile_update(base_url, user_a_session):
    """
    Mass-assignment attack test: User A attempts to inject 'is_admin': true or 'admin_role'
    into PUT /auth/profile.
    """
    url = f"{base_url}/auth/profile"
    payload = {
        "first_name": "Attacker",
        "last_name": "User",
        "is_admin": True,
        "admin_role": "superadmin",
        "admin_permissions": ["*"],
    }
    response = user_a_session.put(url, json=payload)

    # Re-verify profile via /auth/me
    me_res = user_a_session.get(f"{base_url}/auth/me")
    assert me_res.status_code == 200

    user_data = me_res.json().get("data", {}).get("user", {})
    assert not user_data.get("is_admin", False), (
        f"Mass Assignment Privilege Escalation Vulnerability: Standard user elevated self to admin via profile update!"
    )

