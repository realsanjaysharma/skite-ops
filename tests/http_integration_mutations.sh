#!/bin/bash
# Phase 3+ HTTP Mutation & Guard Tests
# Verifies POST endpoints accept correct payloads and reject wrong methods.
# Uses today's date so day-locked tests don't conflict with old runs.

set +e

BASE="http://localhost/skite/index.php"
COOKIES="/tmp/skite_test_cookies2.txt"
TODAY=$(date +%Y-%m-%d)

PASS=0
FAIL=0
TOTAL=0

# ----- LOGIN -----
echo "=== Login ==="
LOGIN_RESP=$(curl -s -X POST "$BASE?route=auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"ops.test.phase2@skite.local","password":"TestPass123!"}' \
  -c "$COOKIES")
CSRF=$(echo "$LOGIN_RESP" | grep -oE '"csrf_token":"[a-f0-9]+"' | cut -d'"' -f4)
[ -z "$CSRF" ] && { echo "LOGIN FAILED"; exit 1; }
echo "Login OK"
echo ""

# ----- HELPER -----
run() {
  local name="$1"
  local method="$2"
  local route="$3"
  local expected="$4"
  local body="$5"
  local must_contain="$6"
  TOTAL=$((TOTAL + 1))

  if [ "$method" = "GET" ]; then
    code=$(curl -s -o /tmp/skite_last.txt -w "%{http_code}" \
      -b "$COOKIES" -H "X-CSRF-Token: $CSRF" \
      "$BASE?route=$route")
  else
    code=$(curl -s -o /tmp/skite_last.txt -w "%{http_code}" \
      -X "$method" -b "$COOKIES" \
      -H "Content-Type: application/json" \
      -H "X-CSRF-Token: $CSRF" \
      -d "$body" \
      "$BASE?route=$route")
  fi

  body_resp=$(cat /tmp/skite_last.txt)
  ok=true
  [ "$code" != "$expected" ] && ok=false
  if [ -n "$must_contain" ] && ! echo "$body_resp" | grep -q "$must_contain"; then
    ok=false
  fi

  if [ "$ok" = "true" ]; then
    echo "  [PASS] $name ($method → $code)"
    PASS=$((PASS + 1))
  else
    snippet=$(echo "$body_resp" | head -c 250 | tr '\n' ' ')
    echo "  [FAIL] $name ($method → $code, expected $expected${must_contain:+ containing \"$must_contain\"})"
    echo "         body: $snippet"
    FAIL=$((FAIL + 1))
  fi
}

# ============================================
# METHOD GUARDS — POST routes must reject GET
# ============================================
echo "=== Method Guards (POST routes reject GET with 405) ==="
run "belt/create rejects GET"        GET "belt/create"        405
run "watering/mark rejects GET"      GET "watering/mark"      405
run "attendance/mark rejects GET"    GET "attendance/mark"    405
run "labour/mark rejects GET"        GET "labour/mark"        405
run "task/create rejects GET"        GET "task/create"        405
run "task/start rejects GET"         GET "task/start"         405
run "issue/create rejects GET"       GET "issue/create"       405
echo ""

# ============================================
# PAYLOAD VALIDATION — bad payloads return 400
# ============================================
echo "=== Payload Validation ==="
run "watering/mark: missing belt_id"   POST "watering/mark"   400 '{}'                                    "error"
run "labour/mark: missing belt_id"     POST "labour/mark"     400 '{}'                                    "error"
run "attendance/mark: missing user"    POST "attendance/mark" 400 '{}'                                    "error"
run "issue/create: missing fields"     POST "issue/create"    400 '{}'                                    "error"
run "site/create: bad enum"            POST "site/create"     400 '{"site_code":"X","site_category":"BILLBOARD","lighting_type":"LIT"}' "error"
echo ""

# ============================================
# HAPPY PATH MUTATIONS (idempotent / safe)
# ============================================
echo "=== Happy Path Writes ==="

# Watering DONE for belt_id=1 today (UPSERT semantics)
run "watering/mark DONE today"       POST "watering/mark"       200 \
  "{\"belt_id\":1,\"watering_date\":\"$TODAY\",\"status\":\"DONE\"}"      "success"

# Watering NOT_REQUIRED (override)
run "watering/mark NOT_REQUIRED"     POST "watering/mark"       200 \
  "{\"belt_id\":1,\"watering_date\":\"$TODAY\",\"status\":\"NOT_REQUIRED\",\"reason_text\":\"rain\"}" "success"

# Labour entry today (UPSERT semantics with new field names)
run "labour/mark today (correct fields)" POST "labour/mark"     200 \
  "{\"belt_id\":1,\"entry_date\":\"$TODAY\",\"labour_count\":2,\"gardener_count\":1,\"night_guard_count\":0}" "success"

# Settings list update — fetch first, update one
echo ""
echo "=== Settings round-trip ==="
SETTINGS=$(curl -s -b "$COOKIES" -H "X-CSRF-Token: $CSRF" "$BASE?route=settings/list")
echo "  Settings sample: $(echo "$SETTINGS" | head -c 150)..."
echo ""

# ============================================
# AUTH GUARDS
# ============================================
echo "=== Auth Guards (no session) ==="
TOTAL=$((TOTAL + 1))
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE?route=belt/list")
if [ "$code" = "401" ] || [ "$code" = "403" ]; then
  echo "  [PASS] Unauthenticated belt/list → $code"
  PASS=$((PASS + 1))
else
  echo "  [FAIL] Unauthenticated belt/list → $code (expected 401 or 403)"
  FAIL=$((FAIL + 1))
fi

# ============================================
# SUMMARY
# ============================================
echo ""
echo "================================================"
echo "TOTAL: $TOTAL    PASS: $PASS    FAIL: $FAIL"
echo "================================================"
