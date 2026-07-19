#!/usr/bin/env bash
#
# Build a clean, WordPress.org-ready distribution zip of the plugin.
#
# The archive contains a single top-level folder named after the plugin slug
# (6arshid-social-community/) and EXCLUDES all dev-only files:
#   build/, .git, .github, node_modules, vendor, tests, phpcs.xml, README.md,
#   composer/npm manifests, editor config, and the .distignore/.gitattributes
#   files themselves.
#
# Two build strategies are supported:
#   1. git archive  — preferred; respects .gitattributes `export-ignore` and only
#      ships committed files. Used automatically when run inside a git work tree.
#   2. rsync + zip  — fallback for a non-git checkout; respects .distignore.
#
# Usage:  bash bin/make-dist.sh
#
set -euo pipefail

SLUG="6arshid-social-community"

# Resolve the project root (parent of this bin/ directory).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

# Read the version from readme.txt (Stable tag) so the zip is versioned.
VERSION="$(grep -m1 -E '^Stable tag:' readme.txt | sed -E 's/^Stable tag:[[:space:]]*//' | tr -d '\r' || true)"
[ -n "$VERSION" ] || VERSION="dev"

OUT="${ROOT_DIR}/${SLUG}-${VERSION}.zip"
rm -f "$OUT"

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
	# Strategy 1: package the current WORKING TREE (committed + uncommitted
	# changes) via git, honoring .gitattributes export-ignore.
	#
	# A throwaway temp index snapshots every working-tree file (so uncommitted
	# edits and new files are included) without touching the real index or HEAD;
	# --worktree-attributes applies the working-tree .gitattributes export rules
	# even before they are committed.
	TMP_IDX_DIR="$(mktemp -d)"
	TMP_INDEX="$TMP_IDX_DIR/index"   # must NOT pre-exist; git creates it.
	trap 'rm -rf "$TMP_IDX_DIR"' EXIT
	# -c core.autocrlf=false / safecrlf=false silences the noisy per-file
	# "LF will be replaced by CRLF" warnings on Windows checkouts.
	GIT_INDEX_FILE="$TMP_INDEX" git -c core.autocrlf=false -c core.safecrlf=false add -A
	TREE="$( GIT_INDEX_FILE="$TMP_INDEX" git write-tree )"
	git archive --worktree-attributes --format=zip --prefix="${SLUG}/" -o "$OUT" "$TREE"
	echo "Created ${OUT}"
	echo "  (packaged the WORKING TREE via git, honoring .gitattributes export-ignore)"
else
	# Strategy 2: rsync a clean copy honoring .distignore, then zip it.
	command -v rsync >/dev/null 2>&1 || { echo "rsync is required for the non-git build path." >&2; exit 1; }
	command -v zip   >/dev/null 2>&1 || { echo "zip is required for the non-git build path." >&2; exit 1; }

	STAGE="$(mktemp -d)"
	trap 'rm -rf "$STAGE"' EXIT
	mkdir -p "${STAGE}/${SLUG}"

	# Build rsync excludes from .distignore (strip comments/blank lines).
	EXCLUDES=()
	if [ -f .distignore ]; then
		while IFS= read -r line; do
			line="${line%%#*}"
			line="$(echo "$line" | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//')"
			[ -n "$line" ] && EXCLUDES+=( "--exclude=$line" )
		done < .distignore
	fi

	rsync -a "${EXCLUDES[@]}" ./ "${STAGE}/${SLUG}/"
	( cd "$STAGE" && zip -rq "$OUT" "$SLUG" )
	echo "Created ${OUT}"
	echo "  (via 'rsync + zip', honoring .distignore)"
fi
