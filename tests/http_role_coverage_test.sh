#!/bin/bash
# ============================================================
# Skite Ops — Role Coverage Test Suite
# Tests login, landing, allowed routes (200), and forbidden
# routes (403) for all 10 roles.
# Run: bash tests/http_role_coverage_test.sh
# ============================================================

set +e
BASE="http://localhost/skite/index.php"
PASS=0; FAIL=0; TOTAL=0

pass() { echo "  [PASS] $1"; PASS=$((PASS+1)); TOTAL=$((TOTAL+1)); }
fail() { echo "  [FAIL] $1 — expected $2, got $3"; FAIL=$((FAIL+1)); TOTAL=$((TOTAL+1)); }

check() {
  local label="$1" method="$2" route="$3" expected="$4" body="$5"
  local cookies="$6" csrf="$7"
  if [ "$method" = "GET" ]; then
    code=$(curl -s -o /dev/null -w "%{http_code}" \
      -b "$cookies" -H "X-CSRF-Token: $csrf" "$BASE?route=$route")
  else
    code=$(curl -s -o /dev/null -w "%{http_code}" \
      -X POST -b "$cookies" -H "X-CSRF-Token: $csrf" \
      -H "Content-Type: application/json" -d "$body" "$BASE?route=$route")
  fi
  [ "$code" = "$expected" ] && pass "$label ($code)" || fail "$label" "$expected" "$code"
}

login() {
  local email="$1" cookiejar="$2"
  local resp=$(curl -s -X POST "$BASE?route=auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$email\",\"password\":\"TestPass123!\"}" \
    -c "$cookiejar")
  echo "$resp" | grep -o '"csrf_token":"[^"]*"' | cut -d'"' -f4
}

check_landing() {
  local label="$1" expected_route="$2" cookies="$3" csrf="$4"
  local resp=$(curl -s -b "$cookies" -H "X-CSRF-Token: $csrf" "$BASE?route=auth/session")
  local landing=$(echo "$resp" | grep -o '"landing_route":"[^"]*"' | cut -d'"' -f4 | sed 's/\\//g')
  [ "$landing" = "$expected_route" ] && pass "$label (landing=$landing)" || fail "$label landing" "$expected_route" "$landing"
}

echo "================================================"
echo " Skite Ops — Role Coverage Test"
echo "================================================"
echo ""

# ============================================================
# 1. OPS_MANAGER
# ============================================================
echo "--- OPS_MANAGER (ops.test.phase2@skite.local) ---"
JAR=/tmp/skite_test_ops.txt
CSRF=$(login "ops.test.phase2@skite.local" "$JAR")
[ -n "$CSRF" ] && pass "Login" || { echo "  [FAIL] Login failed — skipping role"; }

check_landing "Landing route" "dashboard/master" "$JAR" "$CSRF"
# Allowed
check "belt/list"            GET  "belt/list"         200 "" "$JAR" "$CSRF"
check "issue/list"           GET  "issue/list"         200 "" "$JAR" "$CSRF"
check "task/list"            GET  "task/list"          200 "" "$JAR" "$CSRF"
check "user/list"            GET  "user/list"          200 "" "$JAR" "$CSRF"
check "audit/list"           GET  "audit/list"         200 "" "$JAR" "$CSRF"
check "settings/list"        GET  "settings/list"      200 "" "$JAR" "$CSRF"
check "report/belt-health"   GET  "report/belt-health&month=2026-04" 200 "" "$JAR" "$CSRF"
check "authority/view"       GET  "authority/view"     200 "" "$JAR" "$CSRF"
check "alert/list"           GET  "alert/list"         200 "" "$JAR" "$CSRF"
# Forbidden (other-role modules)
check "workday/my-list (blocked)" GET "workday/my-list"        403 "" "$JAR" "$CSRF"
check "media/client-library (blocked)" GET "media/client-library" 403 "" "$JAR" "$CSRF"
echo ""

# ============================================================
# 2. HEAD_SUPERVISOR
# ============================================================
echo "--- HEAD_SUPERVISOR (headsupervisor.phase2@skite.local) ---"
JAR=/tmp/skite_test_hs.txt
CSRF=$(login "headsupervisor.phase2@skite.local" "$JAR")
[ -n "$CSRF" ] && pass "Login" || { echo "  [FAIL] Login failed — skipping role"; }

check_landing "Landing route" "oversight/watering" "$JAR" "$CSRF"
check "oversight/watering"   GET  "oversight/watering"     200 "" "$JAR" "$CSRF"
check "attendance/list"      GET  "attendance/list"        200 "" "$JAR" "$CSRF"
check "labour/list"          GET  "labour/list"            200 "" "$JAR" "$CSRF"
check "issue/list"           GET  "issue/list"             200 "" "$JAR" "$CSRF"
# Forbidden (head supervisor: daily ops only, not belt management or governance)
check "belt/list (blocked)"     GET "belt/list"     403 "" "$JAR" "$CSRF"
check "user/list (blocked)"     GET "user/list"     403 "" "$JAR" "$CSRF"
check "audit/list (blocked)"    GET "audit/list"    403 "" "$JAR" "$CSRF"
check "task/list (blocked)"     GET "task/list"     403 "" "$JAR" "$CSRF"
echo ""

# ============================================================
# 3. GREEN_BELT_SUPERVISOR
# ============================================================
echo "--- GREEN_BELT_SUPERVISOR (test.supervisor.p2@skite.local) ---"
JAR=/tmp/skite_test_gbs.txt
CSRF=$(login "test.supervisor.p2@skite.local" "$JAR")
[ -n "$CSRF" ] && pass "Login" || { echo "  [FAIL] Login failed — skipping role"; }

check_landing "Landing route" "upload/supervisor" "$JAR" "$CSRF"
check "upload/my-list"       GET  "upload/my-list"         200 "" "$JAR" "$CSRF"
# Forbidden (supervisors only see their upload surfaces, not management views)
check "belt/list (blocked)"         GET "belt/list"         403 "" "$JAR" "$CSRF"
check "user/list (blocked)"         GET "user/list"         403 "" "$JAR" "$CSRF"
check "attendance/list (blocked)"   GET "attendance/list"   403 "" "$JAR" "$CSRF"
check "labour/list (blocked)"       GET "labour/list"       403 "" "$JAR" "$CSRF"
check "issue/list (blocked)"        GET "issue/list"        403 "" "$JAR" "$CSRF"
echo ""

# ============================================================
# 4. OUTSOURCED_MAINTAINER
# ============================================================
echo "--- OUTSOURCED_MAINTAINER (test.outsourced.p2@skite.local) ---"
JAR=/tmp/skite_test_out.txt
CSRF=$(login "test.outsourced.p2@skite.local" "$JAR")
[ -n "$CSRF" ] && pass "Login" || { echo "  [FAIL] Login failed — skipping role"; }

check_landing "Landing route" "upload/outsourced" "$JAR" "$CSRF"
check "upload/my-list"       GET  "upload/my-list"         200 "" "$JAR" "$CSRF"
# Forbidden
check "belt/list (blocked)"         GET "belt/list"         403 "" "$JAR" "$CSRF"
check "attendance/list (blocked)"   GET "attendance/list"   403 "" "$JAR" "$CSRF"
check "user/list (blocked)"         GET "user/list"         403 "" "$JAR" "$CSRF"
check "watering/list (blocked)"     GET "watering/list"     403 "" "$JAR" "$CSRF"
echo ""

# ============================================================
# 5. FABRICATION_LEAD
# ============================================================
echo "--- FABRICATION_LEAD (lead.upload.foundation@skite.local) ---"
JAR=/tmp/skite_test_lead.txt
CSRF=$(login "lead.upload.foundation@skite.local" "$JAR")
[ -n "$CSRF" ] && pass "Login" || { echo "  [FAIL] Login failed — skipping role"; }

check_landing "Landing route" "task/my" "$JAR" "$CSRF"
check "task/my"              GET  "task/my"              200 "" "$JAR" "$CSRF"
check "workday/my-list"      GET  "workday/my-list"      200 "" "$JAR" "$CSRF"
check "worker/list"          GET  "worker/list"          200 "" "$JAR" "$CSRF"
check "task/start (method guard)" POST "task/start"      400 '{}' "$JAR" "$CSRF"
# Forbidden
check "user/list (blocked)"     GET "user/list"     403 "" "$JAR" "$CSRF"
check "belt/list (blocked)"     GET "belt/list"     403 "" "$JAR" "$CSRF"
check "issue/list (blocked)"    GET "issue/list"    403 "" "$JAR" "$CSRF"
echo ""

# ============================================================
# 6. MONITORING_TEAM
# ============================================================
echo "--- MONITORING_TEAM (monitor.phase3@skite.local) ---"
JAR=/tmp/skite_test_mon.txt
CSRF=$(login "monitor.phase3@skite.local" "$JAR")
[ -n "$CSRF" ] && pass "Login" || { echo "  [FAIL] Login failed — skipping role"; }

check_landing "Landing route" "monitoring/upload" "$JAR" "$CSRF"
check "upload/my-list"       GET  "upload/my-list"         200 "" "$JAR" "$CSRF"
check "monitoring/history"   GET  "monitoring/history"     200 "" "$JAR" "$CSRF"
# Forbidden (monitoring team: upload + history only)
check "freemedia/list (blocked)" GET "freemedia/list"      403 "" "$JAR" "$CSRF"
check "user/list (blocked)"      GET "user/list"           403 "" "$JAR" "$CSRF"
check "belt/list (blocked)"      GET "belt/list"           403 "" "$JAR" "$CSRF"
check "task/list (blocked)"      GET "task/list"           403 "" "$JAR" "$CSRF"
echo ""

# ============================================================
# 7. AUTHORITY_REPRESENTATIVE
# ============================================================
echo "--- AUTHORITY_REPRESENTATIVE (test.authority.p2@skite.local) ---"
JAR=/tmp/skite_test_auth.txt
CSRF=$(login "test.authority.p2@skite.local" "$JAR")
[ -n "$CSRF" ] && pass "Login" || { echo "  [FAIL] Login failed — skipping role"; }

check_landing "Landing route" "authority/view" "$JAR" "$CSRF"
check "authority/view"       GET  "authority/view"         200 "" "$JAR" "$CSRF"
check "authority/summary"    GET  "authority/summary"      200 "" "$JAR" "$CSRF"
# Forbidden
check "user/list (blocked)"     GET "user/list"     403 "" "$JAR" "$CSRF"
check "belt/list (blocked)"     GET "belt/list"     403 "" "$JAR" "$CSRF"
check "upload/list (blocked)"   GET "upload/list"   403 "" "$JAR" "$CSRF"
check "task/list (blocked)"     GET "task/list"     403 "" "$JAR" "$CSRF"
echo ""

# ============================================================
# 8. SALES_TEAM
# ============================================================
echo "--- SALES_TEAM (test.sales@skite.local) ---"
JAR=/tmp/skite_test_sales.txt
CSRF=$(login "test.sales@skite.local" "$JAR")
[ -n "$CSRF" ] && pass "Login" || { echo "  [FAIL] Login failed — skipping role"; }

check_landing "Landing route" "taskprogress/list" "$JAR" "$CSRF"
check "taskprogress/list"    GET  "taskprogress/list"      200 "" "$JAR" "$CSRF"
check "request/list"         GET  "request/list"           200 "" "$JAR" "$CSRF"
check "media/client-library" GET  "media/client-library"  200 "" "$JAR" "$CSRF"
# Forbidden
check "task/list (blocked)"     GET "task/list"     403 "" "$JAR" "$CSRF"
check "user/list (blocked)"     GET "user/list"     403 "" "$JAR" "$CSRF"
check "belt/list (blocked)"     GET "belt/list"     403 "" "$JAR" "$CSRF"
echo ""

# ============================================================
# 9. CLIENT_SERVICING
# ============================================================
echo "--- CLIENT_SERVICING (test.clientservicing@skite.local) ---"
JAR=/tmp/skite_test_cs.txt
CSRF=$(login "test.clientservicing@skite.local" "$JAR")
[ -n "$CSRF" ] && pass "Login" || { echo "  [FAIL] Login failed — skipping role"; }

check_landing "Landing route" "taskprogress/list" "$JAR" "$CSRF"
check "taskprogress/list"    GET  "taskprogress/list"      200 "" "$JAR" "$CSRF"
check "request/list"         GET  "request/list"           200 "" "$JAR" "$CSRF"
check "media/client-library" GET  "media/client-library"  200 "" "$JAR" "$CSRF"
# Forbidden
check "task/list (blocked)"     GET "task/list"     403 "" "$JAR" "$CSRF"
check "user/list (blocked)"     GET "user/list"     403 "" "$JAR" "$CSRF"
check "belt/list (blocked)"     GET "belt/list"     403 "" "$JAR" "$CSRF"
echo ""

# ============================================================
# 10. MEDIA_PLANNING
# ============================================================
echo "--- MEDIA_PLANNING (test.mediaplanning@skite.local) ---"
JAR=/tmp/skite_test_mp.txt
CSRF=$(login "test.mediaplanning@skite.local" "$JAR")
[ -n "$CSRF" ] && pass "Login" || { echo "  [FAIL] Login failed — skipping role"; }

check_landing "Landing route" "taskprogress/list" "$JAR" "$CSRF"
check "taskprogress/list"    GET  "taskprogress/list"      200 "" "$JAR" "$CSRF"
check "request/list"         GET  "request/list"           200 "" "$JAR" "$CSRF"
check "media/planning-view"  GET  "media/planning-view"   200 "" "$JAR" "$CSRF"
# Forbidden
check "task/list (blocked)"     GET "task/list"     403 "" "$JAR" "$CSRF"
check "user/list (blocked)"     GET "user/list"     403 "" "$JAR" "$CSRF"
check "belt/list (blocked)"     GET "belt/list"     403 "" "$JAR" "$CSRF"
echo ""

# ============================================================
# SUMMARY
# ============================================================
echo "================================================"
echo "TOTAL: $TOTAL   PASS: $PASS   FAIL: $FAIL"
echo "================================================"
[ "$FAIL" -eq 0 ] && echo "All roles verified." || echo "Fix failing checks before testing."
