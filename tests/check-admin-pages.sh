#!/usr/bin/env bash
# Logs into the local dev site and loads every ScriptDock admin screen,
# reporting HTTP status and any PHP errors printed in the page.
# Usage: tests/check-admin-pages.sh [base-url]
set -u
BASE="${1:-http://localhost:8089}"
TMP="$(mktemp -d)"
JAR="$TMP/cookies.txt"

curl -s -c "$JAR" -b "$JAR" -o /dev/null "$BASE/wp-login.php"
curl -s -c "$JAR" -b "$JAR" -o /dev/null \
	--data-urlencode "log=admin" --data-urlencode "pwd=admin" \
	--data-urlencode "wp-submit=Log In" --data-urlencode "testcookie=1" \
	--data-urlencode "redirect_to=$BASE/wp-admin/" \
	"$BASE/wp-login.php"

PAGES=(
	"admin.php?page=scriptdock"
	"admin.php?page=scriptdock-snippets"
	"admin.php?page=scriptdock-snippets&view=trash"
	"admin.php?page=scriptdock-snippets&view=review"
	"admin.php?page=scriptdock-snippets&tags=manage"
	"admin.php?page=scriptdock-edit"
	"admin.php?page=scriptdock-edit&type=php"
	"admin.php?page=scriptdock-global"
	"admin.php?page=scriptdock-library"
	"admin.php?page=scriptdock-files"
	"admin.php?page=scriptdock-tools"
	"admin.php?page=scriptdock-settings"
	"admin.php?page=scriptdock-setup"
	"post-new.php?post_type=page"
	"site-health.php?tab=debug"
	"plugins.php"
)
if [ -n "${EXTRA_PAGES:-}" ]; then
	# shellcheck disable=SC2206
	PAGES+=( $EXTRA_PAGES )
fi

status=0
for page in "${PAGES[@]}"; do
	file="$TMP/page.html"
	code=$(curl -s -b "$JAR" -o "$file" -w '%{http_code}' "$BASE/wp-admin/$page")
	errors=$(grep -o -i -E '<b>Fatal error</b>|critical error on this website|<b>Warning</b>|<b>Notice</b>|<b>Deprecated</b>' "$file" | sort -u | tr '\n' ' ')
	printf '%-72s %s %s\n' "$page" "$code" "$errors"
	if [ "$code" != "200" ] || [ -n "$errors" ]; then
		status=1
	fi
done
# WordPress's own list and tag screens for snippets redirect to the Snippets screen.
REDIRECTS=(
	"edit.php?post_type=scriptdock_snippet|admin.php?page=scriptdock-snippets"
	"edit.php?post_type=scriptdock_snippet&post_status=trash|admin.php?page=scriptdock-snippets&view=trash"
	"edit-tags.php?taxonomy=scriptdock_tag&post_type=scriptdock_snippet|admin.php?page=scriptdock-snippets&tags=manage"
)
for pair in "${REDIRECTS[@]}"; do
	from="${pair%%|*}"
	to="${pair#*|}"
	target=$(curl -s -b "$JAR" -o /dev/null -w '%{redirect_url}' "$BASE/wp-admin/$from")
	if [ "$target" = "$BASE/wp-admin/$to" ]; then
		printf '%-72s -> %s\n' "$from" "$to"
	else
		printf '%-72s -> %s (expected %s)\n' "$from" "$target" "$to"
		status=1
	fi
done

DEV="$(cd "$(dirname "$0")/../dev" && pwd)"

# The sidebar says where you are: a snippet being edited sits under All
# Snippets, a new one under Add Snippet.
id=$(cd "$DEV" && docker compose run --rm -T cli post list --post_type=scriptdock_snippet \
	--post_status=any --posts_per_page=1 --field=ID 2>/dev/null | tr -dc '0-9')
for pair in "admin.php?page=scriptdock-edit&snippet=$id|All Snippets" "admin.php?page=scriptdock-edit|Add Snippet"; do
	page="${pair%%|*}"
	want="${pair#*|}"
	got=$(curl -s -b "$JAR" "$BASE/wp-admin/$page" | grep -o 'class="current" aria-current="page">[^<]*' | head -1 | sed 's/.*>//')
	if [ "$got" = "$want" ]; then
		printf '%-72s %s\n' "menu: $page" "$got"
	else
		printf '%-72s %s (expected %s)\n' "menu: $page" "${got:-nothing}" "$want"
		status=1
	fi
done

# Someone without ScriptDock rights gets our page, not WordPress's bare wall
# — and WordPress's wall is left alone everywhere else. The editor is made
# here and removed afterwards, so the check needs nothing set up first.
(cd "$DEV" && docker compose run --rm cli user create sd-check-editor \
	sd-check-editor@example.com --role=editor --porcelain >/dev/null 2>&1)
EJAR="$TMP/editor.txt"
curl -s -c "$EJAR" -b "$EJAR" -o /dev/null "$BASE/wp-admin/?dev-login=sd-check-editor"
body=$(curl -s -b "$EJAR" "$BASE/wp-admin/admin.php?page=scriptdock-snippets")
code=$(curl -s -b "$EJAR" -o /dev/null -w '%{http_code}' "$BASE/wp-admin/admin.php?page=scriptdock-snippets")
if echo "$body" | grep -q "You do not have access to this" && [ "$code" = "403" ]; then
	printf '%-72s %s\n' "no access (ScriptDock page, as an editor)" "403 ours"
else
	printf '%-72s %s\n' "no access (ScriptDock page, as an editor)" "$code UNEXPECTED"
	status=1
fi
other=$(curl -s -b "$EJAR" "$BASE/wp-admin/plugins.php")
if echo "$other" | grep -q "Sorry, you are not allowed"; then
	printf '%-72s %s\n' "no access (a WordPress page, as an editor)" "WordPress's own"
else
	printf '%-72s %s\n' "no access (a WordPress page, as an editor)" "UNEXPECTED"
	status=1
fi

(cd "$DEV" && docker compose run --rm cli user delete sd-check-editor --yes >/dev/null 2>&1)

rm -rf "$TMP"
exit $status
