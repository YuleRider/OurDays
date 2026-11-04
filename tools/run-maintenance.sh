#!/bin/bash
set -euo pipefail
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
php_cli=${PHP_CLI:-php}
disabled=$("$php_cli" -r '$names=preg_split("/[\s,]+/",(string)ini_get("disable_functions"),-1,PREG_SPLIT_NO_EMPTY);echo implode(",",array_filter($names,function($name){return strcasecmp($name,"exec")!==0;}));')
if [[ "$disabled" =~ [^a-zA-Z0-9_,] ]]; then exit 1; fi
exec "$php_cli" -d "disable_functions=$disabled" "$script_dir/maintenance.php" "$@"
