#!/usr/bin/env bash
#
# Upload the portal to the LAMP server, mirroring the workflow used by the
# existing LinkTec site (rsync over SSH, credentials in .vscode/sftp.json).
#
#   ./scripts/deploy-remote.sh              # upload
#   SYNC_DRY_RUN=1 ./scripts/deploy-remote.sh   # preview only
#   SYNC_DELETE=1 ./scripts/deploy-remote.sh    # also remove remote extras
#   ./scripts/deploy-remote.sh ssh "ls -la"     # run a command on the server
#
# The SFTP password is read from .vscode/sftp.json, or from the macOS Keychain
# when the config does not carry one. Keep .vscode/sftp.json local; it is
# ignored by Git.
#
# The production environment file lives in .deploy/env (also ignored by Git) and
# is uploaded as .env. Keeping it out of the working tree means a local preview
# never picks up the production base URL.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

cd "$ROOT_DIR"

fail() {
    printf 'ERROR: %s\n' "$1" >&2
    exit 1
}

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
    for candidate in /opt/homebrew/bin/php /usr/local/bin/php /usr/bin/php; do
        if [ -x "$candidate" ]; then
            PHP_BIN="$candidate"
            break
        fi
    done
fi

command -v "$PHP_BIN" >/dev/null 2>&1 || fail "PHP is required to read the SFTP config."
command -v rsync >/dev/null 2>&1 || fail "rsync is required."
command -v ssh >/dev/null 2>&1 || fail "ssh is required."

CONFIG_FILE="${SFTP_CONFIG:-$ROOT_DIR/.vscode/sftp.json}"
[ -f "$CONFIG_FILE" ] || fail "No SFTP config at $CONFIG_FILE. Copy .vscode/sftp.example.json and fill it in."

json_value() {
    "$PHP_BIN" -r '
        $data = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($data)) { exit(2); }
        if (isset($data[0]) && is_array($data[0])) { $data = $data[0]; }
        if (!isset($data[$argv[2]]) || $data[$argv[2]] === "") { exit(3); }
        echo $data[$argv[2]];
    ' "$CONFIG_FILE" "$1"
}

host="$(json_value host)" || fail "Missing host in $CONFIG_FILE."
username="$(json_value username)" || fail "Missing username in $CONFIG_FILE."
remote_path="$(json_value remotePath)" || fail "Missing remotePath in $CONFIG_FILE."
port="$(json_value port 2>/dev/null || printf '22')"
password="${SFTP_PASSWORD:-$(json_value password 2>/dev/null || true)}"

if [ -z "$password" ] && command -v security >/dev/null 2>&1; then
    password="$(security find-generic-password -a "$username@$host:$port" -s com.linktec.hydraulic.sftp -w 2>/dev/null || true)"
fi
[ -n "$password" ] || fail "No SFTP password found in $CONFIG_FILE or the Keychain."

askpass_file="$(mktemp "${TMPDIR:-/tmp}/nfc-ssh-askpass.XXXXXX")"
cleanup() {
    rm -f "$askpass_file"
    unset SFTP_PASSWORD SSH_ASKPASS SSH_ASKPASS_REQUIRE
}
trap cleanup EXIT

{
    printf '%s\n' '#!/usr/bin/env sh'
    printf '%s\n' 'printf "%s\n" "$SFTP_PASSWORD"'
} > "$askpass_file"
chmod 700 "$askpass_file"
export SFTP_PASSWORD="$password"
export SSH_ASKPASS="$askpass_file"
export SSH_ASKPASS_REQUIRE=force
export DISPLAY="${DISPLAY:-:0}"

exclude_args=(
    --exclude ".git"
    --exclude ".git/***"
    --exclude ".vscode"
    --exclude ".DS_Store"
    --exclude "tests/***"
    --exclude "docker/***"
    --exclude "logs/***"
    --exclude ".env.example"
    --exclude ".env"
    --exclude ".deploy"
    --exclude ".deploy/***"
    --exclude "storage/uploads/***"
    --exclude "scripts/screenshot.mjs"
    --exclude "scripts/ui-check.mjs"
    --exclude "AGENTS.md"
)

delete_args=()
if [ "${SYNC_DELETE:-0}" = "1" ]; then
    delete_args+=(--delete)
fi

dry_run_args=()
if [ "${SYNC_DRY_RUN:-0}" = "1" ]; then
    dry_run_args+=(--dry-run)
fi

printf 'Uploading to %s@%s:%s\n' "$username" "$host" "$remote_path"

if [ "${1:-}" = "ssh" ]; then
    shift
    exec ssh -p "$port" -o StrictHostKeyChecking=accept-new -o NumberOfPasswordPrompts=1 \
        "$username@$host" "$@"
fi

ssh -p "$port" -o StrictHostKeyChecking=accept-new -o NumberOfPasswordPrompts=1 \
    "$username@$host" "mkdir -p '$remote_path'"

rsync -az --human-readable --progress \
    ${dry_run_args[@]+"${dry_run_args[@]}"} \
    ${delete_args[@]+"${delete_args[@]}"} \
    "${exclude_args[@]}" \
    -e "ssh -p $port -o StrictHostKeyChecking=accept-new -o NumberOfPasswordPrompts=1" \
    "$ROOT_DIR/" "$username@$host:$remote_path/"

# Upload the environment file last, so it always lands as <remote>/.env.
if [ -f "$ROOT_DIR/.deploy/env" ]; then
    rsync -az --human-readable \
        -e "ssh -p $port -o StrictHostKeyChecking=accept-new -o NumberOfPasswordPrompts=1" \
        "$ROOT_DIR/.deploy/env" "$username@$host:$remote_path/.env"
    chmod 640 "$ROOT_DIR/.deploy/env" 2>/dev/null || true
else
    printf 'WARNING: .deploy/env is missing; the server keeps its current .env.\n'
fi

printf 'Upload complete.\n'

# Reminder: the URL is defined by PORTAL_BASE_URL in .deploy/env.
if [ -f "$ROOT_DIR/.deploy/env" ]; then
    printf 'Configured base URL: %s\n' "$(grep -E '^PORTAL_BASE_URL=' "$ROOT_DIR/.deploy/env" | cut -d= -f2- || true)"
fi
