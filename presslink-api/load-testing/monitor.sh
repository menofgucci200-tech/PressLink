#!/usr/bin/env bash
# Échantillonne CPU/RAM des workers PHP + connexions/slow queries MySQL
# pendant un run k6, pour compléter les métriques HTTP (k6 ne peut pas
# mesurer les ressources serveur). Voir load-testing/README.md.
#
# Usage : ./monitor.sh <fichier_csv_sortie> [intervalle_secondes]
# Arrêt : Ctrl+C, ou `kill` le PID affiché au démarrage.

set -euo pipefail

OUT="${1:?Usage: monitor.sh <output.csv> [interval_seconds]}"
INTERVAL="${2:-2}"
MYSQL_USER="${MONITOR_MYSQL_USER:-loadtest}"
MYSQL_PASS="${MONITOR_MYSQL_PASSWORD:-loadtest_pw_2026}"

echo "timestamp,php_cpu_pct,php_rss_mb,php_worker_count,mysql_threads_connected,mysql_slow_queries_cumulative,mysql_questions_cumulative" > "$OUT"

echo "Monitoring démarré (PID $$) → $OUT (intervalle ${INTERVAL}s). Ctrl+C pour arrêter."

trap 'echo; echo "Monitoring arrêté."; exit 0' INT TERM

while true; do
  ts=$(date +%s)

  # Somme %CPU / RSS de tous les processus "php artisan serve" (workers
  # forkés par PHP_CLI_SERVER_WORKERS inclus, ce sont des process séparés).
  php_stats=$(ps -eo pid,pcpu,rss,args --no-headers | awk '(/artisan serve/ || / -S /) && !/bash/ && !/awk/ {cpu+=$2; rss+=$3; n++} END {printf "%.1f,%.1f,%d", cpu+0, rss/1024+0, n+0}')

  mysql_stats=$(mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" -N -B -e "
    SELECT
      (SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME='Threads_connected'),
      (SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME='Slow_queries'),
      (SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME='Questions');
  " 2>/dev/null | tr '\t' ',' || echo "0,0,0")

  echo "${ts},${php_stats},${mysql_stats}" >> "$OUT"

  sleep "$INTERVAL"
done
