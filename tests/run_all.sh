#!/usr/bin/env bash
# Fuehrt die komplette Regressions-Suite aus (siehe tests/README.md).
# Rueckgabewert 0 = alles gruen, 1 = mindestens ein Test rot.
#
# Assertions muessen explizit scharf geschaltet werden: PHP laeuft in der
# Standardkonfiguration mit zend.assertions=-1 (Assertions werden dann nicht
# einmal kompiliert), wodurch JEDER Test stillschweigend "gruen" waere.
set -uo pipefail
cd "$(dirname "$0")"

pass=0
failed=()

for f in test_*.php; do
    if php -d zend.assertions=1 -d assert.exception=1 "$f" > /dev/null 2>&1; then
        pass=$((pass + 1))
    else
        failed+=("$f")
    fi
done

total=$(ls test_*.php | wc -l | tr -d ' ')
echo "PASS: $pass / $total"

if [ ${#failed[@]} -gt 0 ]; then
    echo
    echo "FEHLGESCHLAGEN (${#failed[@]}):"
    for f in "${failed[@]}"; do
        echo "  - $f"
        php -d zend.assertions=1 -d assert.exception=1 "$f" 2>&1 | tail -3 | sed 's/^/      /'
    done
    exit 1
fi

echo "Alle Tests gruen."
