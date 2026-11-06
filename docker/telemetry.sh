#!/bin/bash
#
# Sends one anonymous usage report to the Investbrain maintainers. The entrypoint runs this each
# time the container starts. See docs/self-hosting.md#telemetry for what's included.
#
#   bash docker/telemetry.sh          send the report
#   bash docker/telemetry.sh --show   print the report without sending it
#
# Turn it off with TELEMETRY_ENABLED=false. Nothing is sent when SELF_HOSTED=false.
# It never fails: a report that can't be sent mustn't stop Investbrain from starting.

cd "$(dirname "$0")/.." || exit 0

# A setting from the environment, or from .env when it isn't set there
setting() {
    local key=$1 default=$2 value

    if [ -n "${!key+x}" ]; then
        value=${!key}
    elif [ -f .env ]; then
        value=$(grep -E "^${key}=" .env | tail -n 1 | cut -d= -f2- | sed -e 's/^["'\'']//' -e 's/["'\'']$//')
    fi

    echo "${value:-$default}"
}

is_true() {
    case "${1,,}" in
        true | 1 | yes | on | "(true)") return 0 ;;
        *) return 1 ;;
    esac
}

bool() {
    is_true "$1" && echo true || echo false
}

# A JSON string, without control characters
json_string() {
    local value
    value=$(printf '%s' "$1" | tr -d '\000-\037')
    value=${value//\\/\\\\}
    value=${value//\"/\\\"}
    printf '"%s"' "$value"
}

# A JSON list from comma-separated values, keeping only the allowed ones and reporting the rest as the fallback
json_list() {
    local values=$1 allowed=$2 fallback=$3 item items=()

    IFS=',' read -ra parts <<< "$values"
    for item in "${parts[@]}"; do
        item=$(echo "$item" | tr -d '[:space:]')
        [ -z "$item" ] && continue

        if [[ " $allowed " == *" $item "* ]]; then
            items+=("$(json_string "$item")")
        elif [ -n "$fallback" ]; then
            items+=("$(json_string "$fallback")")
        fi
    done

    local IFS=','
    printf '[%s]' "${items[*]}"
}

if ! is_true "$(setting SELF_HOSTED true)"; then
    exit 0
fi

if [ "$1" != "--show" ] && ! is_true "$(setting TELEMETRY_ENABLED true)"; then
    echo "Telemetry is turned off."
    exit 0
fi

# A random ID kept in the storage volume, so restarts count as the same instance
instance_file=${TELEMETRY_INSTANCE_FILE:-storage/app/.instance-id}
instance_id=$(cat "$instance_file" 2> /dev/null | tr -d '[:space:]')

if ! [[ $instance_id =~ ^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$ ]]; then
    instance_id=$(cat /proc/sys/kernel/random/uuid 2> /dev/null || uuidgen | tr '[:upper:]' '[:lower:]')
    mkdir -p "$(dirname "$instance_file")"
    echo "$instance_id" > "$instance_file"
fi

ai_chat=$(bool "$(setting AI_CHAT_ENABLED false)")
ai_provider=null
if [ "$ai_chat" = true ]; then
    ai_provider=$(json_string "$(setting CHAT_PROVIDER openai)")
fi

# Aggregate counts from the database. Left out if the database can't be read.
stats=$(php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $users = fn () => Illuminate\Support\Facades\DB::table("users");
    $options = $users()->pluck("options")->map(fn ($options) => (array) (is_string($options) ? json_decode($options, true) : $options));
    $tally = fn ($values) => (object) $values
        ->map(fn ($value) => substr((string) $value, 0, 10))
        ->countBy()->sortDesc()->take(20)->all();

    echo json_encode([
        "users" => $users()->count(),
        "active_users_30d" => $users()->where("last_seen_at", ">=", now()->subDays(30))->count(),
        "two_factor_users" => $users()->whereNotNull("two_factor_confirmed_at")->count(),
        "portfolios" => Illuminate\Support\Facades\DB::table("portfolios")->count(),
        "transactions" => Illuminate\Support\Facades\DB::table("transactions")->count(),
        "open_holdings" => Illuminate\Support\Facades\DB::table("holdings")->where("quantity", ">", 0)->count(),
        "symbols" => Illuminate\Support\Facades\DB::table("market_data")->count(),
        "locales" => $tally($options->map(fn (array $option) => $option["locale"] ?? config("app.locale"))),
        "display_currencies" => $tally($options->map(fn (array $option) => strtoupper((string) ($option["display_currency"] ?? config("investbrain.base_currency"))))),
    ]);
' 2> /dev/null) || stats=""

[[ $stats == \{* ]] || stats="{}"

payload=$(cat << JSON
{
    "schema": 1,
    "event": "started",
    "instance_id": $(json_string "$instance_id"),
    "version": $(json_string "$(setting VERSION dev)"),
    "environment": {
        "php": $(json_string "$(php -r 'echo PHP_VERSION;' 2> /dev/null)"),
        "os": $(json_string "$(uname -s)"),
        "arch": $(json_string "$(uname -m)"),
        "database": $(json_string "$(setting DB_CONNECTION sqlite)"),
        "docker": $([ -f /.dockerenv ] && echo true || echo false),
        "timezone": $(json_string "$(setting APP_TIMEZONE UTC)")
    },
    "features": {
        "market_data_providers": $(json_list "$(setting MARKET_DATA_PROVIDER yahoo)" "yahoo alphavantage alpaca finnhub twelvedata fake" custom),
        "base_currency": $(json_string "$(setting BASE_CURRENCY USD)"),
        "registration": $(bool "$(setting REGISTRATION_ENABLED true)"),
        "login_providers": $(json_list "$(setting ENABLED_LOGIN_PROVIDERS '')" "github google facebook linkedin-openid"),
        "ai_chat": $ai_chat,
        "ai_provider": $ai_provider,
        "mcp_server": $(bool "$(setting MCP_SERVER_ENABLED true)"),
        "mail": $(json_string "$(setting MAIL_MAILER log)")
    },
    "stats": $stats
}
JSON
)

if [ "$1" = "--show" ]; then
    echo "$payload"
    exit 0
fi

if curl -fsS --connect-timeout 3 --max-time 5 \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -A "Investbrain/$(setting VERSION dev)" \
    --data-binary "$payload" \
    "$(setting TELEMETRY_URL https://investbra.in/telemetry)" > /dev/null 2>&1; then
    echo "Sent anonymous usage statistics (version, environment, enabled features and aggregate counts)."
else
    echo "Could not send anonymous usage statistics. Investbrain will keep starting normally."
fi

echo "See exactly what is sent with \"bash docker/telemetry.sh --show\". Turn it off with TELEMETRY_ENABLED=false."
exit 0
