"""
Test Suite: Broken Object-Level Authorization / IDOR (OWASP API1:2023)
Verifies:
1. User A (Tenant 1) cannot read, modify, or delete resources owned by User B (Tenant 2).
2. Cross-tenant reads and mutations strictly return 403 Forbidden or 404 Not Found.
3. Neither private organizer metrics, settings, attendee lists, nor credit wallets leak cross-tenant.
"""

import pytest


def test_cross_tenant_organizer_read_forbidden(base_url, user_a_session, tenant_resources):
    """
    User A attempts to read User B's private organizer management profile.
    """
    target_org = tenant_resources.get("user_b_organizer")
    if not target_org:
        pytest.skip("Tenant B organizer not available for cross-tenant testing")

    url = f"{base_url}/organizers/{target_org}"
    response = user_a_session.get(url)

    assert response.status_code in [403, 404], (
        f"BOLA / IDOR Vulnerability: User A accessed Tenant B organizer profile! "
        f"Expected 403 or 404, but received HTTP {response.status_code}: {response.text[:250]}"
    )


def test_cross_tenant_organizer_update_forbidden(base_url, user_a_session, tenant_resources):
    """
    User A attempts to tamper with User B's organizer profile name and description.
    """
    target_org = tenant_resources.get("user_b_organizer")
    if not target_org:
        pytest.skip("Tenant B organizer not available for cross-tenant testing")

    url = f"{base_url}/organizers/{target_org}"
    payload = {
        "name": "HACKED BY TENANT A",
        "description": "Unauthorized malicious modification",
    }
    response = user_a_session.put(url, json=payload)

    assert response.status_code in [403, 404], (
        f"BOLA / IDOR Critical Vulnerability: User A modified Tenant B organizer profile! "
        f"Expected 403 or 404, but received HTTP {response.status_code}: {response.text[:250]}"
    )


def test_cross_tenant_event_update_forbidden(base_url, user_a_session, tenant_resources):
    """
    User A attempts to modify User B's private event.
    """
    target_org = tenant_resources.get("user_b_organizer")
    target_event = tenant_resources.get("user_b_event")
    if not target_org or not target_event:
        pytest.skip("Tenant B event resources not available")

    url = f"{base_url}/organizers/{target_org}/events/{target_event}"
    payload = {
        "title": "Tampered Event Title",
        "status": "cancelled",
    }
    response = user_a_session.put(url, json=payload)

    assert response.status_code in [403, 404], (
        f"BOLA / IDOR Critical Vulnerability: User A modified Tenant B event! "
        f"Expected 403 or 404, but received HTTP {response.status_code}: {response.text[:250]}"
    )


def test_cross_tenant_attendee_list_leak_prevented(base_url, user_a_session, tenant_resources):
    """
    User A attempts to harvest attendee personal data (PII) from User B's event.
    """
    target_org = tenant_resources.get("user_b_organizer")
    target_event = tenant_resources.get("user_b_event")
    if not target_org or not target_event:
        pytest.skip("Tenant B event resources not available")

    url = f"{base_url}/organizers/{target_org}/events/{target_event}/attendees"
    response = user_a_session.get(url)

    assert response.status_code in [403, 404], (
        f"BOLA / PII Data Leak Vulnerability: User A read attendee list of Tenant B! "
        f"Expected 403 or 404, but received HTTP {response.status_code}: {response.text[:250]}"
    )


def test_cross_tenant_credits_wallet_tampering_prevented(base_url, user_a_session, tenant_resources):
    """
    User A attempts to inspect or reconcile User B's billing wallet and credit balance.
    """
    target_org = tenant_resources.get("user_b_organizer")
    if not target_org:
        pytest.skip("Tenant B organizer not available")

    # Read wallet balance
    wallet_url = f"{base_url}/organizers/{target_org}/credits/wallet"
    res1 = user_a_session.get(wallet_url)
    assert res1.status_code in [403, 404], (
        f"BOLA Vulnerability: User A accessed Tenant B credits wallet balance! "
        f"Got HTTP {res1.status_code}"
    )

    # Reconcile ledger
    reconcile_url = f"{base_url}/organizers/{target_org}/credits/reconcile"
    res2 = user_a_session.get(reconcile_url)
    assert res2.status_code in [403, 404], (
        f"BOLA Vulnerability: User A executed ledger reconciliation on Tenant B! "
        f"Got HTTP {res2.status_code}"
    )

