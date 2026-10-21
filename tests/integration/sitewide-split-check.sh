#!/usr/bin/env bash
# Staging check for the site-wide design split.
# Does not deploy anything. Point STAGING_URL at staging only.
#
# STAGING_URL=https://example.com \
#   HOME_PATH=/ SERVICE_PATH=/services/ PRODUCT_PATH=/product/example/ \
#   CONTACT_PATH=/contact/ UNMAPPED_PATH=/about/ VARIANT_PATH=/redesign-home/ \
#   bash tests/integration/sitewide-split-check.sh
set -euo pipefail

BASE="${STAGING_URL:-}"
if [[ -z "${BASE}" ]]; then
	echo "Set STAGING_URL to the staging origin." >&2
	exit 1
fi
BASE="${BASE%/}"
KEY="${EXPERIMENT_KEY:-rwgo_sitewide}"
WEIGHT="${CONTROL_WEIGHT:-0.5}"
HOME_PATH="${HOME_PATH:-/}"
SERVICE_PATH="${SERVICE_PATH:-/services/}"
PRODUCT_PATH="${PRODUCT_PATH:-/product/}"
CONTACT_PATH="${CONTACT_PATH:-/contact/}"
UNMAPPED_PATH="${UNMAPPED_PATH:-/about/}"
VARIANT_PATH="${VARIANT_PATH:-}"

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

mint() {
	RWGO_WANT="$1" RWGO_KEY="$KEY" RWGO_WEIGHT="$WEIGHT" php "${ROOT}/tests/integration/mint-sitewide-cookie.php"
}

fetch() {
	local path="$1"
	local cookie="${2:-}"
	local ua="${3:-Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36}"
	local args=(-sS -D - -o /dev/null -A "$ua" --max-redirs 0)
	if [[ -n "$cookie" ]]; then
		args+=(-H "Cookie: rwgo_site=$cookie")
	fi
	curl "${args[@]}" "${BASE}${path}"
}

assert_uncacheable() {
	local headers="$1"
	echo "$headers" | grep -Eiq 'cache-control:.*no-store|x-litespeed-cache-control:.*no-cache'
}

echo "1. No cookie on ${HOME_PATH}"
H1="$(fetch "$HOME_PATH")"
assert_uncacheable "$H1"
echo "$H1" | grep -Eiq 'set-cookie:.*rwgo_site='
echo "$H1" | grep -Eiq 'x-rwgo-arm: (control|variant)'

COOKIE_B="$(mint variant)"
COOKIE_A="$(mint control)"

check_arm() {
	local path="$1"
	local cookie="$2"
	local expect="$3"
	local headers
	headers="$(fetch "$path" "$cookie")"
	echo "$headers" | grep -Eiq "x-rwgo-arm: ${expect}"
	if echo "$headers" | grep -Eiq '^x-litespeed-cache:'; then
		echo "$headers" | grep -Eiq '^x-litespeed-cache: hit' || echo "LiteSpeed miss on ${path} (${expect}) — repeat the run after a warm-up."
	fi
}

echo "2. Variant arm"
check_arm "$HOME_PATH" "$COOKIE_B" "variant"
check_arm "$SERVICE_PATH" "$COOKIE_B" "variant"
check_arm "$PRODUCT_PATH" "$COOKIE_B" "variant"
check_arm "$CONTACT_PATH" "$COOKIE_B" "variant"
UNMAPPED="$(fetch "$UNMAPPED_PATH" "$COOKIE_B")"
if echo "$UNMAPPED" | grep -Eiq '^x-rwgo-arm: variant'; then
	echo "Unmapped ${UNMAPPED_PATH} was served as the variant arm." >&2
	exit 1
fi

echo "3. Control arm"
check_arm "$HOME_PATH" "$COOKIE_A" "control"
check_arm "$SERVICE_PATH" "$COOKIE_A" "control"
check_arm "$PRODUCT_PATH" "$COOKIE_A" "control"
check_arm "$CONTACT_PATH" "$COOKIE_A" "control"
if [[ -n "$VARIANT_PATH" ]]; then
	REDIR="$(fetch "$VARIANT_PATH" "$COOKIE_A")"
	echo "$REDIR" | grep -Eiq '^HTTP/.* 302'
	echo "$REDIR" | grep -Eiq '^location:'
	assert_uncacheable "$REDIR"
fi

echo "4. Repeat"
check_arm "$HOME_PATH" "$COOKIE_B" "variant"
check_arm "$HOME_PATH" "$COOKIE_A" "control"

echo "5. Googlebot"
BOT="$(fetch "$HOME_PATH" "" "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)")"
echo "$BOT" | grep -Eiq 'x-rwgo-arm: control'
if echo "$BOT" | grep -Eiq 'set-cookie:.*rwgo_site='; then
	echo "Googlebot received an assignment cookie." >&2
	exit 1
fi

echo "Site-wide split check passed."
