#!/usr/bin/env bash
# Front-end output checks. The fixtures are made here, so the scheduled ones
# are always inside their window. Run from the repo root.
set -u
BASE="${1:-http://localhost:8089}"
TMP="$(mktemp -d)"
DEV="$(cd "$(dirname "$0")/../dev" && pwd)"
fails=0

echo "Fixtures"
FIXTURES="$( (cd "$DEV" && docker compose run --rm cli eval-file /tests/features.php --user=admin 2>/dev/null) | grep -v '^ Container' )"
echo "$FIXTURES" | grep -E 'FAIL|Fixtures from earlier runs' || true
POST="${POST_ID:-$(echo "$FIXTURES" | sed -n 's/.*Smoke post is \([0-9]*\).*/\1/p' | tail -1)}"
if [ -z "$POST" ]; then
	echo "  FAIL could not make the fixtures"
	exit 1
fi

expect() { # label, file, pattern, expected count (0 = absent, 1 = present)
	local n
	n=$(grep -c -- "$3" "$2")
	if { [ "$4" = "0" ] && [ "$n" = "0" ]; } || { [ "$4" != "0" ] && [ "$n" != "0" ]; }; then
		echo "  PASS $1"
	else
		echo "  FAIL $1 (found $n)"
		fails=$((fails + 1))
	fi
}

echo "Virtual files"
curl -sL "$BASE/ads.txt" > "$TMP/ads.txt";  expect "ads.txt served" "$TMP/ads.txt" "pub-123" 1
curl -sL -D "$TMP/h" "$BASE/llms.txt" > "$TMP/llms.txt"; expect "llms.txt served" "$TMP/llms.txt" "# Site" 1
expect "llms.txt is text/plain" "$TMP/h" "text/plain" 1
curl -sL "$BASE/.well-known/security.txt" > "$TMP/sec.txt"; expect "security.txt served" "$TMP/sec.txt" "Contact: mailto" 1
curl -sL "$BASE/robots.txt" > "$TMP/robots.txt"; expect "robots.txt keeps WordPress rules" "$TMP/robots.txt" "User-agent: \*" 1
expect "robots.txt has appended rules" "$TMP/robots.txt" "GPTBot" 1

echo "Visitor view of post $POST"
curl -sL "$BASE/?p=$POST" > "$TMP/post.html"
expect "global head with smart tag" "$TMP/post.html" 'sd-global-head" content="ScriptDock Dev' 1
expect "global footer" "$TMP/post.html" "sd-global-footer" 1
expect "scheduled (now) runs" "$TMP/post.html" "sd-scheduled-now" 1
expect "scheduled (future) hidden" "$TMP/post.html" "sd-scheduled-future" 0
expect "scheduled (expired) hidden" "$TMP/post.html" "sd-scheduled-expired" 0
expect "test mode hidden from visitors" "$TMP/post.html" "sd-test-mode" 0
expect "page head code with post ID" "$TMP/post.html" "sd-page-head\" content=\"$POST\"" 1
expect "page CSS" "$TMP/post.html" "scriptdock-page-css" 1
expect "page JS with smart tag" "$TMP/post.html" 'window.sdPageJs = "Smoke post"' 1
expect "page before-content HTML" "$TMP/post.html" "sd-page-before" 1
expect "snippet switched off on this page" "$TMP/post.html" "sd-front-only" 0
expect "interaction-delayed JS is held" "$TMP/post.html" 'data-scriptdock-delay="interaction"' 1
expect "idle JS is held" "$TMP/post.html" 'data-scriptdock-delay="idle"' 1
expect "loader enqueued" "$TMP/post.html" "scriptdock/assets/js/loader.js" 1

echo "Admin view of post $POST"
JAR="$TMP/jar"
curl -s -c "$JAR" -b "$JAR" -o /dev/null "$BASE/wp-admin/?dev-login=1"
curl -sL -b "$JAR" "$BASE/?p=$POST" > "$TMP/admin.html"
expect "test mode visible to admins" "$TMP/admin.html" "sd-test-mode" 1
expect "admin bar inspector" "$TMP/admin.html" "wp-admin-bar-scriptdock" 1

rm -rf "$TMP"
[ "$fails" = "0" ] && echo "ALL PASSED" || { echo "FAILED: $fails"; exit 1; }
