#!/bin/sh

set -eu

BASE_URL=${BASE_URL:-http://localhost:8080}
TMP_DIR=$(mktemp -d)
RESPONSE_BODY="$TMP_DIR/response.json"

cleanup() {
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT INT TERM

fail() {
    printf 'FAIL: %s\n' "$1" >&2
    if [ -f "$RESPONSE_BODY" ]; then
        cat "$RESPONSE_BODY" >&2
        printf '\n' >&2
    fi
    exit 1
}

request() {
    method=$1
    path=$2
    auth_token=${3:-}
    body=${4:-}

    if [ -n "$body" ] && [ -n "$auth_token" ]; then
        RESPONSE_STATUS=$(curl -sS -o "$RESPONSE_BODY" -w '%{http_code}' \
            -X "$method" "$BASE_URL$path" \
            -H "Authorization: Bearer $auth_token" \
            -H 'Content-Type: application/json' \
            --data "$body")
    elif [ -n "$body" ]; then
        RESPONSE_STATUS=$(curl -sS -o "$RESPONSE_BODY" -w '%{http_code}' \
            -X "$method" "$BASE_URL$path" \
            -H 'Content-Type: application/json' \
            --data "$body")
    elif [ -n "$auth_token" ]; then
        RESPONSE_STATUS=$(curl -sS -o "$RESPONSE_BODY" -w '%{http_code}' \
            -X "$method" "$BASE_URL$path" \
            -H "Authorization: Bearer $auth_token")
    else
        RESPONSE_STATUS=$(curl -sS -o "$RESPONSE_BODY" -w '%{http_code}' \
            -X "$method" "$BASE_URL$path")
    fi
}

assert_status() {
    expected=$1
    description=$2
    if [ "$RESPONSE_STATUS" != "$expected" ]; then
        fail "$description: expected HTTP $expected, got $RESPONSE_STATUS"
    fi
}

assert_contains() {
    value=$1
    description=$2
    if ! grep -F "$value" "$RESPONSE_BODY" >/dev/null 2>&1; then
        fail "$description: response did not contain $value"
    fi
}

json_string() {
    key=$1
    sed -n "s/.*\"$key\":\"\([^\"]*\)\".*/\1/p" "$RESPONSE_BODY" | head -n 1
}

json_number() {
    key=$1
    sed -n "s/.*\"$key\":[[:space:]]*\([0-9][0-9]*\).*/\1/p" "$RESPONSE_BODY" | head -n 1
}

login() {
    email=$1
    request POST /api/auth/login '' "{\"email\":\"$email\",\"password\":\"Demo#12345\"}"
    assert_status 200 "login for $email"
    LOGIN_TOKEN=$(json_string token)
    [ -n "$LOGIN_TOKEN" ] || fail "login for $email did not return a token"
}

assert_scope() {
    email=$1
    scope_expected=$2
    login "$email"

    request GET /api/me "$LOGIN_TOKEN"
    assert_status 200 "$email /api/me"
    assert_contains "\"email\":\"$email\"" "$email /api/me email"

    request GET /api/accounts "$LOGIN_TOKEN"
    assert_status 200 "$email account scope"
    actual=$(json_number total)
    [ "$actual" = "$scope_expected" ] || fail "$email account scope: expected $scope_expected accounts, got ${actual:-empty}"
}

printf 'Smoke tests against %s\n' "$BASE_URL"

request GET /api/me
assert_status 401 'unauthenticated /api/me'

request POST /api/auth/login '' '{"email":"merchant1@payarc.test","password":"wrong-password"}'
assert_status 401 'invalid credentials'
assert_contains '"code":"invalid_credentials"' 'invalid credentials error code'

assert_scope admin@payarc.test 6
ADMIN_TOKEN=$LOGIN_TOKEN
assert_scope agent1@payarc.test 3
AGENT1_TOKEN=$LOGIN_TOKEN
assert_scope agent2@payarc.test 2
assert_scope merchant1@payarc.test 1
MERCHANT1_TOKEN=$LOGIN_TOKEN
assert_scope merchant2@payarc.test 2
MERCHANT2_TOKEN=$LOGIN_TOKEN
assert_scope merchant3@payarc.test 1
login norole@payarc.test
request GET /api/me "$LOGIN_TOKEN"
assert_status 200 'norole /api/me'
assert_contains '"account_ids":[]' 'norole account scope'
NOROLE_TOKEN=$LOGIN_TOKEN

request GET /api/accounts "$NOROLE_TOKEN"
assert_status 403 'no-role account access'

request GET /api/charges "$NOROLE_TOKEN"
assert_status 403 'no-role charge access'

request POST /api/charges "$AGENT1_TOKEN" '{"account_id":"1","amount":1200,"currency":"USD","customer_email":"smoke-agent@example.test"}'
assert_status 403 'agent charge creation'

request GET /api/charges?page_size=1 "$MERCHANT1_TOKEN"
assert_status 200 'merchant one charge list'
CHARGE_ID=$(json_string id)
[ -n "$CHARGE_ID" ] || fail 'merchant one charge list did not return a charge id'

request GET "/api/charges/$CHARGE_ID" "$MERCHANT2_TOKEN"
assert_status 404 'cross-tenant charge access'

ACCOUNT_ID=$(request GET /api/accounts "$MERCHANT1_TOKEN"; json_string id)
[ -n "$ACCOUNT_ID" ] || fail 'merchant one account list did not return an account id'

request POST /api/charges "$MERCHANT1_TOKEN" "{\"account_id\":\"$ACCOUNT_ID\",\"amount\":1200,\"currency\":\"USD\",\"customer_email\":\"smoke-void@example.test\",\"capture\":false,\"description\":\"P6 smoke void\"}"
assert_status 201 'authorized charge creation'
VOID_CHARGE_ID=$(json_string id)
[ -n "$VOID_CHARGE_ID" ] || fail 'authorized charge creation did not return an id'

request POST "/api/charges/$VOID_CHARGE_ID/void" "$MERCHANT1_TOKEN" '{"reason":"P6 smoke test"}'
assert_status 200 'void authorized charge'

request POST "/api/charges/$VOID_CHARGE_ID/void" "$MERCHANT1_TOKEN" '{"reason":"P6 duplicate void"}'
assert_status 409 'void already voided charge'

request POST /api/charges "$MERCHANT1_TOKEN" "{\"account_id\":\"$ACCOUNT_ID\",\"amount\":1300,\"currency\":\"USD\",\"customer_email\":\"smoke-refund@example.test\",\"capture\":true,\"description\":\"P6 smoke refund\"}"
assert_status 201 'captured charge creation'
REFUND_CHARGE_ID=$(json_string id)
[ -n "$REFUND_CHARGE_ID" ] || fail 'captured charge creation did not return an id'

request POST "/api/charges/$REFUND_CHARGE_ID/refund" "$MERCHANT1_TOKEN" '{"amount":999999,"reason":"P6 excessive refund"}'
assert_status 422 'excessive refund'

request POST "/api/charges/$REFUND_CHARGE_ID/refund" "$MERCHANT1_TOKEN" '{"reason":"P6 smoke test"}'
assert_status 200 'full refund'

request POST "/api/charges/$REFUND_CHARGE_ID/refund" "$MERCHANT1_TOKEN" '{"reason":"P6 duplicate refund"}'
assert_status 409 'refund already refunded charge'

printf 'Smoke tests passed.\n'
