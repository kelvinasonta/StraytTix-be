import os
import pytest
import requests

BASE_URL = os.getenv("API_BASE_URL", "http://localhost:8080/api/v1")

# Static tokens or fallback test accounts
ADMIN_TOKEN = os.getenv("ADMIN_TOKEN", "")
USER_A_TOKEN = os.getenv("USER_A_TOKEN", "")
USER_B_TOKEN = os.getenv("USER_B_TOKEN", "")

# Sample test fixtures & seeded resources
ORGANIZER_A_UUID = os.getenv("ORGANIZER_A_UUID", "")
ORGANIZER_B_UUID = os.getenv("ORGANIZER_B_UUID", "")
EVENT_A_UUID = os.getenv("EVENT_A_UUID", "")
EVENT_B_UUID = os.getenv("EVENT_B_UUID", "")
ORDER_A_UUID = os.getenv("ORDER_A_UUID", "")
ORDER_B_UUID = os.getenv("ORDER_B_UUID", "")


@pytest.fixture(scope="session")
def base_url():
    """Returns the base API target URL."""
    return BASE_URL.rstrip("/")


@pytest.fixture(scope="session")
def unauthenticated_session():
    """Session without any authorization headers."""
    session = requests.Session()
    session.headers.update({
        "Accept": "application/json",
        "Content-Type": "application/json",
    })
    return session


@pytest.fixture(scope="session")
def admin_session(base_url):
    """Session for administrative role."""
    session = requests.Session()
    session.headers.update({
        "Accept": "application/json",
        "Content-Type": "application/json",
    })
    token = ADMIN_TOKEN
    if not token:
        # Dynamic login fallback against local dev seeder
        res = session.post(f"{base_url}/auth/login", json={
            "email": "admin@eventsos.com",
            "password": "Password123!",
        })
        if res.status_code == 200:
            token = res.json().get("data", {}).get("token")
    if token:
        session.headers.update({"Authorization": f"Bearer {token}"})
    return session


@pytest.fixture(scope="session")
def user_a_session(base_url):
    """Session for User A (Tenant 1)."""
    session = requests.Session()
    session.headers.update({
        "Accept": "application/json",
        "Content-Type": "application/json",
    })
    token = USER_A_TOKEN
    if not token:
        # Dynamic login fallback against local dev seeder
        res = session.post(f"{base_url}/auth/login", json={
            "email": "organizer@eventsos.com",
            "password": "Password123!",
        })
        if res.status_code == 200:
            token = res.json().get("data", {}).get("token")
    if token:
        session.headers.update({"Authorization": f"Bearer {token}"})
    return session


@pytest.fixture(scope="session")
def user_b_session(base_url):
    """Session for User B (Tenant 2)."""
    session = requests.Session()
    session.headers.update({
        "Accept": "application/json",
        "Content-Type": "application/json",
    })
    token = USER_B_TOKEN
    if not token:
        # Register or authenticate dynamic second tenant
        res = session.post(f"{base_url}/auth/register", json={
            "first_name": "Tenant",
            "last_name": "Two",
            "email": "tenant2_sec_audit@eventsos.com",
            "password": "Password123!",
            "password_confirmation": "Password123!",
            "account_type": "organizer",
            "organizer_type": "individual",
        })
        if res.status_code in [200, 201]:
            token = res.json().get("data", {}).get("token")
        else:
            # Try login if already registered
            res = session.post(f"{base_url}/auth/login", json={
                "email": "tenant2_sec_audit@eventsos.com",
                "password": "Password123!",
            })
            if res.status_code == 200:
                token = res.json().get("data", {}).get("token")
    if token:
        session.headers.update({"Authorization": f"Bearer {token}"})
    return session


@pytest.fixture(scope="session")
def tenant_resources(base_url, user_a_session, user_b_session):
    """
    Discovers or creates isolated resources for Tenant A and Tenant B
    to verify BOLA / IDOR isolation.
    """
    resources = {
        "user_a_organizer": ORGANIZER_A_UUID,
        "user_b_organizer": ORGANIZER_B_UUID,
        "user_a_event": EVENT_A_UUID,
        "user_b_event": EVENT_B_UUID,
        "user_a_order": ORDER_A_UUID,
        "user_b_order": ORDER_B_UUID,
    }

    # Fetch User A's organizer if not provided
    if not resources["user_a_organizer"]:
        res = user_a_session.get(f"{base_url}/organizers")
        if res.status_code == 200:
            data = res.json().get("data", [])
            items = data.get("data", data) if isinstance(data, dict) else data
            if items and len(items) > 0:
                resources["user_a_organizer"] = items[0].get("id") or items[0].get("uuid")

    # Fetch User B's organizer if not provided
    if not resources["user_b_organizer"]:
        res = user_b_session.get(f"{base_url}/organizers")
        if res.status_code == 200:
            data = res.json().get("data", [])
            items = data.get("data", data) if isinstance(data, dict) else data
            if items and len(items) > 0:
                resources["user_b_organizer"] = items[0].get("id") or items[0].get("uuid")
        if not resources["user_b_organizer"]:
            # Create organizer for Tenant B
            create_res = user_b_session.post(f"{base_url}/organizers", json={
                "name": "Tenant B Security Corp",
                "type": "organization",
                "email": "tenant2_sec_audit@eventsos.com",
            })
            if create_res.status_code in [200, 201]:
                resources["user_b_organizer"] = create_res.json().get("data", {}).get("id")

    # Fetch User A's events
    if resources["user_a_organizer"] and not resources["user_a_event"]:
        res = user_a_session.get(f"{base_url}/organizers/{resources['user_a_organizer']}/events")
        if res.status_code == 200:
            data = res.json().get("data", [])
            items = data.get("data", data) if isinstance(data, dict) else data
            if items and len(items) > 0:
                resources["user_a_event"] = items[0].get("id") or items[0].get("uuid")

    # Fetch or create User B's event
    if resources["user_b_organizer"] and not resources["user_b_event"]:
        res = user_b_session.get(f"{base_url}/organizers/{resources['user_b_organizer']}/events")
        if res.status_code == 200:
            data = res.json().get("data", [])
            items = data.get("data", data) if isinstance(data, dict) else data
            if items and len(items) > 0:
                resources["user_b_event"] = items[0].get("id") or items[0].get("uuid")
        if not resources["user_b_event"]:
            create_res = user_b_session.post(f"{base_url}/organizers/{resources['user_b_organizer']}/events", json={
                "title": "Tenant B Private Event",
                "description": "Tenant B Isolated Event for IDOR security tests",
                "eventCountry": "Nigeria",
                "eventType": "virtual",
                "onlineMeetingUrl": "https://meet.google.com/abc-defg-hij",
                "scheduleType": "single",
                "scheduleTimezone": "UTC",
                "startDate": "2027-01-01",
                "startTime": "10:00",
                "endDate": "2027-01-01",
                "endTime": "12:00",
            })
            if create_res.status_code in [200, 201]:
                resources["user_b_event"] = create_res.json().get("data", {}).get("id")

    return resources

