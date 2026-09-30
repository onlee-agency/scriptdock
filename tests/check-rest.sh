#!/usr/bin/env bash
# REST checks that need real HTTP requests:
#  1. Logged-out requests are refused.
#  2. A snippet that dies with a fatal error in its trial run answers with a
#     JSON error, stays switched off and keeps the error.
#  3. Saving PHP that will not parse, and PHP that is fine.
#  4. A bulk switch-on that hits a fatal error says which snippets it had
#     already switched on.
#  5. A request to /snippets/<id>/... does not run snippet <id> at boot, so a
#     snippet that breaks every request can still be switched off.
# Requires the dev site with the dev-login mu-plugin. Run from the repo root.
set -u
BASE="${1:-http://localhost:8089}"
API="$BASE/wp-json/scriptdock/v1"
TMP="$(mktemp -d)"
JAR="$TMP/cookies.txt"
DEV="$(cd "$(dirname "$0")/../dev" && pwd)"
fails=0

pass() { echo "  PASS $1"; }
fail() { echo "  FAIL $1${2:+ — $2}"; fails=$((fails + 1)); }

wp() { (cd "$DEV" && docker compose run --rm cli "$@" --user=admin 2>/dev/null | grep -v '^ Container'); }

# Creates a snippet and prints its ID: title, type, location, code, active (0/1).
make() {
	wp eval "\$s = new ScriptDock\\Snippet(); \$s->fill( array( 'title' => '$1', 'type' => '$2', 'location' => '$3', 'code' => base64_decode( '$(printf '%s' "$4" | base64)' ), 'active' => (bool) $5 ) ); echo \$s->save();" | tr -d '[:space:]'
}

curl -s -c "$JAR" -b "$JAR" -o /dev/null "$BASE/wp-admin/?dev-login=1"
NONCE="$(curl -s -b "$JAR" "$BASE/wp-admin/admin-ajax.php?action=rest-nonce")"

api() { # method, path, [json body]
	curl -s -b "$JAR" -H "X-WP-Nonce: $NONCE" -H 'Content-Type: application/json' \
		-X "$1" ${3:+--data "$3"} -o "$TMP/body.json" -D "$TMP/headers.txt" -w '%{http_code}' "$API$2"
}
json() { python3 -c 'import json,sys
v = json.load(open(sys.argv[1]))
for k in sys.argv[2].split("."):
    v = v.get(k) if isinstance(v, dict) else None
print(v if isinstance(v, str) else json.dumps(v, separators=(",", ":")))' "$TMP/body.json" "$1"; }

echo "Logged out"
code=$(curl -s -o /dev/null -w '%{http_code}' "$API/snippets")
[ "$code" = "401" ] && pass "list refuses a logged-out request" || fail "list refuses a logged-out request" "$code"

echo "Fatal error in a trial run"
FATAL=$(make "REST crash test fatal" php php_everywhere 'function wp_die() {}' 0)
code=$(api POST "/snippets/$FATAL/activate")
[ "$code" = "422" ] && pass "answers 422" || fail "answers 422" "$code"
[ "$(json code)" = "scriptdock_test_fatal" ] && pass "code is scriptdock_test_fatal" || fail "code is scriptdock_test_fatal" "$(json code)"
grep -qi 'content-type: application/json' "$TMP/headers.txt" && pass "answers JSON" || fail "answers JSON"
json data.error.message | grep -qi 'redeclare' && pass "carries the error message" || fail "carries the error message" "$(json data.error.message)"
code=$(api GET "/snippets/$FATAL")
[ "$(json active)" = "false" ] && pass "stays switched off" || fail "stays switched off"
json error.message | grep -qi 'redeclare' && pass "the error is recorded" || fail "the error is recorded"

echo "Saving from the editor with code that dies"
code=$(api POST "/snippets" '{"title":"REST crash test save","type":"php","location":"php_everywhere","code":"function wp_die() {}","active":true}')
[ "$code" = "422" ] && pass "answers 422" || fail "answers 422" "$code"
[ "$(json code)" = "scriptdock_test_fatal" ] && pass "code is scriptdock_test_fatal" || fail "code is scriptdock_test_fatal" "$(json code)"
SAVED=$(json data.snippet_id)
[ "$SAVED" != "null" ] && pass "names the snippet it saved" || fail "names the snippet it saved" "$SAVED"
code=$(api GET "/snippets/$SAVED")
[ "$(json active)" = "false" ] && pass "the snippet is saved and switched off" || fail "the snippet is saved and switched off" "$(json active)"
json error.message | grep -qi 'redeclare' && pass "the error is recorded" || fail "the error is recorded" "$(json error.message)"
[ "$(json code)" = "function wp_die() {}" ] && pass "the code it saved is the code that was sent" || fail "the code it saved is the code that was sent" "$(json code)"

echo "Saving PHP that will not parse, and PHP that is fine"
# Code that will not parse is not a crash, so it saves rather than failing —
# switched off, with the line to fix. Only code that dies when tried gives 422.
code=$(api POST "/snippets" '{"title":"REST crash test syntax","type":"php","location":"php_everywhere","code":"if ( true ) {","active":true}')
[ "$code" = "201" ] && pass "a syntax error still saves" || fail "a syntax error still saves" "$code"
BROKEN=$(json item.id)
[ "$(json item.active)" = "false" ] && pass "but it is left switched off" || fail "but it is left switched off" "$(json item.active)"
[ "$(json notice.code)" = "syntax" ] && pass "and it is told so" || fail "and it is told so" "$(json notice.code)"
[ "$(json notice.error.line)" = "1" ] && pass "with the line to fix" || fail "with the line to fix" "$(json notice.error.line)"

code=$(api POST "/snippets" '{"title":"REST crash test good","type":"php","location":"php_everywhere","code":"add_filter( \"rest_crash_test_good\", \"__return_true\" );","active":true}')
[ "$code" = "201" ] && pass "valid PHP saves" || fail "valid PHP saves" "$code"
GOOD=$(json item.id)
[ "$(json item.active)" = "true" ] && pass "and comes out active" || fail "and comes out active" "$(json item.active)"

echo "Bulk switch-on that hits a fatal error"
OK=$(make "REST crash test fine" html site_footer '<!-- fine -->' 0)
code=$(api POST "/snippets/bulk" "{\"action\":\"activate\",\"ids\":[$OK,$FATAL]}")
[ "$code" = "422" ] && pass "answers 422" || fail "answers 422" "$code"
[ "$(json data.done)" = "[$OK]" ] && pass "lists what it had switched on" || fail "lists what it had switched on" "$(json data.done)"
[ "$(json data.snippet_id)" = "$FATAL" ] && pass "names the snippet that died" || fail "names the snippet that died" "$(json data.snippet_id)"

echo "A snippet is held back for its own requests"
MARK=$(make "REST crash test marker" php php_everywhere "header( 'X-SD-Boot-Marker: ran' );" 1)
curl -s -o /dev/null "$BASE/" # Let the runtime cache rebuild.
api GET "/tags" >/dev/null
grep -qi 'x-sd-boot-marker' "$TMP/headers.txt" && pass "runs for other requests" || fail "runs for other requests"
api GET "/snippets/$MARK" >/dev/null
grep -qi 'x-sd-boot-marker' "$TMP/headers.txt" && fail "not run for /snippets/$MARK" || pass "not run for /snippets/$MARK"
curl -s -b "$JAR" -H "X-WP-Nonce: $NONCE" -D "$TMP/headers.txt" -o /dev/null "$BASE/?rest_route=/scriptdock/v1/snippets/$MARK"
grep -qi 'x-sd-boot-marker' "$TMP/headers.txt" && fail "held back with ?rest_route= too" || pass "held back with ?rest_route= too"
api POST "/snippets/$MARK/deactivate" >/dev/null
[ "$(json item.active)" = "false" ] && pass "and can be switched off" || fail "and can be switched off"

echo "Clean up"
wp eval "foreach ( array( $FATAL, $OK, $MARK, $SAVED, $BROKEN, $GOOD ) as \$id ) { wp_delete_post( \$id, true ); } ScriptDock\\Compiler::rebuild(); echo 'done';" >/dev/null
rm -rf "$TMP"

[ "$fails" = "0" ] && echo "ALL PASSED" || echo "FAILED: $fails"
