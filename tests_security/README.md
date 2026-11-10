# Automated API Security Test Suite (OWASP API Security Top 10)

This directory contains an automated Python `pytest` security auditing suite for the StaytTix API.

## Requirements & Setup

```bash
# 1. Install dependencies
pip install -r tests_security/requirements.txt
```

## Running the Security Audit

```bash
# Run against local Docker Nginx instance (default: http://localhost:8080/api/v1)
pytest tests_security/

# Or specify custom host & pre-minted bearer tokens:
export API_BASE_URL="http://localhost:8080/api/v1"
export ADMIN_TOKEN="<ADMIN_TOKEN>"
export USER_A_TOKEN="<USER_A_TOKEN>"
export USER_B_TOKEN="<USER_B_TOKEN>"

pytest tests_security/ -v
```

## Security Test Modules

1. **`test_01_authentication.py`** (Broken Authentication - OWASP API2:2023)
   - Asserts missing `Authorization` headers strictly return `401 Unauthorized`.
   - Asserts malformed, corrupted, and invalid Bearer tokens return `401`.

2. **`test_02_bola_idor.py`** (Broken Object-Level Authorization - OWASP API1:2023)
   - Tests User A accessing User B's organizer profiles, events, private attendees, and billing wallets.
   - Asserts strict `403 Forbidden` / `404 Not Found` boundaries.

3. **`test_03_bfla_privilege_escalation.py`** (Broken Function-Level Authorization - OWASP API5:2023)
   - Tests non-admin accounts invoking `/admin/*` management routes and feature flag toggles.
   - Tests mass-assignment privilege escalation attempts on `/auth/profile`.

4. **`test_04_input_validation_and_leaks.py`** (Improper Input & Information Leakage - OWASP API8/API3:2023)
   - Tests malformed data types, missing required attributes, and SQL injection strings.
   - Verifies that responses return proper `422/400` status codes and **never** leak SQLSTATE errors, PDO exceptions, or file traces.

5. **`test_05_rate_limiting.py`** (Unrestricted Resource Consumption - OWASP API4:2023)
   - Tests burst threshold loops against sensitive endpoints (`/auth/password/forgot`) to assert enforcement of `429 Too Many Requests`.

