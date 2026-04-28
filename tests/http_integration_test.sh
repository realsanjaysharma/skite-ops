#!/bin/bash
# Phase 3+ HTTP Integration Test
# Runs against local XAMPP at http://localhost/skite/
# Uses ops.test.phase2@skite.local (OPS_MANAGER, user_id=3)
# Each test: name, method, route, expected_http_code, [body]
# Reports pass/fail per endpoint and a summary at the end.

set +e

BASE="http://localhost/skite/index.php"
COOKIES="/tmp/skite_test_cookies.txt"
RESULTS="/tmp/skite_test_results.txt"
> "$RESULTS"

PASS=0
FAIL=0
TOTAL=0

# ----- LOGIN -----
echo "=== Auth: login ==="
LOGIN_RESP=$(curl -s -X POST "$BASE?route=auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"ops.test.phase2@skite.local","password":"TestPass123!"}' \
  -c "$COOKIES")

CSRF=$(echo "$LOGIN_RESP" | grep -oE '"csrf_token":"[a-f0-9]+"' | cut -d'"' -f4)
if [ -z "$CSRF" ]; then
  echo "LOGIN FAILED — cannot continue"
  echo "$LOGIN_RESP"
  exit 1
fi
echo "Login OK. CSRF: ${CSRF:0:16}..."
echo ""

# ----- HELPER -----
run_test() {
  local name="$1"
  local method="$2"
  local route="$3"
  local expected="$4"
  local body="$5"
  TOTAL=$((TOTAL + 1))

  if [ "$method" = "GET" ]; then
    code=$(curl -s -o /tmp/skite_last_resp.txt -w "%{http_code}" \
      -b "$COOKIES" -H "X-CSRF-Token: $CSRF" \
      "$BASE?route=$route")
  else
    code=$(curl -s -o /tmp/skite_last_resp.txt -w "%{http_code}" \
      -X "$method" -b "$COOKIES" \
      -H "Content-Type: application/json" \
      -H "X-CSRF-Token: $CSRF" \
      -d "$body" \
      "$BASE?route=$route")
  fi

  if [ "$code" = "$expected" ]; then
    echo "  [PASS] $name ($method $route → $code)"
    PASS=$((PASS + 1))
    echo "PASS|$name|$method $route|$code" >> "$RESULTS"
  else
    snippet=$(head -c 200 /tmp/skite_last_resp.txt | tr '\n' ' ')
    echo "  [FAIL] $name ($method $route → $code, expected $expected)"
    echo "         body: $snippet"
    FAIL=$((FAIL + 1))
    echo "FAIL|$name|$method $route|got $code expected $expected|$snippet" >> "$RESULTS"
  fi
}

# ============================================
# PHASE 2: GREEN BELT CORE
# ============================================
echo "=== Phase 2: Green Belt Core ==="
run_test "Belt list"                 GET  "belt/list"                                    200
run_test "Belt get (id=1)"           GET  "belt/get&belt_id=1"                          200
run_test "Cycle list"                GET  "cycle/list"                                   200
run_test "Supervisor assignment list" GET "supervisorassignment/list&belt_id=1"         200
run_test "Authority assignment list"  GET "authorityassignment/list&belt_id=1"          200
run_test "Outsourced assignment list" GET "outsourcedassignment/list&belt_id=1"         200
echo ""

# ============================================
# PHASE 3: FIELD OPERATIONS
# ============================================
echo "=== Phase 3: Field Operations ==="
run_test "Watering list"             GET  "watering/list&date=$(date +%Y-%m-%d)"        200
run_test "Attendance list"           GET  "attendance/list&date=$(date +%Y-%m-%d)"      200
run_test "Labour list"               GET  "labour/list&date=$(date +%Y-%m-%d)"          200
run_test "Issue list"                GET  "issue/list"                                   200
run_test "Upload my-list"            GET  "upload/my-list"                               200
run_test "Upload review list"        GET  "upload/list"                                  200
run_test "Oversight watering"        GET  "oversight/watering"                           200
echo ""

# ============================================
# PHASE 3: TASK + WORKER
# ============================================
echo "=== Phase 3: Tasks & Workers ==="
run_test "Request list"              GET  "request/list"                                 200
run_test "Task list"                 GET  "task/list"                                    200
run_test "Task my"                   GET  "task/my"                                      200
run_test "Task progress list"        GET  "taskprogress/list"                            200
run_test "Worker list"               GET  "worker/list"                                  200
run_test "Worker availability"       GET  "worker/availability&date=$(date +%Y-%m-%d)"  200
run_test "Workday list"              GET  "workday/list&date=$(date +%Y-%m-%d)"         200
echo ""

# ============================================
# PHASE 3: ADVERTISEMENT
# ============================================
echo "=== Phase 3: Advertisement ==="
run_test "Site list"                 GET  "site/list"                                    200
run_test "Site list (CITY)"          GET  "site/list&site_category=CITY"                200
run_test "Site list (HIGHWAY)"       GET  "site/list&site_category=HIGHWAY"             200
run_test "Site list (GREEN_BELT)"    GET  "site/list&site_category=GREEN_BELT"          200
run_test "Campaign list"             GET  "campaign/list"                                200
run_test "Free Media list"           GET  "freemedia/list"                               200
echo ""

# ============================================
# PHASE 3: MONITORING
# ============================================
echo "=== Phase 3: Monitoring ==="
run_test "Monitoring plan list"      GET  "monitoringplan/list&month=$(date +%Y-%m)"    200
run_test "Monitoring history"        GET  "monitoring/history"                           200
echo ""

# ============================================
# PHASE 4: AUTHORITY + REPORTS + SETTINGS
# ============================================
echo "=== Phase 4: Authority / Reports / Settings ==="
run_test "Authority view"            GET  "authority/view"                               200
run_test "Settings list"             GET  "settings/list"                                200
run_test "Audit list"                GET  "audit/list"                                   200
run_test "Report belt-health"        GET  "report/belt-health&month=$(date +%Y-%m)"     200
run_test "Report supervisor-activity" GET "report/supervisor-activity&month=$(date +%Y-%m)" 200
run_test "Report worker-activity"    GET  "report/worker-activity&month=$(date +%Y-%m)" 200
run_test "Report ad-ops"             GET  "report/advertisement-operations&month=$(date +%Y-%m)" 200
echo ""

# ============================================
# PHASE 8: CLEANUP
# ============================================
echo "=== Phase 8: Rejected Cleanup ==="
run_test "Cleanup list"              GET  "upload/cleanup-list"                          200
echo ""

# ============================================
# DASHBOARD
# ============================================
echo "=== Dashboards ==="
run_test "Dashboard master"          GET  "dashboard/master"                             200
run_test "Dashboard green-belt"      GET  "dashboard/green-belt"                         200
run_test "Dashboard advertisement"   GET  "dashboard/advertisement"                      200
run_test "Dashboard monitoring"      GET  "dashboard/monitoring"                         200
echo ""

# ============================================
# RBAC (governance)
# ============================================
echo "=== Governance ==="
run_test "User list"                 GET  "user/list"                                    200
run_test "Role list"                 GET  "role/list"                                    200
echo ""

# ============================================
# SUMMARY
# ============================================
echo "================================================"
echo "TOTAL: $TOTAL    PASS: $PASS    FAIL: $FAIL"
echo "================================================"
if [ "$FAIL" -gt 0 ]; then
  echo ""
  echo "FAILURES:"
  grep "^FAIL" "$RESULTS" | while IFS='|' read -r status name route detail snippet; do
    echo "  - $name | $route | $detail"
    echo "    → $snippet"
  done
fi
