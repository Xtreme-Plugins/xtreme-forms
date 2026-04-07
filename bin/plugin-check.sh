#!/usr/bin/env bash
# Run the exact PHPCS ruleset used by the WordPress.org Plugin Check admin UI
# against the xtreme-forms plugin inside the budgetprint Docker container.
#
# Usage:
#   ./bin/plugin-check.sh           # full phpcs report (same rules as admin UI)
#   ./bin/plugin-check.sh --fix     # auto-fix all fixable issues (phpcbf)
#   ./bin/plugin-check.sh --summary # one-line-per-file summary
#
# Note: The Plugin Check admin UI also runs non-PHPCS checks (hidden files,
#       readme.txt, PrefixAllGlobals) that this script can't replicate.
#       For those, check the admin UI at:
#       https://budgetprintonline.com/wp-admin/tools.php?page=plugin-check

set -euo pipefail

PHPCS_DIR="/var/www/html/wp-content/plugins/plugin-check/vendor/squizlabs/php_codesniffer/bin"
PHPCS="php $PHPCS_DIR/phpcs"
PHPCBF="php $PHPCS_DIR/phpcbf"
RULESET="/var/www/html/wp-content/plugins/plugin-check/phpcs-rulesets/plugin-review.xml"
PLUGIN="/var/www/html/wp-content/plugins/xtreme-forms"
CONTAINER="budgetprint-fpm"

MODE="${1:-}"

run_in_docker() {
  docker exec "$CONTAINER" sh -c "$1 2>&1" 2>&1
}

if [ "$MODE" = "--fix" ]; then
  echo "Running phpcbf auto-fixer (plugin-review ruleset)..."
  run_in_docker "$PHPCBF --standard=$RULESET --extensions=php $PLUGIN" || true
  echo ""
  echo "Done. Run without --fix to see remaining issues."
  exit 0
fi

echo "Running Plugin Check PHPCS (plugin-review.xml ruleset)..."
echo ""

OUTPUT=$(run_in_docker "$PHPCS --standard=$RULESET --extensions=php $PLUGIN" || true)

if [ "$MODE" = "--summary" ]; then
  echo "$OUTPUT" | grep -E "^(FILE:|FOUND)" | sed "s|.*/xtreme-forms/||"
  echo ""
  echo "$OUTPUT" | grep -E "^(A TOTAL|Time)" | head -5
else
  echo "$OUTPUT" | sed "s|/var/www/html/wp-content/plugins/xtreme-forms/||g"
fi

# Exit with 1 if any issues found
echo "$OUTPUT" | grep -q "No violations found" && exit 0
echo "$OUTPUT" | grep -q "FOUND 0 ERRORS" || exit 1
exit 0
