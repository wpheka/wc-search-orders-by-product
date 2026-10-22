#!/usr/bin/env bash
#
# Laptop only: the same steps as run.sh, against the Search Orders Pro wp-env store
# (http://localhost:8892), which mounts this plugin. run.sh drives WordPress through a local
# WP-CLI at WP_PATH; here every WP-CLI call goes into the wp-env cli container instead.
#
#   NODE_PATH=C:/path/to/node_modules bash tests/functional/lap-run-wpenv.sh
#
# What is adapted, and nothing else:
# - `wp --path=WP_PATH ...` becomes `docker exec <cli container> wp ...`, with SOBP_STATE passed
#   in. The state folder lives in the store's wp-content/uploads, which the laptop and the
#   container both see (wp-env mounts its WordPress folder).
# - admin.js runs with `node -r lap-wpenv-shim.cjs`, which sends its `wp` calls to the same
#   container and uses the installed Chrome.
# - Search Orders Pro is deactivated for the run and activated again at the end.
# The order of steps, the files run and the result counting are run.sh's.
set -uo pipefail
export MSYS_NO_PATHCONV=1

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLI="${LAP_CLI:-$(docker ps --format '{{.Names}}' | grep -- 'wc-search-orders-by-product-pro-.*-cli-1$' | grep -v tests | head -1)}"
[ -n "$CLI" ] || { echo "wp-env cli container for Search Orders Pro is not running (npx @wordpress/env@11 start in the Pro folder)"; exit 2; }
WP_HOST_ROOT="$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html"}}{{.Source}}{{end}}{{end}}' "$CLI" | tr '\\' '/')"
[ -n "$WP_HOST_ROOT" ] || { echo "cannot find the store's WordPress folder"; exit 2; }
STATE_NAME="lap-sobp-state-$$"
STATE="$WP_HOST_ROOT/wp-content/uploads/$STATE_NAME"
CSTATE="/var/www/html/wp-content/uploads/$STATE_NAME"
mkdir -p "$STATE"
PLUGINS_HOST="$(cygpath -m "$(cd "$HERE/../../.." && pwd)")"
export SOBP_STATE="$(cygpath -m "$STATE")" LAP_CLI="$CLI" LAP_PLUGINS_HOST="$PLUGINS_HOST" LAP_STATE_CONTAINER="$CSTATE"
[ -n "${NODE_PATH:-}" ] || { echo "set NODE_PATH to a node_modules with playwright (Windows path)"; exit 2; }

CDIR="/var/www/html/wp-content/plugins/wc-search-orders-by-product/tests/functional"
wpx() { docker exec -e SOBP_STATE="$CSTATE" -w /var/www/html "$CLI" wp "$@"; }
wpe() { wpx eval-file "$CDIR/$(basename "$1")" "${@:2}" 2>&1 | grep -v -e 'Deprecated' -e '^$' -e 'Undefined array key 1'; }
# Node on Windows needs C:/ paths (MSYS_NO_PATHCONV stops Git Bash converting them).
node_admin() { node -r "$(cygpath -m "$HERE/lap-wpenv-shim.cjs")" "$(cygpath -m "$HERE/admin.js")" "$@"; }

PRO_WAS=$(wpx plugin get wc-search-orders-by-product-pro --field=status 2>/dev/null | tail -1)
[ "$PRO_WAS" = "active" ] && wpx plugin deactivate wc-search-orders-by-product-pro >/dev/null 2>&1
HPOS_BEFORE=$(wpx option get woocommerce_custom_orders_table_enabled 2>/dev/null | tail -1)
cleanup() {
  wpx option update woocommerce_custom_orders_table_enabled "$HPOS_BEFORE" >/dev/null 2>&1
  wpe "$HERE/cleanup.php" | sed 's/^/  /'
  [ "$PRO_WAS" = "active" ] && wpx plugin activate wc-search-orders-by-product-pro >/dev/null 2>&1
  echo "  Search Orders Pro: $(wpx plugin get wc-search-orders-by-product-pro --field=status 2>/dev/null | tail -1) (was $PRO_WAS)"
  rm -rf "$STATE"
}
CLEANED=0
on_exit() { [ "$CLEANED" = 1 ] && return; CLEANED=1; cleanup; }
trap on_exit EXIT
trap 'on_exit; exit 130' INT TERM HUP

OFFSET=$(docker exec "$CLI" sh -c 'wc -c < /var/www/html/wp-content/debug.log 2>/dev/null || echo 0' | tr -d '\r')

echo "== setup";       wpe "$HERE/setup.php" | sed 's/^/  /'
echo "== HPOS";        node_admin hpos || echo "FAIL|hpos: browser script exited with an error (cases after the failure did not run)" >> "$STATE/results"
                       wpe "$HERE/checks.php" | sed 's/^/  /'
if [ "$HPOS_BEFORE" = "yes" ]; then
  echo "== legacy storage"
  wpx option update woocommerce_custom_orders_table_enabled no >/dev/null 2>&1
  node_admin legacy || echo "FAIL|legacy: browser script exited with an error (cases after the failure did not run)" >> "$STATE/results"
  wpe "$HERE/checks.php" | sed 's/^/  /'
  wpx option update woocommerce_custom_orders_table_enabled yes >/dev/null 2>&1
  BACK=$(wpx option get woocommerce_custom_orders_table_enabled 2>/dev/null | tail -1)
  [ "$BACK" = "yes" ] && echo "PASS|order storage switched back to HPOS" >> "$STATE/results" || echo "FAIL|order storage switched back to HPOS -- option is $BACK" >> "$STATE/results"
fi

NEW=$(docker exec "$CLI" sh -c "tail -c +$((OFFSET + 1)) /var/www/html/wp-content/debug.log 2>/dev/null" | grep -v 'PHP Deprecated' | grep 'wc-search-orders-by-product' || true)
if [ -n "$NEW" ]; then echo "$NEW" | cut -c1-300; echo "FAIL|debug.log clean -- new errors above" >> "$STATE/results"; else echo "PASS|debug.log clean" >> "$STATE/results"; fi

echo; echo "== results"
PASS=$(grep -c '^PASS|' "$STATE/results"); FAIL=$(grep -c '^FAIL|' "$STATE/results")
sed 's/^\([A-Z]*\)|/\1  /' "$STATE/results"
echo; echo "$PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
